<?php

namespace App\Ai;

use App\Admin\ViewingAs;
use App\Models\UsageEvent;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Writes the usage_events row for a call (spec 2.2, 14.3.1).
 *
 * Postcondition: exactly one row per call reaching a provider, whether the
 * call succeeded or threw. Cost is priced here, at write time, from the
 * catalog prices of the model that served -- so a later price change never
 * silently rewrites what a past call actually cost.
 *
 * @phpstan-import-type ModelRow from Catalog
 */
final class UsageRecorder
{
    /**
     * @param  ModelRow  $model
     * @param  'ok'|'failed'|'failed_over'  $outcome
     */
    public function record(
        string $feature,
        string $promptKey,
        Tier $tier,
        array $model,
        string $outcome,
        Usage $usage,
        int $latencyMs,
        ?int $userId = null,
    ): UsageEvent {
        return UsageEvent::create([
            'user_id' => $userId,
            'acting_user_id' => ViewingAs::actingUserId(),
            'feature' => $feature,
            'prompt_key' => $promptKey,
            'provider' => $model['provider'],
            'tier' => $tier->value,
            'model' => $model['model'],
            'ai_model_id' => $model['id'],
            'outcome' => $outcome,
            'input_tokens' => $usage->promptTokens,
            'cached_input_tokens' => $usage->cacheReadInputTokens,
            'cache_write_tokens' => $usage->cacheWriteInputTokens,
            'output_tokens' => $usage->completionTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => $this->cost($model, $usage),
            'created_at' => now(),
        ]);
    }

    /**
     * Price a call in US dollars from a model's per-million-token prices.
     *
     * @param  ModelRow  $model
     */
    public function cost(array $model, Usage $usage): float
    {
        return round((
            $usage->promptTokens * $model['input']
            + $usage->cacheReadInputTokens * $model['cache_read']
            + $usage->cacheWriteInputTokens * $model['cache_write']
            + $usage->completionTokens * $model['output']
        ) / 1_000_000, 8);
    }
}
