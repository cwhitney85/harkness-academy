<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * The laravel/ai agent the gateway drives (spec 2.1).
 *
 * laravel/ai's own AnonymousAgent cannot carry provider options, which is what
 * the 2.4 caching workaround needs, so the gateway builds this instead. It is
 * the single place Harkness Academy hands an agent to a provider; nothing
 * else in the app constructs one.
 */
class GatewayAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  iterable<mixed>  $agentMessages
     * @param  iterable<mixed>  $agentTools
     */
    public function __construct(
        protected readonly string $agentInstructions,
        protected readonly iterable $agentMessages = [],
        protected readonly iterable $agentTools = [],
    ) {}

    public function instructions(): string
    {
        return $this->agentInstructions;
    }

    /**
     * @return iterable<mixed>
     */
    public function messages(): iterable
    {
        return $this->agentMessages;
    }

    /**
     * @return iterable<mixed>
     */
    public function tools(): iterable
    {
        return $this->agentTools;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return PromptCaching::providerOptions(
            $provider instanceof Lab ? $provider->value : $provider,
            $this->agentInstructions,
        );
    }
}
