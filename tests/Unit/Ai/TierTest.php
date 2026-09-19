<?php

namespace Tests\Unit\Ai;

use App\Ai\Tier;
use App\Models\AiModel;
use App\Models\AiTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 14.1.2 -- tiers are rows, and each names a model in the catalog.
 */
class TierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function three_tiers_resolve_to_three_distinct_models(): void
    {
        $models = [
            Tier::Cheap->model(),
            Tier::Mid->model(),
            Tier::Top->model(),
        ];

        $this->assertCount(3, array_unique($models), 'Each tier must resolve to a distinct model.');
        $this->assertNotContains('', $models);
    }

    #[Test]
    public function changing_a_tier_row_changes_the_model_it_resolves_to(): void
    {
        $other = AiModel::create([
            'provider' => 'anthropic', 'model' => 'a-different-model', 'display_name' => 'Different',
            'input_per_mtok' => 1, 'output_per_mtok' => 1, 'cache_read_per_mtok' => 1, 'cache_write_per_mtok' => 1,
        ]);

        $top = Tier::Top->model();

        AiTier::query()->where('name', 'mid')->sole()->update(['ai_model_id' => $other->id]);

        $this->assertSame('a-different-model', Tier::Mid->model());
        $this->assertSame($top, Tier::Top->model());
    }

    /**
     * The bug of 2026-09-02: laravel/ai defaults every call to 60 seconds, and
     * a `top` structured generation cannot finish inside one. The client hung
     * up, Anthropic billed the work it had already started, and the student
     * was told nothing had changed.
     */
    #[Test]
    public function every_billable_tier_states_its_own_timeout(): void
    {
        foreach ([Tier::Cheap, Tier::Mid, Tier::Top] as $tier) {
            $this->assertGreaterThan(0, $tier->timeout(), "Tier [{$tier->value}] must state a timeout.");
        }

        $this->assertGreaterThan(
            60,
            Tier::Top->timeout(),
            'A `top` call takes longer than laravel/ai\'s 60-second default, which is the whole reason this exists.',
        );
    }

    #[Test]
    public function a_tier_with_no_row_is_a_wiring_bug(): void
    {
        AiTier::query()->where('name', 'top')->sole()->delete();

        $this->expectException(InvalidArgumentException::class);

        Tier::Top->timeout();
    }

    #[Test]
    public function non_billable_tiers_do_not_resolve_to_a_model(): void
    {
        $this->assertFalse(Tier::None->isBillable());
        $this->assertFalse(Tier::Session->isBillable());

        $this->expectException(InvalidArgumentException::class);

        Tier::None->model();
    }

    #[Test]
    public function a_session_tier_resolves_against_the_tier_of_its_session(): void
    {
        $this->assertSame(Tier::Top, Tier::Session->resolve(Tier::Top));
        $this->assertSame(Tier::Mid, Tier::Mid->resolve(Tier::Top), 'A concrete tier ignores the session tier.');
    }

    #[Test]
    public function a_session_tier_without_a_session_is_a_caller_bug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tier::Session->resolve(null);
    }
}
