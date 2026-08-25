<?php

namespace App\Lessons\Pipeline;

use App\Lessons\LessonProgress;
use App\Models\Lesson;

/**
 * Step four: open the next lesson (spec 5.4.6).
 *
 * Last on purpose. The next lesson's system prompt carries the student's
 * working-knowledge document (5.1), so unlocking before step two would open a
 * lesson taught against a WK that does not know the previous one happened.
 * The homework interstitial is what hides the wait.
 *
 * The last lesson of a course unlocks nothing; the course moves to its final
 * exam through package 6.3, not through here.
 */
final class UnlockNextLesson extends PipelineJob
{
    public function step(): PipelineStep
    {
        return PipelineStep::Unlock;
    }

    protected function run(Lesson $lesson): void
    {
        app(LessonProgress::class)->unlockNext($lesson);
    }
}
