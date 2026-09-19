<?php

namespace App\Ai;

use App\Admin\ViewingAs;
use App\Ai\Providers\Providers;
use App\Models\UsageEvent;
use App\Models\User;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Writes the usage_events row for a call (spec 2.2, 14.3.1).
 *
 * Postcondition: exactly one row per call reaching a provider, whether the
 * call succeeded or threw. Tokens are counted and cost priced here, at
 * write time, by the serving model's provider class from that model's catalog
 * prices -- so a later price change never silently rewrites what a past call
 * actually cost (spec 14.2.4). The plan is copied for the same reason: the
 * demo sweep deletes the user, and what a demo cost has to outlive them
 * (spec 14.3.4).
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
        ?User $user = null,
        ?int $courseId = null,
        ?int $lessonId = null,
    ): UsageEvent {
        $provider = Providers::for($model['provider']);
        $usage = $provider->tokens($usage);

        return UsageEvent::create([
            'user_id' => $user?->id,
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
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
            'plan' => $user?->plan,
            'cost_usd' => $provider->cost($model, $usage),
            'created_at' => now(),
        ]);
    }
}
