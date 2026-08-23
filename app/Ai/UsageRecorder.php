<?php

namespace App\Ai;

use App\Models\UsageEvent;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Writes the usage_events row for a call (spec 2.2).
 *
 * Postcondition: exactly one row per call reaching a provider, whether the
 * call succeeded or threw. Cost is priced here, at write time, from the tier
 * rates in config/ai.php -- so a later price change never silently rewrites
 * what a past call actually cost.
 */
final class UsageRecorder
{
    public function record(
        string $feature,
        string $promptKey,
        string $provider,
        Tier $tier,
        string $model,
        Usage $usage,
        int $latencyMs,
        ?int $userId = null,
    ): UsageEvent {
        return UsageEvent::create([
            'user_id' => $userId,
            'feature' => $feature,
            'prompt_key' => $promptKey,
            'provider' => $provider,
            'tier' => $tier->value,
            'model' => $model,
            'input_tokens' => $usage->promptTokens,
            'cached_input_tokens' => $usage->cacheReadInputTokens,
            'cache_write_tokens' => $usage->cacheWriteInputTokens,
            'output_tokens' => $usage->completionTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => $this->cost($tier, $usage),
            'created_at' => now(),
        ]);
    }

    /**
     * Price a call in US dollars.
     *
     * Anthropic bills a cache read and a cache write as multiples of the input
     * rate; both multipliers live alongside the tier rates in config/ai.php.
     */
    public function cost(Tier $tier, Usage $usage): float
    {
        $pricing = $tier->pricing();

        $read = (float) config('ai.prompt_caching.read_multiplier', 0.1);
        $write = (float) config('ai.prompt_caching.write_multiplier', 1.25);

        $inputUnits = $usage->promptTokens
            + ($usage->cacheReadInputTokens * $read)
            + ($usage->cacheWriteInputTokens * $write);

        return round(
            ($inputUnits * $pricing['input'] + $usage->completionTokens * $pricing['output']) / 1_000_000,
            8,
        );
    }
}
