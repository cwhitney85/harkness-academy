<?php

namespace Tests\Feature\Ai;

use App\Ai\GatewayAgent;
use App\Ai\PromptCaching;
use App\Ai\Tier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.4 -- the prompt-caching spike, structural half.
 *
 * The live half is `php artisan ai:cache-spike`, which is the only thing that
 * can prove `cache_read_input_tokens > 0`. What this proves without spending
 * money is the part that silently rots: that the laravel/ai#119 workaround
 * still reaches the wire. laravel/ai builds `system` as a bare string and then
 * merges provider options over the body -- if that merge order ever changes,
 * our cache_control disappears with no error, no exception, and a bill that
 * quietly triples. These assertions are that tripwire.
 */
class PromptCachingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function the_system_prompt_reaches_anthropic_as_a_content_block_carrying_cache_control(): void
    {
        $body = $this->captureRequestBody('You are a tutor.');

        $this->assertIsArray($body['system'], 'system must be a content-block array, not a bare string, or cache_control has nowhere to live.');
        $this->assertSame('text', $body['system'][0]['type']);
        $this->assertSame('You are a tutor.', $body['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);
    }

    #[Test]
    public function the_top_level_breakpoint_reaches_anthropic_for_the_conversation_tail(): void
    {
        $body = $this->captureRequestBody('You are a tutor.');

        $this->assertSame(['type' => 'ephemeral'], $body['cache_control']);
    }

    #[Test]
    public function disabling_prompt_caching_sends_neither_breakpoint(): void
    {
        config()->set('ai.prompt_caching.enabled', false);

        $body = $this->captureRequestBody('You are a tutor.');

        $this->assertSame('You are a tutor.', $body['system']);
        $this->assertArrayNotHasKey('cache_control', $body);
    }

    #[Test]
    public function a_single_breakpoint_can_be_configured_without_the_other(): void
    {
        config()->set('ai.prompt_caching.breakpoints', ['system']);

        $body = $this->captureRequestBody('You are a tutor.');

        $this->assertIsArray($body['system']);
        $this->assertArrayNotHasKey('cache_control', $body);
    }

    #[Test]
    public function a_non_anthropic_provider_gets_no_anthropic_options(): void
    {
        $this->assertSame([], PromptCaching::providerOptions('openai', 'You are a tutor.'));
        $this->assertSame([], PromptCaching::providerOptions('gemini', 'You are a tutor.'));
    }

    #[Test]
    public function an_empty_system_prompt_gets_no_system_breakpoint(): void
    {
        $options = PromptCaching::providerOptions('anthropic', '');

        $this->assertArrayNotHasKey('system', $options);
        $this->assertSame(['type' => 'ephemeral'], $options['cache_control']);
    }

    #[Test]
    public function a_non_default_ttl_is_sent_and_the_default_one_is_not(): void
    {
        $this->assertSame(['type' => 'ephemeral'], PromptCaching::cacheControl());

        config()->set('ai.prompt_caching.ttl', '1h');

        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], PromptCaching::cacheControl());
    }

    /**
     * Drive one real gateway call into a faked Anthropic endpoint and return
     * the JSON body that laravel/ai actually built.
     *
     * @return array<string, mixed>
     */
    private function captureRequestBody(string $instructions): array
    {
        Http::fake([
            '*/messages' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'model' => Tier::Cheap->model(),
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 2,
                    'cache_creation_input_tokens' => 1024,
                    'cache_read_input_tokens' => 0,
                ],
            ]),
        ]);

        (new GatewayAgent($instructions))->prompt('Hello.', model: Tier::Cheap->model());

        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'No request reached the Anthropic endpoint.');

        /** @var Request $request */
        $request = $recorded[0][0];

        return $request->data();
    }
}
