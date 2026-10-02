# Spec 2 — AI Service Layer

*Provides: the gateway (2.1), usage log (2.2), prompt system (2.3) consumed by packages 4–6. Consumes: 1.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D1](../02-design.md) Anthropic-direct behind a provider abstraction · [D4](../02-design.md) one real agent, everything else scripted · [D14](../02-design.md) a Q&A turn gets its own inventory row · [D19](../02-design.md) `homework_results` on `wk.update` · [D25](../02-design.md) `top`-tier calls run on the queue. Anthropic-isms: [docs/anthropic-specific.md](../anthropic-specific.md).

- **2.1 MUST** — Provider gateway: all calls through one service wrapping laravel/ai; call sites name a capability tier. *Done: no call site names a model — the rule and its test belong to [14.1.4](14-routing-and-usage.md).*
  - **2.1.1 MUST** — Tier→model resolution. **Rewritten 2026-09-20 ([14.5.1](14-routing-and-usage.md)); the map is no longer config.** A tier is a row in `ai_tiers` naming a primary model, ordered backups and a timeout; a model is a row in `ai_models` carrying its provider and its prices. Spec [14.1](14-routing-and-usage.md) owns both tables, [14.2](14-routing-and-usage.md) owns the class per provider, and this item owns only the call-site contract it always did: **a call site names `cheap`, `mid`, `top` or `session`, and nothing else in the app names a model.** Plan-based routing and adding a provider are a row and a class, not an edit here. *Done: `Tier::model()` resolves through `App\Ai\Catalog`; changing `mid`'s row alters the lesson fixture's model with no code diff (14.1.2).*
- **2.2 MUST** — Usage instrumentation: every call writes `usage_events` (feature, tier, model, input/cached/output tokens, latency). *Done: seeded demo cycle yields per-feature cost report from SQL alone.*
  - **2.2.1 SHOULD** — `php artisan usage:latency` reports each prompt's call count, average and worst latency beside its tier's timeout and the host's between-bytes ceiling. Added 2026-09-12: 2.2's report is readable "from SQL alone" only where there is a SQL prompt, and the deployed environment has none — the numbers are wanted where the ceilings are real, and the ceilings are what [T10](../lessons.md) is about. *Done: the command names every prompt that has run, and flags one whose worst latency reaches `--ceiling`.*
- **2.4 MUST** — Prompt-caching spike (**first task of week 1** — outcome can reorder caching design): verify `cache_control` reaches Anthropic; work around laravel/ai#119 for system prompts. *Done: `cache_read_input_tokens > 0` on second call of a repeated-prefix test.* Riskiest cell in the spec.
- **2.5 SHOULD** — Portability ledger `docs/anthropic-specific.md`. *Done: exists; 2.4 workaround is entry #1.*

## 2.3 Prompt inventory & contracts

- **2.3.1 Conventions — MUST.** Files in `prompts/`, YAML frontmatter (`key`, `tier`, `output: stream|json/<schema>`, `vars: [...]`) + template body. Render fails on missing/undeclared vars. Prompt edits invalidate that prompt's cache prefix on deploy (accepted). **Amended 2026-09-20 ([D54](../02-design.md), [15.1](15-prompt-registry.md)): a file is the reviewed baseline, not what runs.** `prompts:sync` reads the tree into `prompt_versions` on deploy, and the gateway renders the active version; git is the history of file versions, `prompt_versions` the history of what ran. `output` and `vars` still come only from the file, so this item still owns the frontmatter contract and the render rules. *Done: loader test — missing var throws; unknown key throws; tier consumed by gateway — and [15.1.5](15-prompt-registry.md) proves the same two against the database-backed repository.*
- **2.3.2 Inventory — MUST.** Any AI call site without a row here is a spec violation.

