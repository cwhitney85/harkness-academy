<?php

namespace App\Http\Controllers;

use App\Courses\CourseStatus;
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
    public function __invoke(Request $request): Response
    {
        $courses = Course::query()
            ->where('user_id', $request->user()->id)
            // NextAction reads the lessons of an active course and the card
            // counts them; one load rather than two queries per card.
            ->with('lessons')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('dashboard', [
            'in_progress' => $courses
                ->filter(fn (Course $course): bool => $course->isOpen())
                ->map($this->card(...))
                ->values()
                ->all(),
            'urls' => [
                'create' => route('courses.create'),
            ],
        ]);
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
            'level' => $course->skill_level->title(),
            'attempt' => $course->attempt,
            'standing' => $course->standing()->title(),
            'detail' => $this->detail($course),
            'url' => NextAction::url($course),
        ];
    }

    /**
     * How far through the teaching a course is, when it is being taught.
     *
     * Only `active` has a count worth showing: the pipeline statuses are
     * already a position, and `standing` names it.
     */
    private function detail(Course $course): ?string
    {
        if ($course->standing() !== CourseStatus::Active) {
            return null;
        }

        $total = $course->lessons->count();

        if ($total === 0) {
            return null;
        }

        $done = $course->lessons
            ->filter(fn (Lesson $lesson): bool => $lesson->status === LessonStatus::Completed)
            ->count();

        return match (true) {
            $done === $total => 'Every lesson done — the exam is next',
            $done === 0 => 'Lesson 1 is waiting',
            default => "{$done} of {$total} lessons done",
        };
    }
}
