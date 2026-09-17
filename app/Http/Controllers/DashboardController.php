<?php

namespace App\Http\Controllers;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\LessonStatus;
use App\Courses\NextAction;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S1 -- the dashboard (spec 8.10.1).
 *
 * The starter kit left a route with no controller and a page of placeholder
 * rectangles. What belongs here is the one thing no other screen can say: the
 * courses this student has, and the single next move in each.
 *
 * **The next move is not decided here.** `NextAction` is the flow map's resume
 * table written once (8.6), and the map says nothing else may decide it -- so
 * every card's URL comes from there, including the arms that lead somewhere
 * this controller has never heard of.
 *
 * Pre:  the student is signed in.
 * Post: every card names a URL of a route that exists, and no course the
 *       student owns is missing from the lists.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CourseTransitions $transitions): Response
    {
        $courses = Course::query()
            ->where('user_id', $request->user()->id)
            // NextAction reads the lessons of an active course and the card
            // counts them; a completed card names its module and NextAction
            // resolves the rollup from the same row. One load rather than
            // three queries per card. The student is what tells NextAction
            // whether a finished course ends at its exam or its offer (12.4.1).
            ->with(['lessons', 'module', 'student'])
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('dashboard', [
            'in_progress' => $courses
                ->filter(fn (Course $course): bool => $course->isOpen())
                ->map(fn (Course $course): array => [
                    ...$this->card($course),
                    'withdraw_url' => route('courses.withdraw', $course),
                ])
                ->values()
                ->all(),
            // Apart from the ones in progress, and never mixed in with them
            // (8.10.3): a course that is set aside is not something the
            // student is behind on.
            'set_aside' => $courses
                ->filter(fn (Course $course): bool => $course->isWithdrawn())
                ->map(fn (Course $course): array => $this->setAside($course, $transitions))
                ->values()
                ->all(),
            // A finished course is not gone: it is the one door to its
            // module's rollup (S12), which is the edge 8.6 draws out of S1.
            'completed' => $courses
                ->filter(fn (Course $course): bool => $course->isCompleted())
                ->map($this->card(...))
                ->values()
                ->all(),
            'urls' => [
                'create' => route('courses.create'),
            ],
        ]);
    }

    /**
     * A card for a course the student stepped away from.
     *
     * Either the way back in or the reason there is none -- asked of
     * `CourseTransitions`, which owns the rule, so the list cannot offer a
     * button that 422s (8.10.3, D7b).
     *
     * @return array<string, mixed>
     */
    private function setAside(Course $course, CourseTransitions $transitions): array
    {
        $refusal = $transitions->refusal($course);

        return [
            ...$this->card($course),
            'set_aside_on' => $course->withdrawn_at?->toFormattedDayDateString(),
            'resume_url' => $refusal === null ? route('courses.resume', $course) : null,
            'refusal' => $refusal?->explanation(),
        ];
    }

    /**
     * One course as a card reads it.
     *
     * @return array<string, mixed>
     */
    private function card(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->subtopic_title,
            'module' => $course->module?->title,
            'level' => $course->skill_level->title(),
            'attempt' => $course->attempt,
            'standing' => $course->standing()->title(),
            'detail' => $this->detail($course),
            'url' => NextAction::url($course),
        ];
    }

    /**
     * The second half of the standing line: what `standing` has not said.
     *
     * An open course's standing *is* its pipeline position, so there is
     * nothing to add unless it is being taught, where the count is the whole
     * story. A course set aside says only that it was set aside, so the
     * position it still holds -- and returns to (D7d) -- goes here.
     */
    private function detail(Course $course): ?string
    {
        if ($course->isCompleted()) {
            return null;
        }

        if ($course->status === CourseStatus::Active) {
            return $this->lessonsDone($course);
        }

        return $course->isWithdrawn() ? $course->status->title() : null;
    }

    /**
     * How far through the teaching a course is.
     *
     * Null before approval creates the lessons -- a course can stand at
     * `active` for the moment between the two.
     */
    private function lessonsDone(Course $course): ?string
    {
        $total = $course->lessons->count();

        if ($total === 0) {
            return null;
        }

        $done = $course->lessons
            ->filter(fn (Lesson $lesson): bool => $lesson->status === LessonStatus::Completed)
            ->count();

        return match (true) {
            $done === $total && $course->student->isDemo() => 'Every lesson done — keep your course',
            $done === $total => 'Every lesson done — the exam is next',
            $done === 0 => 'Lesson 1 is waiting',
            default => "{$done} of {$total} lessons done",
        };
    }
}
