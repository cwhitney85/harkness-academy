<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\LessonRail;
use App\Http\Controllers\Controller;
use App\Lessons\LessonState;
use App\Models\Lesson;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S6 -- the lesson (spec 8.8.1), and S7, its completed state (8.8.6).
 *
 * The payload is `LessonState` verbatim (spec 5.3) plus the two things the
 * shell around it needs and 5.3 has no reason to know: a stream URL against
 * each section, and the course's other lessons for the rail. Decorating the
 * state rather than widening it keeps 5.3's restoration contract exactly what
 * package 5 wrote.
 *
 * A checkpoint's answer URL is not among them. A checkpoint can come into
 * existence *after* this render -- it is generated at the end of the section
 * the student is being taught -- so the one URL that could not be named here
 * is built from the generated route helper in `resources/js/routes`, which is
 * how the rest of the front end has always addressed a route it holds an id
 * for.
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
            'course' => $this->course($lesson),
            'urls' => [
                'question' => route('lessons.questions.store', $lesson),
                'explanation' => route('lessons.explanations.store', $lesson),
                'complete' => route('lessons.complete', $lesson),
                'refresher' => route('lessons.refreshers.store', $lesson),
                'homework' => route('lessons.homework.show', $lesson),
                'exam' => route('courses.final-exam.show', $lesson->course),
            ],
        ]);
    }

    /**
     * The course, as the rail draws it: every lesson, in order, marked.
     *
     * @return array<string, mixed>
     */
    private function course(Lesson $lesson): array
    {
        return [
            'title' => $lesson->course->subtopic_title,
            'lessons' => LessonRail::of($lesson->course),
        ];
    }
}
