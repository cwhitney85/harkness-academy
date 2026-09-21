<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use App\Ai\Tier;
use App\Models\Prompt;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * What runs: the active version of every prompt (spec 15.1.5, ledger D65).
 *
 * The interface is the one the gateway has always called -- `get`, `has`,
 * `all`, `keys` -- and the render rules of 2.3.1 are unchanged; only where
 * the body and the tier come from has moved. The contract (`output`, `vars`)
 * still comes from the file, through the `prompts` row `prompts:sync` writes.
 *
 * Every AI call reads this, so the registry is read once and kept: memoised
 * within a request or job, and in the cache store across them until a
 * `Prompt` or `PromptVersion` write clears it. Plain arrays, because this
 * app's cache refuses to unserialize objects (`cache.serializable_classes`).
 *
 * @phpstan-type RegistryRow array{version: int, tier: string, output: string, vars: list<string>, body: string}
 */
final class PromptRepository
{
    /**
     * @throws UnknownPromptKey
     */
    public function get(string $key): PromptDefinition
    {
        return $this->define($key, $this->row($key));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, self::registry());
    }

    /**
     * The id of the version a call made now would run -- what its usage row
     * names (spec 15.1.6).
     *
     * @throws UnknownPromptKey
     */
    public function version(string $key): int
    {
        return $this->row($key)['version'];
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function all(): array
    {
        $definitions = [];

        foreach (self::registry() as $key => $row) {
            $definitions[$key] = $this->define($key, $row);
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::registry());
    }

    /**
     * @param  RegistryRow  $row
     */
    private function define(string $key, array $row): PromptDefinition
    {
        [$output, $schema] = PromptOutput::parse($row['output'])
            ?? throw new LogicException("Prompt [{$key}] is registered with output [{$row['output']}], which is not a contract this app knows.");

        $tier = Tier::tryFrom($row['tier'])
            ?? throw new LogicException("Prompt [{$key}] is registered at tier [{$row['tier']}], which is not a tier.");

        return new PromptDefinition($key, $tier, $output, $schema, $row['vars'], $row['body']);
    }

    /**
     * @return RegistryRow
     *
     * @throws UnknownPromptKey
     */
    private function row(string $key): array
    {
        return self::registry()[$key] ?? throw UnknownPromptKey::for($key, $this->keys());
    }

    /**
     * @return array<string, RegistryRow>
     */
    private static function registry(): array
    {
        return Cache::memo()->rememberForever(Prompt::CACHE_KEY, fn (): array => Prompt::query()
            ->with('activeVersion')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(function (Prompt $prompt): array {
                $active = $prompt->activeVersion ?? throw new LogicException(
                    "Prompt [{$prompt->key}] has no active version; nothing can run it (spec 15.1.1)."
                );

                return [$prompt->key => [
                    'version' => $active->id,
                    'tier' => $active->tier->value,
                    'output' => $prompt->output,
                    'vars' => $prompt->vars,
                    'body' => $active->body,
                ]];
            })
            ->all());
    }
}
