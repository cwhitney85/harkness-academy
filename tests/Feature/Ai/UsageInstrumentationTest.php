<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Providers\AnthropicProvider;
use App\Ai\Tier;
use App\Ai\UsageRecorder;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec 2.2 -- every call writes usage_events, and a demo cycle yields a
 * per-feature cost report from SQL alone.
 */
class UsageInstrumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function a_call_records_its_tokens_latency_tier_and_model(): void
    {
        GatewayAgent::fake([
            new TextResponse(
                'Explained.',
                new Usage(promptTokens: 320, completionTokens: 96, cacheWriteInputTokens: 1024, cacheReadInputTokens: 2048),
                new Meta('anthropic', Tier::Cheap->model()),
            ),
        ]);

        $this->explain();

        $event = UsageEvent::sole();

        $this->assertSame('lesson.explainer', $event->feature);
        $this->assertSame('explainer.select', $event->prompt_key);
        $this->assertSame('anthropic', $event->provider);
        $this->assertSame(Tier::Cheap->value, $event->tier);
        $this->assertSame(Tier::Cheap->model(), $event->model);
        $this->assertSame(320, $event->input_tokens);
        $this->assertSame(2048, $event->cached_input_tokens);
        $this->assertSame(1024, $event->cache_write_tokens);
        $this->assertSame(96, $event->output_tokens);
        $this->assertGreaterThanOrEqual(0, $event->latency_ms);
    }

    #[Test]
    public function a_call_that_throws_still_writes_its_row(): void
    {
        GatewayAgent::fake(fn (): never => throw new RuntimeException('provider exploded'));

        try {
            $this->explain();
            $this->fail('The provider exception should have propagated.');
        } catch (RuntimeException) {
            // expected
        }

        $event = UsageEvent::sole();

        $this->assertSame('lesson.explainer', $event->feature);
        $this->assertSame(0, $event->output_tokens);
        $this->assertSame('0.00000000', (string) $event->cost_usd);
    }

    #[Test]
    public function a_demo_cycle_yields_a_per_feature_cost_report_from_sql_alone(): void
    {
        $user = User::factory()->create();

        $cycle = [
            ['course.knowledge_interview', 'wk.create', Tier::Mid, new Usage(4_000, 800)],
            ['course.goals_interview', 'criteria.create', Tier::Mid, new Usage(3_500, 700)],
            ['course.syllabus', 'syllabus.generate', Tier::Top, new Usage(6_000, 2_500)],
            ['lesson.checkpoint', 'checkpoint.generate', Tier::Cheap, new Usage(1_200, 400, 0, 8_000)],
            ['lesson.checkpoint', 'checkpoint.generate', Tier::Cheap, new Usage(1_100, 380, 0, 8_000)],
            ['exam.final', 'exam.final', Tier::Top, new Usage(9_000, 3_000)],
        ];

        $recorder = new UsageRecorder;
        $prompts = app(PromptRepository::class);

        foreach ($cycle as [$feature, $promptKey, $tier, $usage]) {
            $recorder->record(
                $feature, $promptKey, $prompts->version($promptKey), $tier, $tier->primary(), 'ok', $usage, 1_200, $user,
            );
        }

        $report = DB::table('usage_events')
            ->selectRaw('feature, count(*) as calls, sum(input_tokens + cached_input_tokens + cache_write_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(cost_usd) as cost_usd')
            ->groupBy('feature')
            ->orderByDesc('cost_usd')
            ->get();

        $this->assertCount(5, $report, 'One row per feature exercised by the cycle.');

        $costliest = $report->first();

        $this->assertSame('exam.final', $costliest->feature);
        $this->assertGreaterThan(0, (float) $costliest->cost_usd);

        $this->assertSame(2, (int) $report->firstWhere('feature', 'lesson.checkpoint')->calls);

        $total = (float) DB::table('usage_events')->sum('cost_usd');

        $this->assertEqualsWithDelta(
            array_sum(array_map(
                fn (array $call): float => (new AnthropicProvider)->cost($call[2]->primary(), $call[3]),
                $cycle,
            )),
            $total,
            0.00000001,
        );
    }

    private function explain(): void
    {
        app(Gateway::class)
            ->prompt('explainer.select', [
                'selection' => 'a phrase',
                'surrounding_chunk' => 'a paragraph',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->feature('lesson.explainer')
            ->generate();
    }
}
