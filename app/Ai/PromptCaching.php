<?php

namespace App\Ai;

/**
 * The workaround for laravel/ai#119 (spec 2.4). See docs/anthropic-specific.md
 * entry #1 for why this exists and what to delete when it stops being needed.
 *
 * laravel/ai builds the Anthropic body with `$body['system'] = $instructions`
 * -- a bare string, which has nowhere to hang a `cache_control`. Its last act
 * is `array_merge($body, $providerOptions)`, so provider options win. We use
 * that to replace `system` with a content-block array carrying an explicit
 * breakpoint, and to set Anthropic's top-level automatic breakpoint as well:
 *
 *   - `system` breakpoint  -- the course's stable prefix (spec 2.3.3's
 *     [persona | criteria | wk | guidance]) stays warm across conversations,
 *     so the second lesson in a course does not re-write it.
 *   - `tail` breakpoint    -- Anthropic's top-level automatic form, which
 *     lands on the last cacheable block, so a growing lesson transcript keeps
 *     hitting cache within one conversation.
 *
 * Provider-neutral by construction: options are keyed by driver, and a
 * non-Anthropic driver gets an empty array.
 */
final class PromptCaching
{
    /**
     * Provider options that place the configured cache breakpoints.
     *
     * @return array<string, mixed>
     */
    public static function providerOptions(string $driver, string $instructions): array
    {
        if ($driver !== 'anthropic' || ! config('ai.prompt_caching.enabled')) {
            return [];
        }

        /** @var list<string> $breakpoints */
        $breakpoints = config('ai.prompt_caching.breakpoints', []);

        $options = [];

        if (in_array('system', $breakpoints, true) && $instructions !== '') {
            $options['system'] = [[
                'type' => 'text',
                'text' => $instructions,
                'cache_control' => self::cacheControl(),
            ]];
        }

        if (in_array('tail', $breakpoints, true)) {
            $options['cache_control'] = self::cacheControl();
        }

        return $options;
    }

    /**
     * @return array{type: string, ttl?: string}
     */
    public static function cacheControl(): array
    {
        $control = ['type' => 'ephemeral'];

        $ttl = config('ai.prompt_caching.ttl');

        // Anthropic's default ephemeral TTL is 5m; sending it explicitly is
        // redundant and only widens the surface that can invalidate a prefix.
        if (is_string($ttl) && $ttl !== '' && $ttl !== '5m') {
            $control['ttl'] = $ttl;
        }

        return $control;
    }
}
