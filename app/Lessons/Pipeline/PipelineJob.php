<?php

namespace App\Lessons\Pipeline;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One step of the completion chain (spec 5.4.1, 5.4.5).
 *
 * The skip-run-record shape lives here so no individual step can forget it.
 * The check and the record bracket the work: a step whose row exists does
 * nothing, and a step that throws records nothing, so a retry re-runs exactly
 * the work that did not land.
 *
 * Holds the lesson **id**, not the model. A job that serialized a Lesson would
 * carry the row as it looked at dispatch time; by the time step three runs,
 * steps one and two have written to it, and a stale copy would overwrite them.
 *
 * `tries` and `backoff` come from config so 5.4.5's "×3 with backoff" is one
 * number in one place rather than four class properties.
 */
abstract class PipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly int $lessonId) {}

    /**
     * Which step this job is, for the idempotency check.
     */
    abstract public function step(): PipelineStep;

    /**
     * Do the work. Called only when the step has not already run.
     */
    abstract protected function run(Lesson $lesson): void;

    public function tries(): int
    {
        return (int) config('lessons.pipeline.tries', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        /** @var list<int> $backoff */
        $backoff = config('lessons.pipeline.backoff', [10, 60, 300]);

        return $backoff;
    }

    public function handle(CompletionPipeline $pipeline): void
    {
        $lesson = Lesson::query()->whereHas('course')->find($this->lessonId);

        // The course was deleted or removed under us (16.2.8). There is nothing
        // left to summarize and nothing to park -- the chain is done because
        // its subject is gone.
        if ($lesson === null) {
            return;
        }

        if ($pipeline->hasCompleted($lesson, $this->step())) {
            return;
        }

        $this->run($lesson);

        $pipeline->record($lesson, $this->step());
    }
}
