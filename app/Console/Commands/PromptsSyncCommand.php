<?php

namespace App\Console\Commands;

use App\Ai\Prompts\Exceptions\PromptException;
use App\Ai\Prompts\PromptSync;
use App\Ai\Prompts\PromptSyncOutcome;
use Illuminate\Console\Command;

/**
 * Reads `prompts/` into the registry (spec 15.1.2, ledger D54).
 *
 * Runs on every deploy, after `migrate` (docs/deploy.md), and at test
 * bootstrap. It is safe to run any number of times: a file that has not
 * changed since the version made from it makes no version.
 */
class PromptsSyncCommand extends Command
{
    protected $signature = 'prompts:sync';

    protected $description = 'Spec 15.1.2: make a prompt version for each file whose content changed';

    public function handle(PromptSync $sync): int
    {
        try {
            $outcomes = $sync->run();
        } catch (PromptException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($outcomes as $key => $outcome) {
            if ($outcome !== PromptSyncOutcome::Unchanged) {
                $this->components->twoColumnDetail($key, $outcome->value);
            }
        }

        $counts = array_count_values(array_map(fn (PromptSyncOutcome $o): string => $o->value, $outcomes));

        $this->components->info(sprintf(
            '%d prompts: %d activated, %d waiting behind a live edit, %d unchanged.',
            count($outcomes),
            $counts[PromptSyncOutcome::Activated->value] ?? 0,
            $counts[PromptSyncOutcome::Waiting->value] ?? 0,
            $counts[PromptSyncOutcome::Unchanged->value] ?? 0,
        ));

        return self::SUCCESS;
    }
}
