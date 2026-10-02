<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Admin\Exceptions\RestoreRefused;
use App\Admin\Removals;
use App\Courses\SubtopicOrigin;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Removal;
use App\Models\Subtopic;
use App\Models\SubtopicRefusal;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Catalog: the modules and subtopics, what is removed, Demand and
 * Refusals (spec 16.5, 16.1.5, 16.1.6, ledgers D57, D59).
 *
 * Removing and restoring are `Removals`' (16.2); this asks why, and says
 * back to the admin when a restore is refused.
 *
 * Demand is the student subtopics nobody has listed yet, most-typed first;
 * promoting one puts it in the picker. Refusals is every name the scope check
 * turned away (4.9.7), with the model's reason -- which a student never reads
 * (4.9.6) and an admin does.
 */
class CatalogController extends Controller
{
    /**
     * Every module and subtopic not removed, unlisted student rows included,
     * each subtopic with the courses a removal of it would hide (16.5.1).
     */
    public function index(): Response
    {
        $courses = Course::query()
            ->groupBy('subtopic_id')
            ->selectRaw('subtopic_id, count(*) as n')
            ->pluck('n', 'subtopic_id');

        return Inertia::render('admin/catalog/index', [
            'modules' => Module::query()->with('subtopics')->orderBy('position')->get()
                ->map(fn (Module $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'subtopics' => $module->subtopics->map(fn (Subtopic $subtopic): array => [
                        'id' => $subtopic->id,
                        'title' => $subtopic->title,
                        'slug' => $subtopic->slug,
                        'listed' => $subtopic->listed,
                        'courses' => (int) ($courses[$subtopic->id] ?? 0),
                    ])->all(),
                ])
                ->all(),
        ]);
    }

    /**
     * A subtopic's title, which is what the picker shows (16.5.2). The slug,
     * and so every course's memory path, stays frozen.
     */
    public function retitle(Request $request, Subtopic $subtopic, ActionLog $log): RedirectResponse
    {
        $title = $request->validate(['title' => ['required', 'string', 'max:120']])['title'];

        $was = $subtopic->title;

        DB::transaction(function () use ($request, $subtopic, $title, $was, $log): void {
            $subtopic->update(['title' => $title]);

            $log->record(
                $request->user(),
                AdminActionType::SubtopicRetitled,
                $subtopic,
                payload: ['title' => ['was' => $was, 'now' => $title]],
            );
        });

        return back();
    }

    public function removeModule(Request $request, Module $module, Removals $removals): RedirectResponse
    {
        $removals->remove($request->user(), $module, $this->reason($request));

        return back();
    }

    public function removeSubtopic(Request $request, Subtopic $subtopic, Removals $removals): RedirectResponse
    {
        $removals->remove($request->user(), $subtopic, $this->reason($request));

        return back();
    }

