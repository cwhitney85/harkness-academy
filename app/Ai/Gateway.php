<?php

namespace App\Ai;

use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use App\Ai\Prompts\PromptDefinition;
use App\Ai\Prompts\PromptRepository;

/**
 * The provider gateway (spec 2.1).
 *
 * Every AI call in OpenTutor starts here. Call sites name a prompt key from
 * the 2.3.2 inventory and a capability tier comes with it; no call site ever
 * names a model, and no call site talks to laravel/ai directly.
 */
final class Gateway
{
    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly UsageRecorder $recorder,
    ) {}

    /**
     * Begin a call against an inventory prompt.
     *
     * @param  array<string, string|\Stringable>  $vars
     *
     * @throws UnknownPromptKey
     */
    public function prompt(string $key, array $vars = []): PendingCall
    {
        return new PendingCall($this->prompts->get($key), $this->recorder, $vars);
    }

    /**
     * Render an inventory prompt without calling anything.
     *
     * This is how the fragment and system prompts of spec 2.3.3 are assembled.
     *
     * @param  array<string, string|\Stringable>  $vars
     */
    public function render(string $key, array $vars = []): string
    {
        return $this->prompts->get($key)->render($vars);
    }

    public function definition(string $key): PromptDefinition
    {
        return $this->prompts->get($key);
    }

    public function prompts(): PromptRepository
    {
        return $this->prompts;
    }
}
