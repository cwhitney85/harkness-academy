<?php

/*
|--------------------------------------------------------------------------
| AI Configuration (spec 2.1.1)
|--------------------------------------------------------------------------
|
| This file is merged over the laravel/ai package defaults, so only the keys
| OpenTutor overrides appear here. The package's `providers` block already
| reads ANTHROPIC_API_KEY, so it is inherited untouched.
|
| THIS IS THE ONLY FILE IN THE REPOSITORY THAT MAY NAME A MODEL. Call sites
| name a capability tier (cheap / mid / top) through the AI gateway. Both
| plan-based routing and provider migration are edits to the `tiers` block
| alone -- see tests/Unit/Ai/NoModelIdsOutsideConfigTest.php, which fails the
| build if a model ID leaks anywhere else.
|
*/

return [

    'default' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Capability Tiers
    |--------------------------------------------------------------------------
    |
    | Prices are US dollars per million tokens, taken from Anthropic's list
    | rates, and are what usage_events (spec 2.2) costs a call at. Anthropic
    | bills a cache read at 0.1x the input rate and a 5-minute cache write at
    | 1.25x; those multipliers live in `caching` below.
    |
    */

    'tiers' => [

        'cheap' => [
            'model' => 'claude-haiku-4-5',
            'pricing' => ['input' => 1.00, 'output' => 5.00],
        ],

        'mid' => [
            'model' => 'claude-sonnet-5',
            'pricing' => ['input' => 3.00, 'output' => 15.00],
        ],

        'top' => [
            'model' => 'claude-opus-5',
            'pricing' => ['input' => 5.00, 'output' => 25.00],
        ],

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

        // Cost multipliers applied to a tier's input rate.
        'read_multiplier' => 0.1,
        'write_multiplier' => 1.25,

    ],

];
