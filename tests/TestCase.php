<?php

namespace Tests;

use App\Ai\GatewayAgent;
use App\Ai\StructuredGatewayAgent;
use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * No test in this suite renders a built asset, so none of them waits on
     * one (spec 1.4).
     *
     * Without this, every Inertia screen test resolves `@vite` against
     * `public/build/manifest.json` and dies with a 500 the moment that
     * manifest is older than the page under test -- which it is for the whole
     * of any session that adds a screen. The failure names a Vite manifest and
     * looks nothing like the stale build it is, and `composer test` gives no
     * hint that `npm run build` is the fix.
     *
     * Pre:  a test asserts status codes, props or rendered text.
     * Post: the asset tags are empty strings and nothing reads the build
     *       directory. A test that ever needs the real thing calls
     *       `$this->withVite()` and says why.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * The props of an Inertia page, as data.
     *
     * `assertInertia` is the right tool for asserting the shape of a screen;
     * this is for the tests that assert the shape of the *state* a screen is
     * handed -- package 5's resume contract, which was written against a JSON
     * route before 8.8 turned that URL into a page.
     *
     * @return array<string, mixed>
     */
    protected function inertiaProps(TestResponse $response): array
    {
        /** @var array<string, mixed> $props */
        $props = AssertableInertia::fromTestResponse($response)->toArray()['props'];

        return $props;
    }

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
