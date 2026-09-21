<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\PromptSyncFailed;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

/**
 * Reads `prompts/` into the registry (spec 15.1.2, ledger D54).
 *
 * Pre:  every file in `prompts/` parses (the loader says so).
 * Post: each prompt's contract is the file's, and the file's current text is
 *       a version -- a new one only where the file changed since the last
 *       version made from it, so running this twice makes nothing the second
 *       time.
 *
 * A file version takes over only where the active version also came from a
 * file (spec 15.1.3): a deploy never overrides a live edit, it queues behind
 * one. The whole run is one transaction, so a refusal (spec 15.1.4) leaves
 * the registry exactly as it was.
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

        $hash = $this->files->hash($key);

        if ($prompt->latestFileVersion()?->file_hash === $hash) {
            return PromptSyncOutcome::Unchanged;
        }

        $version = $prompt->versions()->create([
            'number' => (int) $prompt->versions()->max('number') + 1,
            'body' => $definition->body,
            'tier' => $definition->tier,
            'source' => PromptSource::File,
            'file_hash' => $hash,
        ]);

        $active = $prompt->activeVersion;

        if ($active !== null && $active->source !== PromptSource::File) {
            return PromptSyncOutcome::Waiting;
        }

        $prompt->activate($version);

        return PromptSyncOutcome::Activated;
    }

    /**
     * Spec 15.1.4: a file may not drop a variable the running body uses.
     *
     * Only an admin version can be in that position -- a file version's body
     * and `vars` arrived together and the loader already held them to each
     * other.
     *
     * @throws PromptSyncFailed
     */
    private function assertTheFileStillDeclaresWhatIsRunning(Prompt $prompt, PromptDefinition $definition): void
    {
        $active = $prompt->activeVersion;

        if ($active === null || $active->source !== PromptSource::Admin) {
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
