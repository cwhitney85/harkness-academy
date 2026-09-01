<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\LessonStatus;
use App\Http\Controllers\Controller;
use App\Lessons\LessonState;
use App\Models\Lesson;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S6 -- the lesson (spec 8.8.1), and S7, its completed state (8.8.6).
 *
 * The payload is `LessonState` verbatim (spec 5.3) plus the three things the
 * shell around it needs and 5.3 has no reason to know: the course's other
 * lessons, for the rail; a URL against each section and each checkpoint, so
 * nothing is assembled from an id in the browser; and the moves a finished
 * lesson offers. Decorating the state rather than widening it keeps 5.3's
 * restoration contract exactly what package 5 wrote.
 *
 * Pre:  the student may view this lesson (LessonPolicy).
 * Post: every URL in the payload names a route that exists, and a locked
 *       lesson carries none -- the rail draws what the student may open from
 *       the presence of a URL rather than from a second rule of its own.
 */
class LessonController extends Controller
{
    public function show(Lesson $lesson): Response
    {
        $this->authorize('view', $lesson);

        $state = LessonState::of($lesson)->toArray();

        return Inertia::render('lessons/show', [
            ...$state,
            'sections' => array_map(
                static fn (array $section): array => [
                    ...$section,
                    'stream' => route('lessons.sections.stream', [$lesson, $section['index']]),
                ],
                $state['sections'],
            ),
            'checkpoints' => array_map(
                static fn (array $checkpoint): array => [
                    ...$checkpoint,
                    'answer' => route('lessons.checkpoints.answers.store', [$lesson, $checkpoint['checkpoint_id']]),
                ],
                $state['checkpoints'],
            ),
            'course' => $this->course($lesson),
            'urls' => [
                'question' => route('lessons.questions.store', $lesson),
                'explanation' => route('lessons.explanations.store', $lesson),
                'complete' => route('lessons.complete', $lesson),
                'homework' => route('lessons.homework.show', $lesson),
                'exam' => route('courses.final-exam.show', $lesson->course),
            ],
        ]);
    }

    /**
     * The course, as the rail draws it: every lesson, in order, marked.
     *
     * Titles come off the approved syllabus in one read rather than through
     * `Lesson::syllabusEntry()` per row, which would parse the same JSON once
     * per lesson to answer the same question.
     *
     * @return array<string, mixed>
     */
    private function course(Lesson $lesson): array
    {
        $course = $lesson->course;

        /** @var list<array<string, mixed>> $entries */
        $entries = $course->approvedSyllabus->syllabus_json['lessons'];
        $titles = array_column($entries, 'title', 'position');

        return [
            'title' => $course->subtopic_title,
            'lessons' => $course->lessons->map(static fn (Lesson $each): array => [
                'position' => $each->position,
                'title' => $titles[$each->position] ?? "Lesson {$each->position}",
                'completed' => $each->status === LessonStatus::Completed,
                'url' => $each->status === LessonStatus::Locked
                    ? null
                    : route('lessons.show', $each),
            ])->all(),
        ];
    }
}
