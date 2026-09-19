<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\PendingCall;
use App\Ai\Tier;
use App\Models\AiModel;
use App\Models\AiTier;
use App\Models\UsageEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 14.1 -- a call names a task, the task a tier, the tier a model in the
 * catalog.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
        config()->set('ai.providers.openai.key', 'test-key');
    }

    #[Test]
    public function the_catalog_is_seeded_with_three_priced_models_and_three_tiers_over_them(): void
    {
        $this->assertSame(3, AiModel::query()->where('provider', 'anthropic')->where('active', true)->count());
        $this->assertSame(['cheap', 'mid', 'top'], AiTier::query()->orderBy('id')->pluck('name')->all());

        foreach ([Tier::Cheap, Tier::Mid, Tier::Top] as $tier) {
            $model = $tier->primary();

            // As config/ai.php charged: a cache read at 0.1x input, a write at 1.25x.
            $this->assertEqualsWithDelta($model['input'] * 0.1, $model['cache_read'], 0.0001, $tier->value);
            $this->assertEqualsWithDelta($model['input'] * 1.25, $model['cache_write'], 0.0001, $tier->value);
        }
    }

    #[Test]
    public function ten_calls_make_no_catalog_queries_and_a_write_clears_the_cache(): void
    {
        GatewayAgent::fake(array_fill(0, 11, 'Explained.'));

        $this->explain()->generate();

        $catalogQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$catalogQueries): void {
            $catalogQueries += preg_match('/\bai_(models|tiers)\b/', $query->sql);
        });

        for ($i = 0; $i < 10; $i++) {
            $this->explain()->generate();
        }

        $this->assertSame(0, $catalogQueries);

        AiTier::query()->where('name', 'cheap')->sole()->update(['ai_model_id' => Tier::Top->primary()['id']]);

        $this->assertSame(Tier::Top->model(), Tier::Cheap->model(), 'A write through the model clears the cache.');
    }

    #[Test]
    public function a_rate_limited_primary_fails_over_to_the_backup_and_the_row_names_the_backup(): void
    {
        $backup = $this->backupForCheap();

        GatewayAgent::fake(function (string $prompt, $attachments, Provider $provider): string {
            if ($provider->name() === 'anthropic') {
                throw RateLimitedException::forProvider('anthropic', 429);
            }

            return 'Explained by the backup.';
        });

        $response = $this->explain()->generate();

        $this->assertSame('Explained by the backup.', $response->text);

        $event = UsageEvent::sole();

        $this->assertSame($backup->id, $event->ai_model_id);
        $this->assertSame('backup-model', $event->model);
        $this->assertSame('openai', $event->provider);
        $this->assertSame('failed_over', $event->outcome);
    }

    #[Test]
    public function a_stream_fails_over_the_same_way(): void
    {
        $backup = $this->backupForCheap();

        GatewayAgent::fake(function (string $prompt, $attachments, Provider $provider): string {
            if ($provider->name() === 'anthropic') {
                throw RateLimitedException::forProvider('anthropic', 429);
            }

            return 'Streamed by the backup.';
        });

        $this->gateway()
            ->prompt('lesson.section', ['section_index' => '1', 'section_title' => 'Greetings', 'section_concepts' => 'greeting-formal'])
            ->feature('lesson.section')
            ->sessionTier(Tier::Cheap)
            ->stream()
            ->each(fn () => null);

        $event = UsageEvent::sole();

        $this->assertSame($backup->id, $event->ai_model_id);
        $this->assertSame('failed_over', $event->outcome);
    }

    #[Test]
    public function a_call_the_primary_serves_is_ok_and_one_that_throws_is_failed(): void
    {
        GatewayAgent::fake(['Explained.']);

        $this->explain()->generate();

        $this->assertSame('ok', UsageEvent::sole()->outcome);
        $this->assertSame(Tier::Cheap->primary()['id'], UsageEvent::sole()->ai_model_id);

        UsageEvent::query()->delete();
        GatewayAgent::fake(fn (): never => throw RateLimitedException::forProvider('anthropic', 429));

        try {
            $this->explain()->generate();
            $this->fail('With no backup, the rate limit should propagate.');
        } catch (RateLimitedException) {
            // expected
        }

        $this->assertSame('failed', UsageEvent::sole()->outcome);
    }

    #[Test]
    public function a_backup_on_the_primarys_own_provider_is_not_reachable_through_the_sdk(): void
    {
        $sameProvider = AiModel::query()->where('model', Tier::Top->model())->sole();

        AiTier::query()->where('name', 'cheap')->sole()->update(['backup_model_ids' => [$sameProvider->id]]);

        // laravel/ai keys failover by provider (ledger D52's 2026-09-19 note).
        $this->assertSame(['anthropic' => Tier::Cheap->model()], array_map(
            fn (array $model): string => $model['model'],
            Tier::Cheap->failover(),
        ));
    }

    private function backupForCheap(): AiModel
    {
        $backup = AiModel::create([
            'provider' => 'openai', 'model' => 'backup-model', 'display_name' => 'Backup',
            'input_per_mtok' => 2, 'output_per_mtok' => 8, 'cache_read_per_mtok' => 0.5, 'cache_write_per_mtok' => 2,
        ]);

        AiTier::query()->where('name', 'cheap')->sole()->update(['backup_model_ids' => [$backup->id]]);

        return $backup;
    }

    private function explain(): PendingCall
    {
        return $this->gateway()
            ->prompt('explainer.select', [
                'selection' => 'a phrase',
                'surrounding_chunk' => 'a paragraph',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->feature('lesson.explainer');
    }

    private function gateway(): Gateway
    {
        return app(Gateway::class);
    }
}
