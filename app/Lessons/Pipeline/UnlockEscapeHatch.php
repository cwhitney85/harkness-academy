<?php

namespace App\Lessons\Pipeline;

use App\Courses\LessonStatus;
use App\Lessons\LessonProgress;
use App\Models\Lesson;

/**
 * The next lesson opens even when the chain did not finish (spec 5.4.6).
 *
 * The trade this makes is explicit in the spec: a stale working-knowledge
 * document beats a stuck course. A student whose pipeline parked at 11pm
 * should not find their course frozen in the morning because a provider had a
 * bad minute -- the cost of teaching lesson 4 against a WK that stops at
 * lesson 2 is a slightly redundant lesson, and the cost of the alternative is
 * a student who cannot continue at all.
 *
 * The wait is measured from `pipeline_parked_at`, not from completion: the
 * retries in `lessons.pipeline.backoff` legitimately take minutes, and
 * starting the clock at completion would race the chain it exists to cover.
 */
final class UnlockEscapeHatch
{
    public function __construct(private readonly LessonProgress $progress) {}

    /**
     * Open the next lesson of every course whose chain has been parked too long.
     *
     * Post: for each lesson returned, its successor is `available` and the
     *       parked chain is left exactly as it was -- `lessons:retry-pipeline`
     *       can still finish it, and the WK it eventually writes is still
     *       correct, just late.
     *
     * @return list<Lesson> the lessons whose successors were opened
     */
    public function sweep(): array
    {
        $deadline = now()->subMinutes($this->waitMinutes());

        $stalled = Lesson::query()
            ->whereNotNull('completed_at')
            ->whereNotNull('pipeline_parked_at')
            ->where('pipeline_parked_at', '<=', $deadline)
            ->orderBy('id')
            ->get();

        $unlocked = [];

        foreach ($stalled as $lesson) {
            if ($this->progress->unlockNext($lesson) instanceof Lesson) {
                $unlocked[] = $lesson;
            }
        }

        return $unlocked;
    }

    /**
     * Whether this lesson's successor would be opened by a sweep right now.
     */
    public function isDue(Lesson $lesson): bool
    {
        return $lesson->isCompleted()
            && $lesson->pipeline_parked_at !== null
            && $lesson->pipeline_parked_at->lte(now()->subMinutes($this->waitMinutes()))
            && $lesson->next()?->status === LessonStatus::Locked;
    }

    private function waitMinutes(): int
    {
        return (int) config('lessons.unlock_escape_hatch_minutes', 15);
    }
}
