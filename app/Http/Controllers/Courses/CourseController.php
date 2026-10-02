<?php

namespace App\Http\Controllers\Courses;

use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\Exceptions\SubtopicAlreadyOpen;
use App\Courses\Exceptions\SubtopicBelongsElsewhere;
use App\Courses\Exceptions\SubtopicCheckUnavailable;
use App\Courses\Exceptions\SubtopicOutOfScope;
use App\Courses\Exceptions\UnusableSubtopicName;
use App\Courses\NextAction;
use App\Courses\SkillLevel;
use App\Courses\SubtopicName;
use App\Courses\SubtopicScopeCheck;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Starting a course -- screen S2 of the flow map (spec 8.7.1, 8.7.6).
 *
 * Package 4 shipped `CreateCourse` with no way to reach it; this is the door.
 * Both of its constructors are behind one form, because 4.1 made picking a
 * preset and typing a name the same act -- they resolve to the same slug, and
 * a screen that treated them as two features would be describing a difference
 * the engine does not have.
 *
 * Four refusals are ordinary outcomes rather than errors, and each sends the
 * student back here with something to decide:
 *
 *   - a **withdrawn attempt** at the same subtopic and level asks D7b's
 *     question in D7b's words (8.7.6) instead of quietly starting another
 *     run -- and with more than one, which to continue (16.4.2);
 *   - a **course already open** in the subtopic offers the way back into it.
 *     The question above should have caught this; what reaches it is the
 *     double tap and the second tab (8.6);
 *   - a typed subtopic that **belongs under another module** offers to start
 *     it there, and a typed subtopic **nothing here teaches** is declined
 *     outright (4.9, ledger D42);
 *   - a scope check that **could not be run** declines too, because failing
 *     closed at creation time costs a sentence (4.9.5).
 *
 * The last three are the only refusals the student reads in words the app
 * generates rather than words a model wrote: the check's own `reason` is
 * stored and never rendered (4.9.6).
 */
