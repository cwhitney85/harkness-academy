# 14-routing-and-usage — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [14.1](../specs/14-routing-and-usage.md) (14.1.1–14.1.6), plus [14.3.1](../specs/14-routing-and-usage.md) pulled forward because 14.1.5's done-condition reads its columns. Then [14.2](../specs/14-routing-and-usage.md) (14.2.1–14.2.4), session of 2026-09-19. Then 14.3.2–14.3.4, later the same day.

## Items

- [x] **14.1.1** — `ai_models` seeded with today's three models; dev db gains three rows and nothing else changes.
- [x] **14.1.2** — `ai_tiers` (primary, ordered backups, timeout); `Tier::model()/pricing()/timeout()` read it; changing a row changes the lesson fixture's model.
- [x] **14.1.3** — resolution cached, cleared on write; zero catalog queries across ten calls.
- [x] **14.1.4** — `NoModelIdsInCodeTest`; no `tiers` key in `config/ai.php`.
- [x] **14.1.5** — primary + backups handed to the SDK; row names the serving model, outcome `failed_over`.
- [x] **14.1.6** — a `tier: session` prompt runs on the model its lesson started on.
- [x] **14.3.1** — the fuller usage row, additive.
- [x] **14.2.1** — `App\Ai\Providers\AnthropicProvider` owns markers, token counting, pricing; `PromptCaching` folded into it.
- [x] **14.2.2** — `AiModel` refuses `active` on a provider with no class.
- [x] **14.2.3** — Anthropic token-counting test against a response with cached tokens.
- [x] **14.2.4** — equal tokens on two models cost differently; a price edit leaves past rows alone.
- [x] **14.3.2** — old rows backfilled: `failed` where input and output tokens are both zero, `ok` otherwise.
- [x] **14.3.3** — `PendingCall::about(Course|Lesson)`; every call site but `subtopic.check` uses it.
- [x] **14.3.4** — `plan` copied onto the row; demo spend by plan survives the sweep.

## Questions and answers (Colin, 2026-09-19)

1. **Backups on the same provider.** laravel/ai's failover list is keyed by provider (`['anthropic' => model]`), so it cannot fall back from one Anthropic model to another — and 14.6.1 keeps Anthropic the only provider. **Answer: use the SDK's failover as-is.** Same-provider backups do nothing until a second provider class exists. **Colin: adding same-provider fallback is a priority for the next unit.** → ledger note on D52 at landing.
2. **Where a lesson remembers its model.** **Answer: a column on `lessons`** (`ai_model_id`), stamped the first time the lesson's session calls a model. → D10.5 gets a dated note.
3. **14.1.5 needs 14.3.1's columns.** **Answer: bring 14.3.1 in whole.** 14.3.2–14.3.4 stay later.

**Triaged already (2026-09-19):** D52 and D10.5 notes, spec 14.1.2/14.1.5/acceptance riders, system.md, lessons T16 and T17. Delete this file when package 14 lands.

## Log

- `config/cache.php` has `serializable_classes => false`, so the catalog is cached as plain arrays, not Eloquent models.
- Dev cache store is `database`; `Cache::memo()` keeps repeat reads in-process so ten calls make zero queries of any kind.
- `Tier::pricing()` is removed: the recorder prices every row from the serving model's catalog row. That is most of 14.2.4 already; what's left is moving the pricing into the Anthropic class (14.2.1).
- 14.3.1's columns exist. The recorder fills `ai_model_id`, `outcome` and `reasoning_tokens`. `course_id`, `lesson_id` and `plan` wait for 14.3.3/14.3.4, and the two version ids wait for 15. Old rows have null `outcome` until 14.3.2 backfills it.
- Dev db: backed up to `storage/backups/opentutor-2026-09-19-133951.sql`, then `migrate` (additive). 3 models, 3 tiers; all 261 usage rows and 49 lessons survived.
- The full suite took 235s on one run, against 80s before. It was slow, not hung; the likely cause is contention on the local Postgres.
- The prompt-caching TTL is still configurable (`1h` is allowed), but the catalog's cache-write price assumes `5m` (1.25x). v1's multiplier had the same assumption. 14.2 should handle it.
- 14.2: the provider map is `config('ai.provider_classes')`, not a hard-coded list, so a test can stand a class in for `openai` (14.1.5's failover tests need an active second-provider backup, which 14.2.2 now refuses without a class). The next unit adds OpenRouter as one line there.
- 14.2.1 byte-identity: request bodies captured through `GatewayAgent` before and after the move, for five caching configs (default, empty instructions, caching off, `system` only, `1h`), were identical. The 2.4 wire tests' assertions are unchanged; only the class name in the direct-call tests changed.
- 14.2.1: `GatewayAgent::providerOptions()` for a provider with no class now throws instead of returning `[]`, so a classless model fails before a request is sent.
- 14.2.1 closes the TTL item above: under `ttl: 1h`, `AnthropicProvider::cost()` prices a cache write at 2x input instead of the catalog's five-minute price. `docs/anthropic-specific.md` #1 says so.
- 14.2.3's fixture is not a captured body: it is call 2 of the 2026-08-23 cache spike's measured usage (3 / 11 / 4,742 / 4) inside the documented Messages response shape. A live `ai:cache-spike` could replace it with a real capture.
- Open for later: `Tier::failover()` does not check `active`, so an inactive backup would still be tried. 14.2.2 guards activation, not use.
- `docs/anthropic-specific.md` #2 still names the retired multipliers and `UsageRecorder::cost()`; its retirement is 14.5.1.
- 14.3.2 on the dev db (backup `storage/backups/opentutor-2026-09-19-190039.sql`): 6 rows had zero input and output tokens before; after the migration 6 are `failed`, 257 `ok`, none null.
- 14.3.3: `about()` also sets the user, from the course's student, so the call sites that use it dropped `for()`. `for()` now takes only a `User`, because the plan has to come from somewhere. It stays in two places: `subtopic.check`, which runs before any course exists, and the refresher, since a module refresher has no course and passes `about(null)`.
- 14.3.3: the demo-sweep test broke with a foreign-key violation on `usage_events.course_id` once rows carried a course. The cause is lessons T17: the check fails only when the usage row was written in the same transaction as the delete. That is true of every `RefreshDatabase` test and never of the nightly sweep. The fix is migration `…000004`, which checks `course_id` and `lesson_id` at commit instead. Checked on a scratch copy of the test schema before and after.
- Not done: rows from before 14.3.4 have null `plan`. The spec asks for no backfill. It could be copied from `users` for rows whose user still exists.
