<?php

namespace Tests\Unit\Ai;

use App\Ai\Tier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.1.1 -- the tier to model map.
 */
class TierTest extends TestCase
{
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
    public function changing_the_config_changes_the_model_a_tier_resolves_to(): void
    {
        config()->set('ai.tiers.mid.model', 'a-different-model');

        $this->assertSame('a-different-model', Tier::Mid->model());
        $this->assertNotSame('a-different-model', Tier::Top->model());
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
