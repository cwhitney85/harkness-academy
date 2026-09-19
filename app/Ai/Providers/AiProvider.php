<?php

namespace App\Ai\Providers;

use App\Ai\Catalog;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Everything about a call that depends on which provider serves it (spec
 * 14.2.1, ledger D53): where its cache markers go, what its token counts
 * mean, and how its prices apply to them. One class per provider, named in
 * `config('ai.provider_classes')`; a model whose provider has none cannot be
 * active (14.2.2).
 *
 * @phpstan-import-type ModelRow from Catalog
 */
interface AiProvider
{
    /**
     * Options merged over the request body laravel/ai builds for this provider.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(string $instructions): array;

    /**
     * The usage as this app counts it: `promptTokens` is uncached input only.
     */
    public function tokens(Usage $reported): Usage;

    /**
     * US dollars for a call, from the serving model's prices and `tokens()`' counts.
     *
     * @param  ModelRow  $model
     */
    public function cost(array $model, Usage $usage): float;
}
