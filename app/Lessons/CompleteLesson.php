<?php

namespace App\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Pipeline\CompletionPipeline;
use App\Models\Lesson;

/**
 * The student finishes a lesson (spec 5.4.1).
 *
 * Two halves, and the split between them is the whole design. The synchronous
 * half is one row update and it cannot fail in a way that matters: the lesson
 * is completed, the completion screen is earned. The asynchronous half --
 * summary, working knowledge, homework, unlock -- is everything that takes a
 * model call, and none of it is allowed to hold the student up (5.4.5).
 *
 * Order matters: the row is stamped *before* the chain is queued. A chain
 * dispatched first could begin summarizing a lesson the database still calls
 * in-progress, and a queue that ran it synchronously (as the test suite does)
 * would summarize a lesson that had not finished.
 *
 * Pre: the lesson's last section has been taught and its course is open.
 * Post: the lesson is `completed` with `completed_at` stamped, and the chain
 *       is queued exactly once -- a second call finds it already completed and
 *       queues nothing.
 */
final class CompleteLesson
{
    public function __construct(
        private readonly LessonProgress $progress,
        private readonly CompletionPipeline $pipeline,
    ) {}

    /**
     * @throws CourseNotOpen|LessonNotOpenable
     */
    public function __invoke(Lesson $lesson): Lesson
    {
        if ($lesson->isCompleted()) {
            return $lesson;
        }

        $completed = $this->progress->complete(
            $lesson,
            LessonSections::of($lesson),
            new LessonTranscript($lesson),
        );

        $this->pipeline->dispatch($completed);

        return $completed;
    }
}
