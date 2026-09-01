<?php

namespace App\Http\Controllers\Courses;

use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\Exceptions\SubtopicAlreadyOpen;
use App\Courses\Exceptions\UnusableSubtopicName;
use App\Courses\NextAction;
use App\Courses\SkillLevel;
use App\Courses\SubtopicName;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * Two refusals are ordinary outcomes rather than errors, and both send the
 * student back here with something to decide:
 *
 *   - a **withdrawn attempt** at the same subtopic and level asks D7b's
 *     question in D7b's words (8.7.6) instead of quietly starting a second
 *     run and orphaning the first;
 *   - a **course already open** in the subtopic offers the way back into it.
 *     The question above should have caught this; what reaches it is the
 *     double tap and the second tab (8.6).
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
        ]);
    }

    /**
     * Pre:  the student is signed in; `level` is a declared SkillLevel; and
     *       either a catalog subtopic or a usable free-text name is named.
     * Post: exactly one new course exists and the student is on its knowledge
     *       interview -- or nothing was created and they are back here with
     *       the question that stopped it.
     */
    public function store(Request $request, CreateCourse $creator, CourseTransitions $transitions): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:catalog,free_text'],
            'subtopic_id' => ['required_if:source,catalog', 'integer', 'exists:subtopics,id'],
            'module_slug' => ['required_if:source,free_text', 'string', 'exists:modules,slug'],
            'name' => ['required_if:source,free_text', 'string', 'max:120'],
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

        // 8.7.6. Asked before anything is written, because the alternative is
        // a second attempt the student did not ask for and cannot merge back.
        if (! ($validated['start_over'] ?? false)) {
            $withdrawn = $transitions->resumable($student, $module->slug, $name->slug, $level);

            if ($withdrawn instanceof Course) {
                return back()->with('resumable', $this->question($withdrawn));
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
     * D7b's question, in D7b's words.
     *
     * @return array<string, mixed>
     */
    private function question(Course $withdrawn): array
    {
        return [
            'course_id' => $withdrawn->id,
            'subtopic_title' => $withdrawn->subtopic_title,
            'level' => $withdrawn->skill_level->title(),
            'attempt' => $withdrawn->attempt,
            'set_aside_on' => $withdrawn->withdrawn_at?->toFormattedDayDateString(),
            'resume_url' => route('courses.resume', $withdrawn),
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
     * The preset catalog (spec 4.1) -- starting points, never a limit.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $modules = [];

        foreach (Module::query()->with('subtopics')->orderBy('position')->cursor() as $module) {
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
