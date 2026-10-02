<?php

namespace Database\Factories;

use App\Ai\Tier;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One AI call's receipt, served by the `mid` tier's model and belonging to
 * nobody until a test says whose.
 *
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = Tier::Mid->primary();

        return [
            'feature' => 'lesson.section',
            'prompt_key' => 'lesson.section',
            'provider' => $model['provider'],
            'tier' => Tier::Mid->value,
            'model' => $model['model'],
            'ai_model_id' => $model['id'],
            'outcome' => 'ok',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'latency_ms' => 1500,
            'cost_usd' => 0.01,
            'created_at' => now(),
        ];
    }

    /**
     * Caused by an admin acting as the student (spec 13.4.5).
     */
    public function actingAs(int $adminId): self
    {
        return $this->state(['acting_user_id' => $adminId]);
    }
}
