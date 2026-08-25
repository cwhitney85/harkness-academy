<?php

namespace App\Lessons\Pipeline;

use App\Models\Lesson;
use App\Models\LessonPipelineStep;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What happens after a lesson is finished (spec 5.4.1, 5.4.5).
 *
 * **Progress never blocks on this.** The completion itself is synchronous and
 * irrevocable (LessonProgress::complete); everything here is downstream of a
 * student who has already been told they finished. That asymmetry is 5.4.5's
 * whole content, and it decides every failure question below: nothing in this
 * chain may be allowed to un-finish a lesson.
 *
 * The chain is ordered and idempotent per (lesson, step). Ordered because each
 * step reads what the one before it wrote; idempotent because it is dispatched
 * from three different places -- completion, a queue retry, and
 * `lessons:retry-pipeline` -- and a second summary would cost real money and
 * write a second WK version over a good one.
 */
final class CompletionPipeline
{
    public function __construct(private readonly LessonPipelineJobs $jobs) {}

    /**
     * Run the chain for a freshly completed lesson.
     *
     * Pre: the lesson is completed. The chain summarizes a transcript that is
     *      finished; running it on a live lesson would summarize half of one.
     * Post: the chain is queued. Nothing here waits for it.
     */
    public function dispatch(Lesson $lesson): void
    {
        $lessonId = $lesson->id;

        // Nothing this method does may reach the caller. 5.4.5's rule is that
        // progress never blocks on the pipeline, and the caller is a student
        // who has already been told they finished.
        //
        // Two ways a failure gets here. On a worker the chain's own `catch`
        // parks it and this call has long since returned; on the `sync`
        // driver -- which the test suite uses -- the chain runs inline and
        // rethrows after parking. Either way the lesson stays completed, and
        // an exception that arrived without parking anything (the queue
        // itself being unreachable, say) is parked here so the artisan
        // recovery of 5.4.5 still finds it.
        try {
            Bus::chain($this->jobs->forLesson($lesson))
                ->catch(function (Throwable $e) use ($lessonId): void {
                    self::park($lessonId, $e);
                })
                ->dispatch();
        } catch (Throwable $e) {
            $parked = Lesson::query()->whereKey($lessonId)->value('pipeline_parked_at');

            if ($parked === null) {
                self::park($lessonId, $e);
            }
        }
    }

    /**
     * Re-run whatever the chain has not finished (spec 5.4.5).
     *
     * The artisan escape hatch, and the reason parking is recoverable rather
     * than terminal. Completed steps skip themselves, so this resumes at the
     * step that broke rather than replaying the ones that worked.
     */
    public function resume(Lesson $lesson): void
    {
        $lesson->forceFill(['pipeline_parked_at' => null])->save();

        $this->dispatch($lesson);
    }

    public function hasCompleted(Lesson $lesson, PipelineStep $step): bool
    {
        return LessonPipelineStep::query()
            ->where('lesson_id', $lesson->id)
            ->where('step', $step->value)
            ->exists();
    }

    /**
     * Mark a step done.
     *
     * Post: `hasCompleted` is true for this step, and a second call is a
     *       no-op rather than a constraint violation -- two workers can reach
     *       the same step, and the unique index is what settles it.
     */
    public function record(Lesson $lesson, PipelineStep $step): void
    {
        try {
            LessonPipelineStep::query()->create([
                'lesson_id' => $lesson->id,
                'step' => $step,
                'completed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->hasCompleted($lesson, $step)) {
                throw $e;
            }
        }
    }

    /**
     * Whether every step has run.
     */
    public function isFinished(Lesson $lesson): bool
    {
        $done = LessonPipelineStep::query()
            ->where('lesson_id', $lesson->id)
            ->pluck('step')
            ->all();

        foreach (PipelineStep::inOrder() as $step) {
            if (! in_array($step, $done, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The chain gave up (spec 5.4.5).
     *
     * Static and keyed on the id rather than the model, because this closure
     * is serialized with the chain and must not carry a snapshot of a row that
     * three failed attempts have since changed.
     *
     * The alert is the point. A parked chain is invisible to the student --
     * they finished their lesson and moved on -- so nothing surfaces it except
     * this log line and 5.4.6's escape hatch opening the next lesson anyway.
     */
    public static function park(int $lessonId, Throwable $e): void
    {
        $lesson = Lesson::query()->find($lessonId);

        $lesson?->forceFill(['pipeline_parked_at' => now()])->save();

        Log::alert('Lesson completion pipeline parked; run `artisan lessons:retry-pipeline` to finish it.', [
            'lesson_id' => $lessonId,
            'command' => "php artisan lessons:retry-pipeline {$lessonId}",
            'reason' => $e->getMessage(),
        ]);
    }
}
