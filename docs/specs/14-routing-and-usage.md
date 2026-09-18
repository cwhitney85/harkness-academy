# Spec 14 — Routing and Usage

*Provides: the model catalog, tiers as rows, a class per provider, per-model prices, and the fuller usage row. Consumes: 13 (the gate, the action log), 2 (the gateway, `usage_events`). Carries out ledger D1's planned move to multiple providers; switching models from admin is the next unit. Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D1](../02-design.md) Anthropic-direct was the v1 step toward multiple providers · [D52](../02-design.md) tasks reach models through a database catalog · [D53](../02-design.md) prices belong to a model, token counting to a provider class · [D56](../02-design.md) the fuller usage row. Anthropic-isms: [docs/anthropic-specific.md](../anthropic-specific.md).

## The shape

**A call names a task; the task names a tier; the tier names a model in the catalog.** That chain existed in v1 with its last link in `config/ai.php`. This package moves the last link into the database, so the next unit can change it from admin, and moves everything provider-specific — cache markers, token counting, pricing — into one class per provider.

**What was tied to one provider, and where it goes** (confirmed by reading the code 2026-09-18):

| v1 | After 14 |
|---|---|
| `PendingCall` passes a model, never a provider | passes the tier's primary and backups as provider→model pairs |
| `Tier::model()` / `pricing()` / `timeout()` read config | read `ai_tiers` |
| `UsageRecorder::cost()` prices by tier | prices by the serving model |
| cache multipliers are global config | cache prices are per model |
| `PromptCaching` runs for Anthropic from the gateway | lives in the Anthropic provider class |
| `tier: session` reasons from Anthropic's per-model cache | means "the model this session started on" |

---

## 14.1 Catalog and tiers

- **14.1.1 MUST** — `ai_models`: provider (SDK driver key), model id, display name, active, and per-million-token prices for input, output, cache read and cache write. The migration seeds today's three models at today's prices. *Done: the dev database gains three rows and nothing else changes.*
- **14.1.2 MUST** — `ai_tiers`: `cheap`, `mid`, `top`, each with a primary model, an ordered list of backup models, and a timeout. `Tier::model()`, `pricing()` and `timeout()` read it. *Done: three tiers resolve to three distinct models; changing a row changes the lesson fixture's model with no code diff.*
- **14.1.3 MUST** — Tier resolution is cached and cleared on write. *Done: a query-count test sees zero catalog queries across ten calls.*
- **14.1.4 MUST** — `NoModelIdsOutsideConfigTest` becomes `NoModelIdsInCodeTest`; the seeding migration and test fixtures are the only exceptions. The `tiers` block leaves `config/ai.php`. *Done: the test passes; `config/ai.php` has no `tiers` key.*
- **14.1.5 MUST** — A call hands the SDK the tier's primary and backups, and the SDK fails over in order. The usage row records the model that served. *Done: a fake rate-limits the primary; the row names the backup, outcome `failed_over`.*
- **14.1.6 MUST** — A `tier: session` prompt runs on the model its session started on. *Done: changing `mid`'s model mid-lesson leaves that lesson's `lesson.section` rows on the original model.*

## 14.2 A class per provider

- **14.2.1 MUST** — One class per provider owns its cache markers, its token counting and its pricing. "Input tokens" means uncached input everywhere. `PromptCaching` moves into the Anthropic class. *Done: the [2.4](02-ai-layer.md) wire test passes unchanged; request bodies are byte-identical before and after.*
- **14.2.2 MUST** — A model can be marked active only if its provider has a class. *Done: validation refuses activating an `openrouter` model while no OpenRouter class exists.*
- **14.2.3 MUST** — Each class has a token-counting test against a recorded provider response that includes cached tokens. *Done: the Anthropic test passes.*
- **14.2.4 MUST** — A call is priced from the serving model's prices when its row is written. *Done: equal tokens on two models cost differently; editing a price leaves past rows unchanged.*

## 14.3 The fuller usage row

- **14.3.1 MUST** — `usage_events` gains `course_id` and `lesson_id` (null on delete), `ai_model_id`, `prompt_version_id` and `experiment_variant_id` (filled by 15), `reasoning_tokens`, `outcome` (`ok` | `failed` | `failed_over`), `acting_user_id`, and `plan`. Additive. *Done: the migration runs on the dev database and every existing row survives.*
- **14.3.2 SHOULD** — Existing rows get an outcome: `failed` where input and output tokens are both zero, `ok` otherwise. (Probable: that is how v1 records a failure.) *Done: the `failed` count equals the zero-token count before the migration.*
- **14.3.3 MUST** — `PendingCall::about(Course|Lesson)` attaches the course or lesson; every call site except `subtopic.check` uses it. *Done: in the acceptance journey, every row but `subtopic.check`'s has a `course_id`.*
- **14.3.4 MUST** — `plan` is copied onto the row when written. *Done: after the demo sweep, `sum(cost_usd) where plan = 'demo'` is unchanged.*

## 14.4 Models screen

- **14.4.1 MUST** — Admin → Models lists the catalog, and the three tiers with their backups, with calls and cost per model over seven days. *Done: renders the fixture.*
- **14.4.2 MUST** — Editing a model's prices is logged and applies only to later calls. *Done: feature test.*

## 14.5 Documents

- **14.5.1 MUST** — The README's first line and roadmap state that multiple providers is the plan. D1 gets a dated note: its "a config change" claim did not hold for the v1 code, and 14 makes it hold. Spec [2.1.1](02-ai-layer.md) is rewritten for the catalog. `anthropic-specific.md` #2 is retired. *Done: grep finds no "only file that may name a model".*

## 14.6 Out of scope

- **14.6.1 WONT** — Provider classes beyond Anthropic.
- **14.6.2 WONT** — Importing prices from LiteLLM.
- **14.6.3 WONT** — Changing a tier's model from admin.

All three are the next unit's.

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. Run one course's interviews and lesson 1.
2. Every usage row has `ai_model_id`, `outcome` and `plan`; every row after `subtopic.check` has `course_id`.
3. Change `mid`'s primary model by SQL. The next interview turn records the new model; the lesson already open stays on the old one.
4. Admin → Models shows both models with their calls.
