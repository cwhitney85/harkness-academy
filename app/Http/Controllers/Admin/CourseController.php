<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Removals;
use App\Assessment\QuizDocument;
use App\Assessment\Sitting;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\SkillLevel;
use App\Http\Controllers\Controller;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\ExamAnswerSheet;
use App\Models\GenerationRequest;
use App\Models\Lesson;
use App\Models\LessonMessage;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Removal;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Courses (spec 17.2): every course, removed ones included, and one
 * course taken apart -- everything the app wrote for it and what that cost.
 *
 * Removing is `Removals`' (16.2). Restoring is Catalog → Removed's, which this
 * page posts to when the course was removed on its own; a course hidden by its
 * subtopic's or module's removal comes back with that, not from here.
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $standings = [...array_column(CourseStatus::cases(), 'value'), 'removed'];

        $filters = $request->validate([
            'standing' => ['nullable', Rule::in($standings)],
            'level' => ['nullable', Rule::enum(SkillLevel::class)],
            'module' => ['nullable', 'string'],
        ]);

        $courses = Course::withTrashed()
            ->with(['student' => fn ($student) => $student->withTrashed()->select('id', 'name')])
            ->withCost()
            ->when($filters['standing'] ?? null, fn (Builder $query, string $standing) => $this->standing($query, $standing))
            ->when($filters['level'] ?? null, fn (Builder $query, $level) => $query->where('skill_level', $level))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module_slug', $module))
            ->latest('id')
            ->simplePaginate(50)
            ->withQueryString()
            ->through(fn (Course $course): array => [
                'id' => $course->id,
                'student' => ['id' => $course->student->id, 'name' => $course->student->name],
                'module' => $course->module_slug,
                'title' => $course->subtopic_title,
                'level' => $course->skill_level->value,
                'attempt' => $course->attempt,
                'standing' => $course->standing()->value,
                'removed' => $course->trashed(),
                'cost' => (float) $course->getAttribute('cost'),
                'created' => $course->created_at->format('j M Y'),
            ]);

        return Inertia::render('admin/courses/index', [
            'courses' => $courses,
            'filters' => [
                'standing' => $filters['standing'] ?? null,
                'level' => isset($filters['level']) ? (int) $filters['level'] : null,
                'module' => $filters['module'] ?? null,
            ],
            'options' => [
                'standings' => $standings,
                'levels' => array_column(SkillLevel::cases(), 'value'),
                'modules' => Module::withTrashed()->orderBy('position')->pluck('slug'),
            ],
        ]);
    }

    /**
     * Everything 17.2.2 lists, removed or not. Criteria and working knowledge
     * are the latest version of each, read through the admin's one door to
     * memory (16.3.4).
     */
    public function show(Course $course, MemoryTree $memory): Response
    {
        $student = $course->student()->withTrashed()->sole();

        $latest = collect($memory->history($student))
            ->mapWithKeys(fn (array $document): array => [$document['path'] => last($document['versions'])['content']]);

        $syllabi = $course->syllabi()->orderBy('generation')->get();
        /** @var list<array{position: int, title: string}> $approved */
        $approved = $syllabi->firstWhere('approved_at', '!==', null)?->syllabus_json['lessons'] ?? [];
        $titles = collect($approved)->pluck('title', 'position');

        $lessons = $course->lessons()->with(['messages' => fn ($messages) => $messages->orderBy('position')])->orderBy('position')->get();

        $usage = UsageEvent::query()->where('course_id', $course->id);
        $attributed = (clone $usage)->whereNull('acting_user_id');

        return Inertia::render('admin/courses/show', [
            'course' => [
                'id' => $course->id,
                'student' => ['id' => $student->id, 'name' => $student->name],
                'module' => $course->module_slug,
                'title' => $course->subtopic_title,
                'level' => $course->skill_level->value,
                'attempt' => $course->attempt,
                'standing' => $course->standing()->value,
                'created' => $course->created_at->format('j M Y'),
                'removal' => $this->removal($course),
            ],
            'criteria' => $latest[CourseCriteria::pathFor($course)] ?? null,
            'working_knowledge' => $latest[$course->workingKnowledgePath()] ?? null,
            'syllabi' => $syllabi->map(fn (CourseSyllabus $syllabus): array => [
                'generation' => $syllabus->generation,
                'feedback' => $syllabus->feedback,
                'approved' => $syllabus->approved_at?->format('j M Y H:i'),
                'created' => $syllabus->created_at->format('j M Y H:i'),
                'syllabus' => $syllabus->syllabus_json,
            ])->all(),
            'lessons' => $lessons->map(fn (Lesson $lesson): array => [
                'id' => $lesson->id,
                'position' => $lesson->position,
                'title' => $titles[$lesson->position] ?? null,
                'status' => $lesson->status->value,
                'completed' => $lesson->completed_at?->format('j M Y H:i'),
                'messages' => $lesson->messages->map(fn (LessonMessage $message): array => [
                    'id' => $message->id,
                    'kind' => $message->kind->value,
                    'speaker' => $message->speaker->value,
                    'section' => $message->section_index,
                    'content' => $message->content,
                ])->all(),
            ])->all(),
            'quizzes' => $this->quizzes($course, $lessons->pluck('position', 'id')->all()),
            'generations' => GenerationRequest::query()->where('course_id', $course->id)->latest('id')->get()
                ->map(fn (GenerationRequest $request): array => [
                    'id' => $request->id,
                    'kind' => $request->kind->value,
                    'state' => $request->state->value,
                    'requested' => $request->requested_at->format('j M Y H:i'),
                    'failed' => $request->failed_at?->format('j M Y H:i'),
                    'failure' => $request->failure,
                ])->all(),
            'cost' => [
                'total' => (float) (clone $attributed)->sum('cost_usd'),
                'features' => (clone $attributed)
                    ->groupBy('feature')
                    ->selectRaw('feature, count(*) as calls, sum(cost_usd) as cost')
                    ->orderByDesc('cost')
                    ->get()
                    ->map(fn (UsageEvent $row): array => [
                        'feature' => $row->feature,
                        'calls' => (int) $row->getAttribute('calls'),
                        'cost' => (float) $row->getAttribute('cost'),
                    ])
                    ->all(),
            ],
            'usage' => (clone $usage)->with('actingUser:id,name')->latest('id')->get()
                ->map(fn (UsageEvent $event): array => [
                    'id' => $event->id,
                    'when' => $event->created_at->format('j M H:i:s'),
                    'feature' => $event->feature,
                    'prompt' => $event->prompt_key,
                    'model' => $event->model,
                    'lesson' => $event->lesson_id === null ? null : $lessons->find($event->lesson_id)?->position,
                    'input' => $event->input_tokens,
                    'cached' => $event->cached_input_tokens,
                    'output' => $event->output_tokens,
                    'latency' => $event->latency_ms,
                    'cost' => (float) $event->cost_usd,
                    'outcome' => $event->outcome,
                    'acting' => $event->actingUser?->name,
                ])
                ->all(),
        ]);
    }

    /**
     * Post: the course, its documents and its open requests are hidden, and
     * one `admin_actions` row says why (16.2.4).
     */
    public function remove(Request $request, Course $course, Removals $removals): RedirectResponse
    {
        $removals->remove(
            $request->user(),
            $course,
            $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'],
        );

        return back();
    }

    /**
     * The course's standing, as `Course::standing()` reads it, in SQL. A
     * removed course is `removed` whatever it was, and matches nothing else.
     *
     * @param  Builder<Course>  $query
     */
    private function standing(Builder $query, string $standing): void
    {
        match ($standing) {
            'removed' => $query->onlyTrashed(),
            CourseStatus::Completed->value => $query->withoutTrashed()->whereNotNull('completed_at'),
            CourseStatus::Withdrawn->value => $query->withoutTrashed()->whereNull('completed_at')->whereNotNull('withdrawn_at'),
            default => $query->withoutTrashed()->open()->where('status', $standing),
        };
    }

    /**
     * What removed this course, and whether it can be restored from here: only
     * a removal that named the course itself.
     *
     * @return array{id: int, own: bool, what: string, reason: string|null, when: string}|null
     */
    private function removal(Course $course): ?array
    {
        if (! $course->trashed()) {
            return null;
        }

        $action = Removal::query()->with('action')->findOrFail($course->removal_id)->action;

        return [
            'id' => $course->removal_id,
            'own' => $action->subject_type === $course->getMorphClass() && $action->subject_id === $course->id,
            'what' => strtolower(class_basename($action->subject_type)),
            'reason' => $action->reason,
            'when' => $action->created_at->format('j M Y H:i'),
        ];
    }

    /**
     * Every checkpoint, homework, exam and refresher the course holds, each
     * with its key -- an admin sees what a student may not -- and its sittings
     * marked as the student's own screen marks them. A final exam's sheet with
     * no marks yet is a sitting still open.
     *
     * @param  array<int, int>  $positions  lesson id => position
     * @return array<int, array<string, mixed>>
     */
    private function quizzes(Course $course, array $positions): array
    {
        $quizzes = Quiz::query()->where('course_id', $course->id)->with('attempts')->orderBy('id')->get();

        $sheets = ExamAnswerSheet::query()->whereIn('quiz_id', $quizzes->modelKeys())->get()->groupBy('quiz_id');

        return $quizzes->map(function (Quiz $quiz) use ($positions, $sheets): array {
            $document = QuizDocument::of($quiz);
            $marked = $quiz->attempts->groupBy('attempt');

            return [
                'id' => $quiz->id,
                'kind' => $quiz->kind->value,
                'lesson' => $quiz->quizzable_type === (new Lesson)->getMorphClass() ? $positions[$quiz->quizzable_id] ?? null : null,
                'section' => $quiz->section_index,
                'questions' => array_map(fn (array $question): array => [
                    'id' => (string) $question['id'],
                    'prompt' => $question['prompt'],
                    'options' => $question['options'],
                    'correct' => $question['correct_option_id'] ?? null,
                    'explanation' => $question['explanation'] ?? null,
                    'concept' => $question['concept_tag'] ?? null,
                ], $document->questions()),
                'sittings' => $marked->map(fn ($answers, int $number): array => [
                    ...Sitting::of($document, $number, $answers->mapWithKeys(fn (QuizAttempt $answer): array => [$answer->question_id => $answer->correct])->all())->toArray(),
                    'answers' => $answers->pluck('option_id', 'question_id')->all(),
                ])->values()->all(),
                'open' => ($sheets[$quiz->id] ?? collect())
                    ->reject(fn (ExamAnswerSheet $sheet): bool => $marked->has($sheet->attempt))
                    ->map(fn (ExamAnswerSheet $sheet): array => ['sitting' => $sheet->attempt, 'ticked' => count($sheet->selections)])
                    ->values()
                    ->all(),
            ];
        })->all();
    }
}
