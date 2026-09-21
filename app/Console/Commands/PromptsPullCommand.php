<?php

namespace App\Console\Commands;

use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptFileWriter;
use App\Models\Prompt;
use Illuminate\Console\Command;

/**
 * Writes every active version back into `prompts/` (spec 15.2.5, ledger D65).
 *
 * The editor owns a prompt's text, so the files go stale the moment one is
 * tuned. This is the one way back: after it the repo, the test fixtures and
 * git history hold what is actually running. It is housekeeping, run by hand
 * and committed -- never on deploy, which reads these files.
 *
 * It only ever writes; a prompt with no file is a prompt whose file was
 * deleted after it seeded the registry, and that is worth reporting rather
 * than silently recreating.
 */
class PromptsPullCommand extends Command
{
    protected $signature = 'prompts:pull';

    protected $description = 'Spec 15.2.5: write each active prompt version back into prompts/';

    public function handle(PromptFiles $files): int
    {
        $rewritten = [];

        foreach (Prompt::query()->with('activeVersion')->orderBy('key')->get() as $prompt) {
            $path = $files->path($prompt->key);
            $text = PromptFileWriter::render($prompt);

            if (is_file($path) && file_get_contents($path) === $text) {
                continue;
            }

            if (! is_dir($directory = dirname($path))) {
                mkdir($directory, 0o755, true);
            }

            file_put_contents($path, $text);
            $rewritten[] = $prompt->key;
        }

        foreach ($rewritten as $key) {
            $this->components->twoColumnDetail($key, 'rewritten');
        }

        $this->components->info(count($rewritten) === 0
            ? 'Every prompt file already holds what is running.'
            : sprintf('%d prompt %s rewritten. Commit them.', count($rewritten), count($rewritten) === 1 ? 'file' : 'files'));

        return self::SUCCESS;
    }
}
