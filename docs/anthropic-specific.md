# Anthropic-specific usage — portability ledger

*Required by spec 2.5. Design decision D1 chose Anthropic-direct with a clean provider abstraction, on the condition that every Anthropic-ism is written down here rather than accumulating silently. This file is the record of what a provider migration would cost.*

The rule: **if a change is made because the provider is Anthropic, it gets an entry.** Model IDs are not entries — they live in `config/ai.php` behind the tier indirection (spec 2.1.1) and swap with the rest of the block.

| # | What | Where | Cost to leave |
|---|---|---|---|
| 1 | Prompt-caching breakpoints, and the workaround that gets them onto the wire | `app/Ai/PromptCaching.php` | Low — delete the class and the two config keys |
| 2 | Cache-cost multipliers used to price a call | `config/ai.php` → `prompt_caching.read_multiplier` / `write_multiplier` | Low — numbers only |

---

## 1. Prompt-caching breakpoints, and the workaround for laravel/ai#119

**Status:** live, verified against the real API 2026-08-23. Spike command: `php artisan ai:cache-spike`. Structural regression test: `tests/Feature/Ai/PromptCachingTest.php`.

### Verified result

`php artisan ai:cache-spike --paragraphs=80` against `cheap` (haiku), 21,655 characters of shared prefix:

| | input (uncached) | cache write | cache read | output |
|---|---|---|---|---|
| call 1 — warm | 3 | 4,753 | 0 | 4 |
| call 2 — read | 3 | 11 | **4,742** | 4 |

**These numbers show the system breakpoint is the one doing the work, and that the top-level breakpoint alone would have missed.** The two calls are separate conversations with *different* user messages ("one", "two"), sharing only the system prefix. Call 1 wrote two cacheable prefixes: `[system]` at 4,742 and `[system + "one"]` at 4,753. Call 2's prefix is `[system + "two"]`, which matches the first of those and not the second — hence 4,742 read and only 11 written for its own tail. With top-level `cache_control` alone there would have been a single breakpoint, placed after `"one"`, and call 2 would have read nothing.

That is the cross-conversation case from the design rationale below, measured rather than argued. It is also the case OpenTutor is made of: every lesson in a course is a separate conversation over one shared prefix.

### What each breakpoint is actually worth

The two breakpoints do not split the work evenly, and the second one is gated by the cache TTL:

| | within one lesson | next lesson, < 5 min later | next lesson, tomorrow |
|---|---|---|---|
| `tail` (top-level) alone | full benefit | miss | miss |
| `tail` + `system` (what we send) | same as above | **hit** | miss |

Within a single conversation the `system` breakpoint adds nothing — the top-level one already stores "everything through this turn" and the next turn matches it. The `system` breakpoint earns its place only in the middle column, and a five-minute idle expiry closes that window fast. A student returning the next day pays full price for the prefix no matter what we send.

**Keep it anyway: it is close to free.** Anthropic bills a cached region once regardless of how many breakpoints subdivide it, so adding the `system` breakpoint cost roughly nothing on the measured call (4,753 written with both). The real cost is not tokens but the merge-order fragility described below, which the wire test contains.

If real usage shows students doing back-to-back lessons, `ai.prompt_caching.ttl` accepts `1h`. Anthropic charges 2x to write an hour-long cache against 1.25x for five minutes, so that is a change to make with usage data, not before.

**Minimum cacheable length is higher than the commonly-cited 1,024.** At 40 paragraphs (~2,370 tokens) the prefix did not cache; at 80 (~4,742) it did. laravel/ai's own draft documentation puts the range at 512–4,096 tokens depending on model, and haiku sits at the top of it. The spike's default is 80 paragraphs for that reason. A short prefix fails silently — no error, no warning, just a bill.

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

### Upstream status — this workaround has an expiry date

Issue #119 is **closed**, resolved in favour of the top-level form alone: a maintainer confirmed `['cache_control' => ['type' => 'ephemeral']]` through `providerOptions()` reaches Anthropic and produces cache hits. That answer covers caching within a conversation and stops short of the cross-conversation case, which is the one that matters here.

The maintainers reached the same conclusion. [laravel/ai#850](https://github.com/laravel/ai/issues/850) raises exactly this gap, and **[PR #869](https://github.com/laravel/ai/pull/869) (open, last touched 2026-08-21) is our workaround done properly upstream** — a first-class `prompt_cache` provider option:

```php
return ['prompt_cache' => [PromptCacheTarget::System]];
```

It emits the system prompt in block form with `cache_control`, stamps the last tool definition so the tool list is covered too, supports per-target TTLs, and does the equivalent thing on Bedrock with `cachePoint`. It composes with the top-level form rather than replacing it.

**When #869 merges, delete the `system` half of `PromptCaching` and pass `prompt_cache` instead.** The `tail` half stays as it is — that is the supported top-level form and #869 leaves it untouched. Watch #869; nothing else here changes.

Until then the per-block half is unsupported API surface riding on merge order.

### How this breaks, and how you find out

It fails **silently**. If laravel/ai ever merges provider options before the body instead of after, `system` reverts to a bare string, `cache_control` vanishes, and nothing errors — the calls keep working and the bill roughly triples. `tests/Feature/Ai/PromptCachingTest.php` asserts on the JSON body that actually reaches the HTTP client, so that change fails the build instead of the invoice.

### Also worth knowing

- **Minimum cacheable prefix is model-specific, 512–4,096 tokens**, and haiku sits at the top of that range — see the measured result above. A shorter prefix silently does not cache. This is the first thing to check when `ai:cache-spike` reports a miss, and the thing to check before assuming a small prompt is being cached.
- **Any byte change anywhere in the prefix invalidates everything after it.** Spec 2.3.1 already accepts that a prompt edit invalidates that prompt's cache prefix on deploy. The thing to watch for is accidental invalidation: a timestamp, a UUID, or a non-deterministically ordered document rendered into the prefix. `ai:cache-spike` builds a deliberately deterministic prefix so it can catch that class of bug.
- **Max 4 breakpoints per request.** We use 2.
- **Scripted calls send no system prompt**, so they get the `tail` breakpoint only. The gateway puts a rendered prompt in as the user message; `instructions` is set separately and today only the lesson session sets it. A scripted call therefore shares no prefix with anything, which is correct — each one is a different prompt with different inputs. Interviews are the case worth revisiting when package 4 lands: they are conversations, and their instructions may belong in `system` rather than in the first user message.

### Cost to leave

Delete `app/Ai/PromptCaching.php` and the `prompt_caching` block in `config/ai.php`; `GatewayAgent::providerOptions()` then returns `[]` and everything else is unchanged. No call site, prompt, or schema is involved. The equivalent feature on another provider is a different mechanism (OpenAI caches automatically and needs no opt-in at all), so this is a deletion, not a port.

---

## 2. Cache-cost multipliers

**Status:** live. `config/ai.php` → `prompt_caching.read_multiplier` (0.1) and `write_multiplier` (1.25).

Anthropic prices a cache read at 0.1x the input rate and a 5-minute cache write at 1.25x. `App\Ai\UsageRecorder::cost()` uses those two numbers to price the `usage_events` row required by spec 2.2. Another provider with a different cache-pricing shape — or none — changes these two numbers, or drops them to `0.1`/`1.0` where reads are free and writes are not billed separately.

Cost to leave: two config values.
