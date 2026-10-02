<?php

namespace App\Console\Commands;

use App\Ai\Prompts\Exceptions\PromptException;
use App\Ai\Prompts\PromptSync;
use App\Ai\Prompts\PromptSyncOutcome;
use App\Models\Prompt;
use Illuminate\Console\Command;

/**
 * Reads `prompts/` into the registry (spec 15.1.2, ledger D65).
 *
 * Runs on every deploy, after `migrate` (docs/deploy.md), and at test
 * bootstrap. It writes each prompt's contract and seeds a body only for a
 * prompt that has none, so running it any number of times -- and running it
 * against a registry the editor has since tuned -- changes no prompt's text.
 * It names every prompt whose running text leaves out a var its file now
 * declares (spec 15.1.8).
 */
class PromptsSyncCommand extends Command
{
    protected $signature = 'prompts:sync';

    protected $description = "Spec 15.1.2: write each prompt file's contract, and seed version 1 where there is none";

    public function handle(PromptSync $sync): int
    {
        try {
            $outcomes = $sync->run();
        } catch (PromptException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $seeded = array_keys(array_filter($outcomes, fn (PromptSyncOutcome $o): bool => $o === PromptSyncOutcome::Seeded));

        foreach ($seeded as $key) {
            $this->components->twoColumnDetail($key, PromptSyncOutcome::Seeded->value);
        }

        $this->components->info(sprintf(
            '%d prompts registered, %d seeded. Every other body is the editor\'s and was left alone.',
            count($outcomes),
            count($seeded),
        ));

        // Accepted, not refused (15.1.8, 15.1.9): the body can only take the
        // new var once the contract declares it, which is what just happened.
        foreach (Prompt::query()->with('activeVersion')->orderBy('key')->get() as $prompt) {
            $unused = $prompt->varsUnusedBy($prompt->activeVersion->body);

            if ($unused !== []) {
                $this->components->warn(sprintf(
                    '[%s] declares %s, which its running text does not use. Edit it in Admin → Prompts; until then its calls run without that material.',
                    $prompt->key,
                    implode(', ', $unused),
                ));
            }
        }

        return self::SUCCESS;
    }
}
