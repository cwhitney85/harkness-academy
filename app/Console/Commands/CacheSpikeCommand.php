<?php

namespace App\Console\Commands;

use App\Ai\GatewayAgent;
use App\Ai\Tier;
use Illuminate\Console\Command;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The prompt-caching spike of spec 2.4.
 *
 * Sends two calls that share a long system prefix and reports what Anthropic
 * billed for each. The done-condition is `cache_read_input_tokens > 0` on the
 * second call; anything else means the workaround in App\Ai\PromptCaching has
 * stopped reaching the wire and docs/anthropic-specific.md entry #1 is stale.
 *
 * This command makes real, billable calls. It is the only thing in the
 * repository that does.
 */
class CacheSpikeCommand extends Command
{
    protected $signature = 'ai:cache-spike
        {--tier=cheap : Capability tier to spike against (cheap, mid, top)}
        {--paragraphs=80 : Size of the shared prefix; the minimum cacheable length is model-specific (512-4096 tokens) and 40 was too short for haiku}';

    protected $description = 'Spec 2.4: verify cache_control reaches Anthropic and a repeated prefix is read from cache';

    public function handle(): int
    {
        $tier = Tier::from((string) $this->option('tier'));

        if (! $tier->isBillable()) {
            $this->components->error('Spike against a billable tier: cheap, mid or top.');

            return self::FAILURE;
        }

        if (blank(config('ai.providers.anthropic.key'))) {
            $this->components->error('ANTHROPIC_API_KEY is not set. This spike makes real calls.');

            return self::FAILURE;
        }

        $instructions = $this->prefix((int) $this->option('paragraphs'));

        $this->components->info(sprintf(
            'Tier %s -> %s. Shared prefix: %s characters.',
            $tier->value, $tier->model(), number_format(strlen($instructions))
        ));

        $agent = new GatewayAgent($instructions);

        $first = $this->call_('warm the cache', $agent, $tier, 'Reply with the single word: one.');
        $second = $this->call_('read the cache', $agent, $tier, 'Reply with the single word: two.');

        $this->newLine();

        if ($second->usage->cacheReadInputTokens > 0) {
            $this->components->info(sprintf(
                'PASS (spec 2.4): the second call read %s tokens from cache.',
                number_format($second->usage->cacheReadInputTokens)
            ));

            return self::SUCCESS;
        }

        $this->components->error(
            'FAIL (spec 2.4): cache_read_input_tokens was 0 on the second call. '
            .'Either the prefix is under this model\'s minimum cacheable length (raise --paragraphs), or '
            .'laravel/ai no longer lets provider options override `system` -- see docs/anthropic-specific.md entry #1.'
        );

        return self::FAILURE;
    }

    private function call_(string $label, GatewayAgent $agent, Tier $tier, string $prompt): AgentResponse
    {
        $startedAt = hrtime(true);

        $response = $agent->prompt($prompt, model: $tier->model());

        $usage = $response->usage;

        $this->newLine();
        $this->components->twoColumnDetail("<fg=cyan>{$label}</>", sprintf('%dms', (hrtime(true) - $startedAt) / 1_000_000));
        $this->components->twoColumnDetail('input (uncached)', number_format($usage->promptTokens));
        $this->components->twoColumnDetail('cache write', number_format($usage->cacheWriteInputTokens));
        $this->components->twoColumnDetail('cache read', number_format($usage->cacheReadInputTokens));
        $this->components->twoColumnDetail('output', number_format($usage->completionTokens));

        return $response;
    }

    /**
     * A deterministic prefix long enough for Anthropic to cache.
     *
     * Deterministic matters more than it looks: a timestamp or a random id
     * anywhere in the prefix is the classic silent cache invalidator, and this
     * spike exists to catch exactly that class of bug.
     */
    private function prefix(int $paragraphs): string
    {
        $paragraph = 'You are a tutor. You explain the concrete case before the abstract rule, you correct errors '
            .'plainly rather than softening them, and you never introduce more scaffolding than the student in '
            .'front of you actually needs to take the next step on their own.';

        return "You are running a prompt-caching spike for OpenTutor (spec 2.4).\n\n"
            .implode("\n\n", array_map(
                fn (int $i): string => "Instruction {$i}. {$paragraph}",
                range(1, max(1, $paragraphs)),
            ));
    }
}
