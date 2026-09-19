<?php

namespace App\Ai\Providers;

use LogicException;

/**
 * The provider classes this app has, keyed by laravel/ai driver key.
 */
final class Providers
{
    public static function has(string $provider): bool
    {
        return isset(config('ai.provider_classes')[$provider]);
    }

    /**
     * @throws LogicException if no class speaks for the provider -- a model
     *                        that reached a call without one bypassed 14.2.2.
     */
    public static function for(string $provider): AiProvider
    {
        $class = config('ai.provider_classes')[$provider]
            ?? throw new LogicException("No provider class for [{$provider}]; a model on it cannot be called (spec 14.2.2).");

        return app($class);
    }
}
