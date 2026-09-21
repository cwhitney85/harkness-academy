<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\PromptSyncFailed;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

/**
 * Reads `prompts/` into the registry (spec 15.1.2, ledger D65).
 *
 * Pre:  every file in `prompts/` parses (the loader says so).
 * Post: each prompt's contract -- `output` and `vars` -- is the file's, and
 *       each prompt has at least one version.
 *
 * **The file owns the contract; the database owns the body.** A prompt's
 * `output` and `vars` are what the calling code depends on, so they are
 * rewritten from the file on every run. Its body and tier belong to whichever
 * version is active, and after the first run that is whatever the editor last
 * saved -- so the file's body is read once, to seed version 1, and never
 * again. Nothing here compares a file against a version, because there is
 * only one writer of a body and it is not this class.
 *
 * `prompts:pull` is the other direction, and the only one: it writes active
 * versions back into the files so the repo, the test seeder and git history
 * hold what is actually running (spec 15.2.5).
 *
 * The whole run is one transaction, so a refusal (spec 15.1.4) leaves the
 * registry exactly as it was.
 */
final class PromptSync
{
    public function __construct(private readonly PromptFiles $files) {}

    /**
     * @return array<string, PromptSyncOutcome> What happened to each key, in key order.
     */
    public function run(): array
    {
        return DB::transaction(function (): array {
            $outcomes = [];

            foreach ($this->files->all() as $key => $definition) {
                $outcomes[$key] = $this->sync($key, $definition);
            }

            return $outcomes;
        });
    }

    private function sync(string $key, PromptDefinition $definition): PromptSyncOutcome
    {
        $prompt = Prompt::query()->with('activeVersion')->firstOrNew(['key' => $key]);

        $this->assertTheFileStillDeclaresWhatIsRunning($prompt, $definition);

        $prompt->fill(['output' => $definition->outputContract(), 'vars' => $definition->vars])->save();

        if ($prompt->activeVersion !== null) {
            return PromptSyncOutcome::Registered;
        }

        $prompt->activate($prompt->versions()->create([
            'number' => 1,
            'body' => $definition->body,
            'tier' => $definition->tier,
            'source' => PromptSource::File,
            'file_hash' => $this->files->hash($key),
        ]));

        return PromptSyncOutcome::Seeded;
    }

    /**
     * Spec 15.1.4: a file may not drop a variable the running body uses.
     *
     * The running body outlives the file it was seeded from, so this holds for
     * a file-sourced version as much as an editor's: change a file to drop a
     * var and sync would write the narrower `vars` while version 1 kept
     * interpolating the dropped one. `PromptDefinition::render()` does not
     * catch that -- it holds the caller to the declared vars, and an
     * undeclared `{{ placeholder }}` is simply left in the prompt text -- so
     * this refusal is the only thing standing between a file edit and a
     * silently mangled prompt.
     *
     * @throws PromptSyncFailed
     */
    private function assertTheFileStillDeclaresWhatIsRunning(Prompt $prompt, PromptDefinition $definition): void
    {
        $active = $prompt->activeVersion;

        if ($active === null) {
            return;
        }

        $dropped = array_values(array_diff(PromptDefinition::placeholders($active->body), $definition->vars));

        if ($dropped !== []) {
            throw PromptSyncFailed::fileDropsVarsInUse(
                $prompt->key,
                $this->files->path($prompt->key),
                $active->number,
                $dropped,
            );
        }
    }
}