| Key | Tier | Inputs | Output | Consumer |
|---|---|---|---|---|
| `persona.default` | — | student_name, subtopic, skill_level | fragment, composes into `lesson.system` only | 2.3.3 |
| `subtopic.check` | cheap | catalog, module, name, collision | `json/subtopic-scope.v1` | 4.9.2 |
| `interview.knowledge` | mid | module, subtopic, skill_level, pacing | streamed conv + `signal_complete` tool | 4.3 |
| `interview.goals` | mid | subtopic, skill_level, wk_doc, pacing | same shape | 4.4 |
| `wk.create` | mid | interview_transcript, subtopic, skill_level, prior_wk_doc | markdown (solid/shaky/gaps) | 4.3 |
| `criteria.create` | mid | interview_transcript, subtopic | markdown (goals/interests/success/eval-style) | 4.4 |
| `syllabus.generate` | top | wk_doc, criteria_doc, skill_level, depth_params | `json/syllabus.v1` | 4.5.1 |
| `syllabus.revise` | top | prev_syllabus_json, feedback, wk_doc, criteria_doc, depth_params | `json/syllabus.v1` | 4.5.4 |
| `lesson.system` | mid | persona, criteria_doc, wk_doc, guidance_doc | system prompt of the one real agent | 5.1 |
| `lesson.section` | (session) | section_index, section_title, section_concepts | streamed markdown | 5.2 |
| `lesson.qa` | (session) | question | streamed markdown | 5.2 |
| `checkpoint.generate` | cheap | section_transcript, guidance_section | `json/checkpoint.v1` | 5.2.1 |
| `lesson.summarize` | mid | lesson_transcript, guidance_doc, checkpoint_results | `json/lesson-summary.v1` | 5.4 |
| `wk.update` | mid | prior_wk_doc, lesson_summary_json, homework_results | markdown (full replacement) | 5.4, 6.2 |
| `homework.generate` | mid | lesson_summary_json, homework_focus | `json/quiz.v1` + suggested_reading[] | 6.2 |
| `exam.final` | top | syllabus_json, wk_doc, question_count | `json/quiz.v1` | 6.3 |
| `quiz.refresher` | cheap | scope_docs, scope_label | `json/quiz.v1` | 6.4/6.5 |
| `explainer.select` | cheap | selection, surrounding_chunk, subtopic, skill_level | short markdown (≤200 words) | 5.5 |

Deliberate absences: grading (code, 5.2.4), module rollup (derived, 3.3), GUIDANCE.md (template render, 4.5.3). `lesson.qa` is a pass-through by design — the session's instructions are already `lesson.system`, so the file exists to name the call, carry the session tier, and give per-turn framing somewhere to live if tuning ever wants it (package 5 session, 2026-08-25). `json/quiz.v1` = checkpoint schema generalized (n questions + scope); one schema, three consumers.
*Done: inventory ↔ codebase audit passes.*
- **2.3.3 Composition & cache order — MUST.** `lesson.system` = [persona | criteria | wk | guidance], most-stable-first (matches 5.1 cached prefix); persona prepends only here. *Done: order unit test; persona edit leaves other prompts byte-identical.*
- **2.3.4 Interview completion — MUST.** One tool `signal_complete(reason)`; Done button bypasses to same summarization. Provider-neutral. *Done: fake-provider test both paths.*
- **2.3.5 Prompt fixtures — SHOULD.** Golden tests for template rendering only; no model-output goldens (flaky by nature). *Done: 1:1 render coverage.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

Against a truncated `usage_events` table:

1. Drive one `mid`-tier call and one `top`-tier call from anywhere in the app. Both write a `usage_events` row carrying feature, tier, model, input / cached / output tokens, latency and cost — and the total spend of those two calls is one SQL query.
2. Every `feature` written is a key in 2.3.2's inventory, and every model named is one the catalog holds. `grep` for a model ID anywhere in `app/`, `config/` or `prompts/` returns nothing ([14.1.4](14-routing-and-usage.md)).
3. Repeat a call over the same stable prefix. The second row's cached-read tokens are above zero — the prefix was read from cache, not rewritten.
4. Point `mid`'s row at another model and repeat step 1. The `mid` row names the new model, and there is no diff at all ([14.1.2](14-routing-and-usage.md)).
5. Render a prompt with a variable missing from its frontmatter, and one with an undeclared variable supplied. Both throw rather than rendering a hole.
