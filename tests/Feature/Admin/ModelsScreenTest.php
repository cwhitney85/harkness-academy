<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Ai\Catalog;
use App\Ai\Tier;
use App\Models\AdminAction;
use App\Models\AiModel;
use App\Models\AiTier;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 14.4 -- Admin → Models lists the catalog and the tiers over it with
 * seven days of calls and cost, and is where a model's prices are edited.
 *
 * The fixture is the seeded catalog itself (14.1.1): three Anthropic models
 * and the three tiers pointing at them.
 */
class ModelsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_screen_renders_the_catalog_the_tiers_and_seven_days_of_usage(): void
    {
        $admin = User::factory()->admin()->create();
        $mid = AiModel::query()->where('id', Tier::Mid->primary()['id'])->sole();

        $this->served($mid, cost: 0.25);
        $this->served($mid, cost: 0.75);
        $this->served($mid, cost: 9.00, ago: 8);

        $this->actingAs($admin)->get('/admin/models')->assertInertia(fn (Assert $page) => $page
            ->component('admin/models')
            ->where('days', 7)
            ->has('models', 3)
            ->where('tiers', [
                ['name' => 'cheap', 'model' => 'Claude Haiku 4.5', 'backups' => [], 'timeout' => 60],
                ['name' => 'mid', 'model' => 'Claude Sonnet 5', 'backups' => [], 'timeout' => 120],
                ['name' => 'top', 'model' => 'Claude Opus 5', 'backups' => [], 'timeout' => 300],
            ])
            ->where('models.1.name', 'Claude Sonnet 5')
            ->where('models.1.provider', 'anthropic')
            ->where('models.1.active', true)
            // A whole price arrives as an int: json_encode(3.0) is `3`.
            ->where('models.1.prices.input_per_mtok', 3)
            ->where('models.1.prices.cache_write_per_mtok', 3.75)
            // The eight-day-old call is outside the window.
            ->where('models.1.calls', 2)
            ->where('models.1.cost', 1)
            ->where('models.0.calls', 0)
            ->where('models.0.cost', 0));
    }

    #[Test]
    public function a_tiers_backups_are_named(): void
    {
        $admin = User::factory()->admin()->create();
        $top = Tier::Top->primary()['id'];

        AiTier::query()->where('name', 'mid')->sole()->update(['backup_model_ids' => [$top]]);

        $this->actingAs($admin)->get('/admin/models')->assertInertia(fn (Assert $page) => $page
            ->where('tiers.1.backups', ['Claude Opus 5']));
    }

    #[Test]
    public function editing_prices_is_logged_and_reaches_later_calls_only(): void
    {
        $admin = User::factory()->admin()->create();
        $model = AiModel::query()->where('id', Tier::Cheap->primary()['id'])->sole();

        $priced = $this->served($model, cost: 0.5);

        $this->actingAs($admin)
            ->put("/admin/models/{$model->id}", [
                'input_per_mtok' => 2,
                'output_per_mtok' => 10,
                'cache_read_per_mtok' => 0.2,
                'cache_write_per_mtok' => 2.5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2.0000', $model->fresh()->input_per_mtok);
        $this->assertSame(2.0, Catalog::model($model->id)['input'], 'The catalog was not cleared by the price edit.');
        $this->assertSame('0.50000000', $priced->fresh()->cost_usd, 'A past call was repriced.');

        $logged = AdminAction::query()->sole();

        $this->assertSame(AdminActionType::AiModelRepriced, $logged->action);
        $this->assertSame($model->id, $logged->subject_id);
        $this->assertSame('1.0000', $logged->payload['was']['input_per_mtok']);
        $this->assertSame('2.0000', $logged->payload['now']['input_per_mtok']);
    }

    #[Test]
    public function a_price_must_be_a_non_negative_number_of_at_most_four_decimals(): void
    {
        $admin = User::factory()->admin()->create();
        $model = AiModel::query()->where('id', Tier::Cheap->primary()['id'])->sole();

        $this->actingAs($admin)
            ->put("/admin/models/{$model->id}", [
                'input_per_mtok' => -1,
                'output_per_mtok' => 'free',
                'cache_read_per_mtok' => 0.123456,
                'cache_write_per_mtok' => 1,
            ])
            ->assertSessionHasErrors(['input_per_mtok', 'output_per_mtok', 'cache_read_per_mtok']);

        $this->assertSame('1.0000', $model->fresh()->input_per_mtok);
        $this->assertSame(0, AdminAction::query()->count());
    }

    private function served(AiModel $model, float $cost, int $ago = 0): UsageEvent
    {
        return UsageEvent::query()->create([
            'feature' => 'lesson',
            'prompt_key' => 'lesson.section',
            'provider' => $model->provider,
            'tier' => 'mid',
            'model' => $model->model,
            'ai_model_id' => $model->id,
            'outcome' => 'ok',
            'latency_ms' => 100,
            'cost_usd' => $cost,
            'created_at' => now()->subDays($ago),
        ]);
    }
}