class CourseController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('courses/create', [
            'modules' => $this->catalog(),
            'levels' => $this->levels(),

            // Flashed by `store` when it declined to create rather than by a
            // link, so a reload of this URL is always the plain picker.
            'resumable' => $request->session()->get('resumable'),
            'occupied' => $request->session()->get('occupied'),
            'refused' => $request->session()->get('refused'),
        ]);
    }

    /**
     * Pre:  the student is signed in; `level` is a declared SkillLevel; and
     *       either a catalog subtopic or a usable free-text name is named.
     * Post: exactly one new course exists and the student is on its knowledge
     *       interview -- or nothing was created and they are back here with
     *       the question that stopped it.
     */
    public function store(
        Request $request,
        CreateCourse $creator,
        CourseTransitions $transitions,
        SubtopicScopeCheck $scope,
    ): RedirectResponse {
        // The three fields below are `nullable` because the form always sends
        // both halves of the either/or and one of them is empty -- and
        // `ConvertEmptyStringsToNull` makes that null before validation sees
        // it. Without `nullable`, picking a catalog subtopic was refused for
        // the free-text name it left blank, and vice versa. `required_if`
        // still refuses a null on the half that is being used, because null
        // is empty and empty is what `required` means.
        $validated = $request->validate([
            'source' => ['required', 'in:catalog,free_text'],
            // Only what the picker offers: an unlisted student row reached
            // through this door would skip the scope check (16.1.4).
            'subtopic_id' => ['nullable', 'required_if:source,catalog', 'integer', Rule::exists('subtopics', 'id')->where('listed', true)->whereNull('deleted_at')],
            'module_slug' => ['nullable', 'required_if:source,free_text', 'string', Rule::exists('modules', 'slug')->whereNull('deleted_at')],
            'name' => ['nullable', 'required_if:source,free_text', 'string', 'max:120'],
            'level' => ['required', 'integer', 'in:101,201,301'],
            // The student has answered D7b's question with "start from
            // scratch"; the same submission a second time must not ask again.
            'start_over' => ['boolean'],
        ]);

        $student = $request->user();
        $level = SkillLevel::from((int) $validated['level']);
        $fromCatalog = $validated['source'] === 'catalog';

        /** @var Subtopic|null $subtopic */
        $subtopic = $fromCatalog
            ? Subtopic::query()->with('module')->findOrFail($validated['subtopic_id'])
            : null;

        $module = $fromCatalog
            ? $subtopic->module
            : Module::query()->where('slug', $validated['module_slug'])->firstOrFail();

        try {
            $name = $subtopic instanceof Subtopic
                ? SubtopicName::fromCatalog($subtopic)
                : SubtopicName::fromFreeText($validated['name']);
        } catch (UnusableSubtopicName $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        // 4.9.4. Before D7b's question, so a misfiled name never raises a
        // resume prompt about a course that should not exist -- and before
        // anything is written, which is what makes a refusal cost a sentence
        // rather than two interviews. The catalog path skips it: a preset
        // subtopic belongs to its module by construction.
        if (! $fromCatalog) {
            try {
                $scope->check($student, $module, $name);
            } catch (SubtopicBelongsElsewhere|SubtopicOutOfScope|SubtopicCheckUnavailable $e) {
                return back()->with('refused', $this->refusal($e, (string) $validated['name']));
            }
        }

        // 8.7.6. Asked before anything is written, because the alternative is
        // a second attempt the student did not ask for and cannot merge back.
        if (! ($validated['start_over'] ?? false)) {
            $withdrawn = $transitions->resumable($student, $module->slug, $name->slug, $level);

            if ($withdrawn->isNotEmpty()) {
                return back()->with('resumable', $this->question(
                    $withdrawn,
                    $creator->nextAttempt($student, $module->slug, $name->slug, $level),
                ));
            }
        }

        try {
            $course = $subtopic instanceof Subtopic
                ? $creator->fromCatalog($student, $subtopic, $level)
                : $creator->fromFreeText($student, $module, $validated['name'], $level);
        } catch (SubtopicAlreadyOpen) {
            return back()->with('occupied', $this->openCourse($request, $module->slug, $name->slug));
        }

        return redirect()->route('courses.knowledge-interview.show', $course);
    }

    /**
     * A scope refusal, in our words and never the model's (spec 4.9.6).
     *
     * `typed` is echoed back exactly as it was submitted, not as it was
     * normalised: the screen keeps the notice up only while the field still
     * holds the name it is about, so editing the field puts it away.
     *
     * @return array<string, mixed>
     */
    private function refusal(
        SubtopicBelongsElsewhere|SubtopicOutOfScope|SubtopicCheckUnavailable $refusal,
        string $typed,
    ): array {
        $headline = match (true) {
            $refusal instanceof SubtopicBelongsElsewhere => 'Taught under another subject',
            $refusal instanceof SubtopicOutOfScope => 'Outside what we teach',
            default => 'Not checked just now',
        };

        $message = match (true) {
            $refusal instanceof SubtopicBelongsElsewhere => "«{$refusal->name->title}» isn't a {$refusal->chosen->title} "
                ."topic. It's one we teach under {$refusal->suggested->title}.",
            $refusal instanceof SubtopicOutOfScope => "We can't build a course around «{$refusal->name->title}.»",
            default => 'We couldn\'t check that topic just now.',
        };

        return [
            'typed' => $typed,
            'title' => $refusal->name->title,
            'headline' => $headline,
            'message' => $message,
            // The outage message already says to try again; the other two do
            // not, and a refusal the student believes is wrong has to leave
            // them somewhere other than a wall. There is no override: the
            // refusal means something or it means nothing (D42).
            'note' => $refusal instanceof SubtopicCheckUnavailable
                ? null
                : 'If you believe this is a mistake, please try again.',
            'start_under' => $refusal instanceof SubtopicBelongsElsewhere ? [
                'module_slug' => $refusal->suggested->slug,
                'module_title' => $refusal->suggested->title,
            ] : null,
        ];
    }

    /**
     * D7b's question, in D7b's words, over every attempt it could pick back
     * up (ledger D64).
     *
     * @param  Collection<int, Course>  $withdrawn  newest first, never empty
     * @return array<string, mixed>
     */
    private function question(Collection $withdrawn, int $nextAttempt): array
    {
        return [
            'subtopic_title' => $withdrawn->first()->subtopic_title,
            'level' => $withdrawn->first()->skill_level->title(),
            'next_attempt' => $nextAttempt,
            'attempts' => $withdrawn->map(fn (Course $course): array => [
                'course_id' => $course->id,
                'attempt' => $course->attempt,
                'standing' => $course->status->title(),
                'set_aside_on' => $course->withdrawn_at?->toFormattedDayDateString(),
                'resume_url' => route('courses.resume', $course),
            ])->all(),
        ];
    }

    /**
     * The course holding this subtopic's one open slot, and the way into it.
     *
     * @return array<string, mixed>|null
     */
    private function openCourse(Request $request, string $moduleSlug, string $subtopicSlug): ?array
    {
        $open = Course::query()
            ->forSubtopic($request->user(), $moduleSlug, $subtopicSlug)
            ->open()
            ->first();

        return $open instanceof Course ? [
            'course_id' => $open->id,
            'subtopic_title' => $open->subtopic_title,
            'level' => $open->skill_level->title(),
            'continue_url' => NextAction::url($open),
        ] : null;
    }

    /**
     * The preset catalog (spec 4.1) -- starting points, never a limit. Listed
     * rows only: a student's typed subtopic joins it when it is promoted
     * (16.1.4).
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $modules = [];

        // `get`, not `cursor`: a cursor drops eager loads, constraint and all,
        // and the lazy load it falls back to would offer unlisted rows.
        $listed = Module::query()->with(['subtopics' => fn ($subtopics) => $subtopics->listed()]);

        foreach ($listed->orderBy('position')->get() as $module) {
            $subtopics = [];

            foreach ($module->subtopics as $subtopic) {
                $subtopics[] = [
                    'id' => $subtopic->id,
                    'slug' => $subtopic->slug,
                    'title' => $subtopic->title,
                ];
            }

            $modules[] = [
                'slug' => $module->slug,
                'title' => $module->title,
                'subtopics' => $subtopics,
            ];
        }

        return $modules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function levels(): array
    {
        return array_map(
            fn (SkillLevel $level): array => [
                'value' => $level->value,
                'title' => $level->title(),
            ],
            SkillLevel::cases(),
        );
    }
}
