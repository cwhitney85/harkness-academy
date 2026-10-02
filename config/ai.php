<?php

use App\Ai\Providers\AnthropicProvider;

/*
|--------------------------------------------------------------------------
| AI Configuration (spec 2.1.1)
|--------------------------------------------------------------------------
|
| This file is merged over the laravel/ai package defaults, so only the keys
| Harkness Academy overrides appear here. The package's `providers` block
| already reads ANTHROPIC_API_KEY, so it is inherited untouched -- and
| inheriting it keeps all sixteen providers configured, which is what makes
| switching one a change of env vars rather than a change of code.
|
| CAUTION: mergeConfigFrom is a SHALLOW array_merge. A top-level key defined
| here REPLACES the package's key of the same name outright. Adding a partial
| `providers` block to tweak one Anthropic setting would silently delete the
| other fifteen providers. If that day comes, publish the package config in
| full (`php artisan vendor:publish --tag=ai-config`) rather than adding a
| partial block here.
|
| Models, tiers and prices are not here: they are rows in `ai_models` and
| `ai_tiers` (spec 14.1, ledger D52), read through App\Ai\Catalog. Call sites
| name a capability tier (cheap / mid / top) through the AI gateway, and
| tests/Unit/Ai/NoModelIdsInCodeTest.php fails the build if a model ID
| appears in code.
|
*/

return [

    'default' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Provider classes (spec 14.2, ledger D53)
    |--------------------------------------------------------------------------
    |
    | laravel/ai driver key => the class that owns that provider's cache
    | markers, token counting and pricing. A catalog model on a provider not
    | listed here cannot be active.
    |
    */

    'provider_classes' => [
        'anthropic' => AnthropicProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Caching (spec 2.4)
    |--------------------------------------------------------------------------
    |
    | `breakpoints` controls the workaround for laravel/ai#119 documented in
    | docs/anthropic-specific.md. `system` puts an explicit cache_control on
    | the instructions block so a course's stable prefix survives across
    | conversations; `tail` adds Anthropic's top-level automatic breakpoint so
    | a growing transcript keeps hitting cache within one conversation.
    |
    */

    'prompt_caching' => [

        'enabled' => env('AI_PROMPT_CACHING', true),

        'breakpoints' => ['system', 'tail'],

        'ttl' => '5m',

    ],

];
