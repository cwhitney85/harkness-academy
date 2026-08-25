<?php

namespace App\Console\Commands;

use App\Lessons\Pipeline\CompletionPipeline;
use App\Lessons\Pipeline\PipelineStep;
use App\Models\Lesson;
use Illuminate\Console\Command;

/**
 * The hand-crank of spec 5.4.5.
 *
 * A parked chain is invisible to the student -- they finished their lesson and
 * moved on -- so the alert that parks it names this command, and this command
 * is what finishes the work. Completed steps skip themselves, so it resumes at
 * the step that broke.
 */
class RetryLessonPipelineCommand extends Command
{
    protected $signature = 'lessons:retry-pipeline {lesson : The lesson id}';

    protected $description = 'Spec 5.4.5: finish a parked lesson completion chain';

    public function handle(CompletionPipeline $pipeline): int
    {
        $lesson = Lesson::query()->find((int) $this->argument('lesson'));

        if ($lesson === null) {
            $this->components->error("No lesson [{$this->argument('lesson')}].");

            return self::FAILURE;
        }

        if (! $lesson->isCompleted()) {
            $this->components->error("Lesson [{$lesson->id}] is not completed; there is nothing to summarize.");

            return self::FAILURE;
        }

        if ($pipeline->isFinished($lesson)) {
            $this->components->info("Lesson [{$lesson->id}] has already run every step.");

            return self::SUCCESS;
        }

        foreach (PipelineStep::inOrder() as $step) {
            $this->components->twoColumnDetail(
                $step->value,
                $pipeline->hasCompleted($lesson, $step) ? '<fg=green>done</>' : '<fg=yellow>pending</>',
            );
        }

        $pipeline->resume($lesson);

        $this->components->info("Re-dispatched the completion chain for lesson [{$lesson->id}].");

        return self::SUCCESS;
    }
}
