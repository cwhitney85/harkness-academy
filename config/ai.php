<?php

/*
|--------------------------------------------------------------------------
| AI Configuration (spec 2.1.1)
|--------------------------------------------------------------------------
|
| This file is merged over the laravel/ai package defaults, so only the keys
| OpenTutor overrides appear here. The package's `providers` block already
| reads ANTHROPIC_API_KEY, so it is inherited untouched -- and inheriting it
| keeps all sixteen providers configured, which is what makes switching one a
| change of env vars rather than a change of code.
|
| CAUTION: mergeConfigFrom is a SHALLOW array_merge. A top-level key defined
| here REPLACES the package's key of the same name outright. Adding a partial
| `providers` block to tweak one Anthropic setting would silently delete the
| other fifteen providers. If that day comes, publish the package config in
| full (`php artisan vendor:publish --tag=ai-config`) rather than adding a
| partial block here.
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
    | `timeout` is how long a call at this tier may take, in seconds. It is a
    | property of the tier for the same reason the model is: a bigger model
    | asked for a whole syllabus in one structured reply takes minutes, and
    | laravel/ai's own default is 60 seconds for every call it makes. Under
    | that default no `top` call could ever finish -- the client hung up at
    | exactly 60s, Anthropic billed the generation it had already started, and
    | the student was told to ask again (found on the first live run,
    | 2026-09-02). Streaming calls were unaffected: the first token arrives
    | long before the minute is up.
    |
    */

    'tiers' => [

        'cheap' => [
            'model' => 'claude-haiku-4-5',
            'pricing' => ['input' => 1.00, 'output' => 5.00],
            'timeout' => 60,
        ],

        'mid' => [
            'model' => 'claude-sonnet-5',
            'pricing' => ['input' => 3.00, 'output' => 15.00],
            'timeout' => 120,
        ],

        'top' => [
            'model' => 'claude-opus-5',
            'pricing' => ['input' => 5.00, 'output' => 25.00],
            'timeout' => 300,
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