    /**
     * Every removal still in effect, newest first, whatever it removed: a
     * course's removal is restored here too (16.5.1, 17.2.3). One whose
     * course the demo sweep has since deleted hides nothing, so it is not.
     */
    public function removed(): Response
    {
        $removals = Removal::with(['action.actor' => fn ($actor) => $actor->withTrashed()->select('id', 'name'), 'action.subject' => fn (MorphTo $subject) => $subject->withTrashed()->morphWith([
            Subtopic::class => ['module' => fn ($module) => $module->withTrashed()],
            Course::class => ['student' => fn ($student) => $student->withTrashed()],
        ])])
            ->latest('id')
            ->get()
            ->filter(function (Removal $removal): bool {
                /** @var Module|Subtopic|Course|null $subject */
                $subject = $removal->action->subject;

                return $subject?->removal_id === $removal->id;
            });

        $courses = Course::onlyTrashed()
            ->whereIn('removal_id', $removals->modelKeys())
            ->groupBy('removal_id')
            ->selectRaw('removal_id, count(*) as n')
            ->pluck('n', 'removal_id');

        return Inertia::render('admin/catalog/removed', [
            'removals' => $removals->map(function (Removal $removal) use ($courses): array {
                /** @var Module|Subtopic|Course $subject the filter above kept only removals whose subject exists */
                $subject = $removal->action->subject;

                return [
                    'id' => $removal->id,
                    'kind' => strtolower(class_basename($subject)),
                    'title' => $subject instanceof Course ? $subject->subtopic_title : $subject->title,
                    'context' => match (true) {
                        $subject instanceof Module => null,
                        $subject instanceof Subtopic => $subject->module->title,
                        $subject instanceof Course => $subject->skill_level->pathSegment()." attempt {$subject->attempt}, {$subject->student->name}",
                    },
                    'when' => $removal->action->created_at->format('j M Y H:i'),
                    'by' => $removal->action->actor->name,
                    'reason' => $removal->action->reason,
                    'courses' => (int) ($courses[$removal->id] ?? 0),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Post: the removal's rows are back, or it was refused, and the refusal
     * -- naming what stands in the way (16.2.6) -- is on the row it was asked
     * from. Either way `Removals` logged it.
     */
    public function restore(Request $request, Removal $removal, Removals $removals): RedirectResponse
    {
        abort_unless($removal->subject()->removal_id === $removal->id, 404);

        try {
            $removals->restore($request->user(), $removal, $this->reason($request));
        } catch (RestoreRefused $refused) {
            return back()->withErrors(['restore' => $refused->getMessage()]);
        }

        return back();
    }

    public function demand(): Response
    {
        $subtopics = DB::table('subtopics')
            ->join('modules', 'modules.id', '=', 'subtopics.module_id')
            ->leftJoin('courses', fn ($join) => $join->on('courses.subtopic_id', '=', 'subtopics.id')->whereNull('courses.deleted_at'))
            ->whereNull('subtopics.deleted_at')
            ->whereNull('modules.deleted_at')
            ->where('subtopics.origin', SubtopicOrigin::Student->value)
            ->where('subtopics.listed', false)
            ->groupBy('subtopics.id', 'modules.id')
            ->select('subtopics.id', 'subtopics.title', 'subtopics.slug', 'modules.title as module')
            ->selectRaw('count(courses.id) as courses, count(distinct courses.user_id) as students')
            ->selectRaw('min(courses.created_at) as first_seen, max(courses.created_at) as last_seen')
            ->orderByDesc('students')
            ->orderByDesc('courses')
            ->orderBy('subtopics.title')
            ->get();

        return Inertia::render('admin/catalog/demand', [
            'subtopics' => $subtopics->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'module' => $row->module,
                'courses' => (int) $row->courses,
                'students' => (int) $row->students,
                'first_seen' => $this->day($row->first_seen),
                'last_seen' => $this->day($row->last_seen),
            ])->all(),
        ]);
    }

    /**
     * List a student subtopic, optionally under a better title (16.1.5).
     *
     * Post: the row is listed, so the picker offers it and the scope check
     * counts it as a starting point; its slug -- and so every existing
     * course's memory path -- is unchanged; one `admin_actions` row.
     */
    public function promote(Request $request, Subtopic $subtopic, ActionLog $log): RedirectResponse
    {
        abort_unless($subtopic->origin === SubtopicOrigin::Student && ! $subtopic->listed, 404);

        $validated = $request->validate(['title' => ['nullable', 'string', 'max:120']]);

        $was = $subtopic->title;

        DB::transaction(function () use ($request, $subtopic, $validated, $was, $log): void {
            $subtopic->update([
                'listed' => true,
                'title' => trim($validated['title'] ?? '') ?: $was,
            ]);

            $log->record(
                $request->user(),
                AdminActionType::SubtopicPromoted,
                $subtopic,
                payload: ['title' => ['was' => $was, 'now' => $subtopic->title]],
            );
        });

        return back();
    }

    public function refusals(): Response
    {
        $modules = Module::query()->pluck('title', 'slug');

        return Inertia::render('admin/catalog/refusals', [
            'refusals' => SubtopicRefusal::query()
                ->with('student:id,name,email')
                ->latest('id')
                ->get()
                ->map(fn (SubtopicRefusal $refusal): array => [
                    'id' => $refusal->id,
                    'when' => $refusal->created_at->format('j M Y H:i'),
                    'typed' => $refusal->typed_name,
                    'module' => $modules[$refusal->module_slug] ?? $refusal->module_slug,
                    'verdict' => $refusal->verdict,
                    'suggested' => $refusal->suggested_module_slug === null
                        ? null
                        : $modules[$refusal->suggested_module_slug] ?? $refusal->suggested_module_slug,
                    'reason' => $refusal->reason,
                    'student' => [
                        'name' => $refusal->student->name,
                        'email' => $refusal->student->email,
                        'url' => route('admin.users.show', $refusal->user_id),
                    ],
                ])
                ->all(),
        ]);
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
    }

    private function day(?string $timestamp): ?string
    {
        return $timestamp === null ? null : Carbon::parse($timestamp)->format('j M Y');
    }
}
