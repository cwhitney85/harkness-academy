<?php

namespace Tests;

use App\Ai\GatewayAgent;
use App\Ai\StructuredGatewayAgent;
use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Script the model for every kind of gateway call.
     *
     * laravel/ai keys its fakes on the exact agent class, and the gateway
     * builds two: GatewayAgent for prose and streams, StructuredGatewayAgent
     * when a prompt declares a json output (spec 2.3.1). Faking only the first
     * lets structured calls -- syllabus, checkpoints, quizzes -- reach the
     * network for real from a test suite.
     *
     * Stray prompts throw rather than returning invented data, so a call this
     * test did not plan for fails loudly instead of quietly hitting a provider.
     *
     * @param  Closure(string): (array<string, mixed>|string|object)  $responder
     */
    protected function fakeModel(Closure $responder): void
    {
        foreach ([GatewayAgent::class, StructuredGatewayAgent::class] as $agent) {
            $agent::fake($responder)->preventStrayPrompts();
        }
    }
}
