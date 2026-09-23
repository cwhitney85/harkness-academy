<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Courses\SubtopicOrigin;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\SubtopicRefusal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Catalog: Demand and Refusals (spec 16.1.5, 16.1.6, ledger D59).
 *
 * Demand is the student subtopics nobody has listed yet, most-typed first;
 * promoting one puts it in the picker. Refusals is every name the scope check
 * turned away (4.9.7), with the model's reason -- which a student never reads
 * (4.9.6) and an admin does.
 */
class CatalogController extends Controller
{
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
                        // The student list until 17.1.2 gives each student a
                        // page of their own.
                        'url' => route('admin.home'),
                    ],
                ])
                ->all(),
        ]);
    }

    private function day(?string $timestamp): ?string
    {
        return $timestamp === null ? null : Carbon::parse($timestamp)->format('j M Y');
    }
}
