<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Homework;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Courses\LessonStatus;
use App\Http\Controllers\Controller;
use App\Lessons\LessonSections;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S8 -- the homework, on the photocopy sheet (spec 8.9.1).
 *
 * The homework is written by step three of the completion chain (5.4.1), which
 * runs behind the completion the student has already been shown. So "not there
 * yet" is an ordinary state rather than a missing resource, and this answers
 * with `state: pending` instead of a 404 -- a 404 would tell the screen the
 * homework does not exist, when what is true is that it has not arrived. The
 * chain retries, parks and can be finished by `lessons:retry-pipeline` (5.4.5),
 * so pending is recoverable in every case and the screen's job is to wait.
 *
 * Pre:  the student may view this lesson (LessonPolicy).
 * Post: the payload carries no `correct_option_id` and no `explanation`. Both
 *       arrive one question at a time, in the reply to `POST …/answers`, which
 *       is what 8.9.1's pre-answer DOM check is checking (5.2.3).
 */
class HomeworkController extends Controller
{
    public function show(Request $request, Lesson $lesson, Quizzes $quizzes): Response
    {
        $this->authorize('view', $lesson);

        $quiz = $quizzes->existing(QuizKind::Homework, $lesson);
        $course = $lesson->course;
        $sections = LessonSections::of($lesson);

        return Inertia::render('lessons/homework', [
            'lesson' => [
                'position' => $lesson->position,
                'title' => $lesson->syllabusEntry()['title'],
                // The rail draws the lesson the sheet belongs to as its
                // sections, all behind the student (the artboard's own
                // anatomy: ticks, then the current row on a wine band).
                'sections' => array_map(
                    static fn (int $index): string => $sections->title($index),
                    range(1, count($sections)),
                ),
            ],
            'course' => ['title' => $course->subtopic_title],
            'student' => $course->student->name,
            // The sheet is dated the day it was handed out, not the day it is
            // being looked at.
            'date' => ($quiz instanceof Quiz ? $quiz->created_at : now())->format('j M Y'),
            'state' => $quiz instanceof Quiz ? 'ready' : 'pending',
            'homework' => $quiz instanceof Quiz
                ? Homework::of($quiz, $course->student)->toArray()
                : null,
            'urls' => [
                'answers' => route('lessons.homework.answers.store', $lesson),
                'lesson' => route('lessons.show', $lesson),
                'next' => $this->next($lesson),
                'exam' => route('courses.final-exam.show', $course),
                // A demo's next move is signing up, whatever lesson follows.
                'sign_up' => $course->student->isDemo() ? route('demo.account.create') : null,
            ],
        ]);
    }

    /**
     * The lesson after this one, if the chain has unlocked it (8.6, S8's
     * "done" edge). Null when this was the last -- the exam is the move then,
     * and the screen has its URL either way.
     */
    private function next(Lesson $lesson): ?string
    {
        $next = $lesson->course->lessons
            ->firstWhere('position', $lesson->position + 1);

        return $next instanceof Lesson && $next->status !== LessonStatus::Locked
            ? route('lessons.show', $next)
            : null;
    }
}
