# Anthropic-specific usage — portability ledger

*Required by spec 2.5. Design decision D1 chose Anthropic-direct with a clean provider abstraction, on the condition that every Anthropic-ism is written down here rather than accumulating silently. This file is the record of what a provider migration would cost.*

The rule: **if a change is made because the provider is Anthropic, it gets an entry.** Model IDs are not entries — they live in `config/ai.php` behind the tier indirection (spec 2.1.1) and swap with the rest of the block.

| # | What | Where | Cost to leave |
|---|---|---|---|
| 1 | Prompt-caching breakpoints, and the workaround that gets them onto the wire | `app/Ai/PromptCaching.php` | Low — delete the class and the two config keys |
| 2 | Cache-cost multipliers used to price a call | `config/ai.php` → `prompt_caching.read_multiplier` / `write_multiplier` | Low — numbers only |

---

## 1. Prompt-caching breakpoints, and the workaround for laravel/ai#119

**Status:** live. Spike command: `php artisan ai:cache-spike`. Structural regression test: `tests/Feature/Ai/PromptCachingTest.php`.

### What Anthropic needs

Anthropic does not cache by default — unlike OpenAI, it is opt-in. There are two forms:

- **Per-block** — `cache_control: {type: "ephemeral"}` on a specific content block. The breakpoint sits exactly where you put it.
- **Top-level** — `cache_control` as a request field, which auto-applies the breakpoint to the *last* cacheable block (system + tools by default).

OpenTutor emits **both**, for reasons that are specific to how a course is shaped:

- The **system breakpoint** covers spec 2.3.3's stable prefix, `[persona | criteria | wk | guidance]`. That prefix is per-course, not per-conversation: every lesson in a course shares it. With only the top-level form, the breakpoint lands after the newest user message, so the *first* call of every new lesson re-writes the whole prefix at 1.25x. The explicit system breakpoint is what makes lesson two cheap.
- The **tail breakpoint** covers the growing lesson transcript within one conversation, which the system breakpoint cannot reach.

### What laravel/ai does

`Laravel\Ai\Gateway\Anthropic\Concerns\BuildsTextRequests::buildTextRequestBody()` writes the system prompt as a bare string:

```php
if (filled($instructions)) {
    $body['system'] = $instructions;
}
```

A string has nowhere to hang a `cache_control`, so the per-block form is unreachable through the normal agent interface. That is [laravel/ai#119](https://github.com/laravel/ai/issues/119).

### The workaround

The same method ends with:

```php
return array_merge($body, $providerOptions);
```

Provider options are merged **last**, so they can replace any key the builder set — including `system`. `App\Ai\PromptCaching` returns provider options that carry the system prompt back as a content-block array with an explicit breakpoint, plus the top-level field:

```php
[
    'system' => [['type' => 'text', 'text' => $instructions, 'cache_control' => ['type' => 'ephemeral']]],
    'cache_control' => ['type' => 'ephemeral'],
]
```

Options are keyed by driver: a non-Anthropic driver gets `[]` and the SDK's own behaviour, unchanged.

Issue #119 is **closed**, resolved in favour of the top-level form alone — a maintainer confirmed `['cache_control' => ['type' => 'ephemeral']]` through `providerOptions()` reaches Anthropic and produces cache hits. The per-block half of what we send is still unsupported API surface, riding on merge order.

### How this breaks, and how you find out

It fails **silently**. If laravel/ai ever merges provider options before the body instead of after, `system` reverts to a bare string, `cache_control` vanishes, and nothing errors — the calls keep working and the bill roughly triples. `tests/Feature/Ai/PromptCachingTest.php` asserts on the JSON body that actually reaches the HTTP client, so that change fails the build instead of the invoice.

### Also worth knowing

- **Minimum cacheable prefix** is roughly 1024 tokens (2048 on some smaller models). A shorter prefix silently does not cache — this is the first thing to check when `ai:cache-spike` reports a miss.
- **Any byte change anywhere in the prefix invalidates everything after it.** Spec 2.3.1 already accepts that a prompt edit invalidates that prompt's cache prefix on deploy. The thing to watch for is accidental invalidation: a timestamp, a UUID, or a non-deterministically ordered document rendered into the prefix. `ai:cache-spike` builds a deliberately deterministic prefix so it can catch that class of bug.
- **Max 4 breakpoints per request.** We use 2.

### Cost to leave

Delete `app/Ai/PromptCaching.php` and the `prompt_caching` block in `config/ai.php`; `GatewayAgent::providerOptions()` then returns `[]` and everything else is unchanged. No call site, prompt, or schema is involved. The equivalent feature on another provider is a different mechanism (OpenAI caches automatically and needs no opt-in at all), so this is a deletion, not a port.

---

## 2. Cache-cost multipliers

**Status:** live. `config/ai.php` → `prompt_caching.read_multiplier` (0.1) and `write_multiplier` (1.25).

Anthropic prices a cache read at 0.1x the input rate and a 5-minute cache write at 1.25x. `App\Ai\UsageRecorder::cost()` uses those two numbers to price the `usage_events` row required by spec 2.2. Another provider with a different cache-pricing shape — or none — changes these two numbers, or drops them to `0.1`/`1.0` where reads are free and writes are not billed separately.

Cost to leave: two config values.
