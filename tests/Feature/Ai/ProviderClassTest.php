<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\Providers\AnthropicProvider;
use App\Ai\Tier;
use App\Models\AiModel;
use App\Models\AiTier;
use App\Models\UsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 14.2 -- one class per provider owns its cache markers, its token
 * counting and its pricing.
 */
class ProviderClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function anthropic_counts_a_recorded_response_with_cached_tokens_as_uncached_input(): void
    {
        // The usage block is call 2 of `ai:cache-spike` (2026-08-23,
        // docs/anthropic-specific.md #1): 4,742 tokens read from cache.
        Http::fake(['*/messages' => Http::response(
            json_decode((string) file_get_contents(base_path('tests/fixtures/providers/anthropic-cached.json')), true),
        )]);

        $this->explain();

        $event = UsageEvent::sole();

        $this->assertSame(3, $event->input_tokens);
        $this->assertSame(4742, $event->cached_input_tokens);
        $this->assertSame(11, $event->cache_write_tokens);
        $this->assertSame(4, $event->output_tokens);

        // Haiku: 1.00 input, 0.10 cache read, 1.25 cache write, 5.00 output per million.
        $this->assertSame('0.00051095', (string) $event->cost_usd);
    }

    #[Test]
    public function anthropic_prices_each_kind_of_token_at_the_models_own_rate(): void
    {
        $anthropic = new AnthropicProvider;
        $model = ['id' => 0, 'provider' => 'anthropic', 'model' => 'm', 'input' => 3.0, 'output' => 15.0, 'cache_read' => 0.3, 'cache_write' => 3.75];

        $this->assertSame(3.0, $anthropic->cost($model, new Usage(promptTokens: 1_000_000)));
        $this->assertSame(0.3, $anthropic->cost($model, new Usage(cacheReadInputTokens: 1_000_000)));
        $this->assertSame(3.75, $anthropic->cost($model, new Usage(cacheWriteInputTokens: 1_000_000)));
        $this->assertSame(15.0, $anthropic->cost($model, new Usage(completionTokens: 1_000_000)));

        config()->set('ai.prompt_caching.ttl', '1h');

        $this->assertSame(6.0, $anthropic->cost($model, new Usage(cacheWriteInputTokens: 1_000_000)), 'An hour-long cache write is 2x input.');
    }

    #[Test]
    public function a_model_cannot_be_active_while_its_provider_has_no_class(): void
    {
        $model = AiModel::create([
            'provider' => 'openrouter', 'model' => 'some/model', 'display_name' => 'Some model', 'active' => false,
            'input_per_mtok' => 1, 'output_per_mtok' => 2, 'cache_read_per_mtok' => 0.1, 'cache_write_per_mtok' => 1,
        ]);

        try {
            $model->update(['active' => true]);
            $this->fail('Activating an openrouter model should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('active', $e->errors());
        }

        $this->assertFalse($model->fresh()->active);

        $this->expectException(ValidationException::class);

        AiModel::create([
            'provider' => 'openrouter', 'model' => 'other/model', 'display_name' => 'Other model',
            'input_per_mtok' => 1, 'output_per_mtok' => 2, 'cache_read_per_mtok' => 0.1, 'cache_write_per_mtok' => 1,
        ]);
    }

    #[Test]
    public function a_call_to_a_provider_with_no_class_is_refused_before_it_is_sent(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No provider class for [openrouter]');

        (new GatewayAgent('You are a tutor.'))->providerOptions('openrouter');
    }

    #[Test]
    public function equal_tokens_cost_what_the_serving_model_charges_and_a_price_edit_leaves_past_rows_alone(): void
    {
        $usage = new Usage(promptTokens: 1_000, completionTokens: 500, cacheWriteInputTokens: 2_000, cacheReadInputTokens: 4_000);

        GatewayAgent::fake(fn (): TextResponse => new TextResponse('Explained.', $usage, new Meta('anthropic', 'any')));

        $this->explain();

        AiTier::query()->where('name', 'cheap')->sole()->update(['ai_model_id' => Tier::Top->primary()['id']]);

        $this->explain();

        [$onHaiku, $onOpus] = UsageEvent::query()->orderBy('id')->get()->all();

        $this->assertSame([1_000, 1_000], [$onHaiku->input_tokens, $onOpus->input_tokens]);
        $this->assertSame('0.00640000', (string) $onHaiku->cost_usd);
        $this->assertSame('0.03200000', (string) $onOpus->cost_usd);

        AiModel::query()->findOrFail($onOpus->ai_model_id)->update(['input_per_mtok' => 50]);

        $this->assertSame('0.03200000', (string) $onOpus->fresh()->cost_usd, 'A price edit does not reprice a past call.');

        $this->explain();

        $this->assertSame('0.07700000', (string) UsageEvent::query()->latest('id')->first()->cost_usd, 'The next call pays the new price.');
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
