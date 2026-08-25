# Spec 2 — AI Service Layer

*Provides: the gateway (2.1), usage log (2.2), prompt system (2.3) consumed by packages 4–6. Consumes: 1. See PLAN.md for status.*

- **2.1 MUST** — Provider gateway: all calls through one service wrapping laravel/ai; call sites name a capability tier. *Done: grep finds zero model IDs outside config.*
  - **2.1.1 MUST** — Tier→model map in `config/ai.php` (`tiers.{tier}.model`): `cheap`=claude-haiku-4-5, `mid`=claude-sonnet-5, `top`=claude-opus-5 (v1 defaults). Plan-based routing and provider migration are edits to this block only. *Done: three tiers resolve to three distinct models in a config test; changing `tiers.mid.model` alters the lesson fixture's model with no other diff.*
- **2.2 MUST** — Usage instrumentation: every call writes `usage_events` (feature, tier, model, input/cached/output tokens, latency). *Done: seeded demo cycle yields per-feature cost report from SQL alone.*
- **2.4 MUST** — Prompt-caching spike (**first task of week 1** — outcome can reorder caching design): verify `cache_control` reaches Anthropic; work around laravel/ai#119 for system prompts. *Done: `cache_read_input_tokens > 0` on second call of a repeated-prefix test.* Riskiest cell in the spec.
- **2.5 SHOULD** — Portability ledger `docs/anthropic-specific.md`. *Done: exists; 2.4 workaround is entry #1.*

## 2.3 Prompt inventory & contracts

- **2.3.1 Conventions — MUST.** Files in `prompts/`, YAML frontmatter (`key`, `tier`, `output: stream|json/<schema>`, `vars: [...]`) + template body. Render fails on missing/undeclared vars. Git = version history; prompt edits invalidate that prompt's cache prefix on deploy (accepted). *Done: loader test — missing var throws; unknown key throws; tier consumed by gateway.*
- **2.3.2 Inventory — MUST.** Any AI call site without a row here is a spec violation.

| Key | Tier | Inputs | Output | Consumer |
|---|---|---|---|---|
| `persona.default` | — | student_name, subtopic, skill_level | fragment, composes into `lesson.system` only | 2.3.3 |
| `interview.knowledge` | mid | module, subtopic, skill_level | streamed conv + `signal_complete` tool | 4.3 |
| `interview.goals` | mid | subtopic, skill_level, wk_doc | same shape | 4.4 |
| `wk.create` | mid | interview_transcript, subtopic, skill_level, prior_wk_doc | markdown (solid/shaky/gaps) | 4.3 |
| `criteria.create` | mid | interview_transcript, subtopic | markdown (goals/interests/success/eval-style) | 4.4 |
| `syllabus.generate` | top | wk_doc, criteria_doc, skill_level, depth_params | `json/syllabus.v1` | 4.5.1 |
| `syllabus.revise` | top | prev_syllabus_json, feedback, wk_doc, criteria_doc, depth_params | `json/syllabus.v1` | 4.5.4 |
| `lesson.system` | mid | persona, criteria_doc, wk_doc, guidance_doc | system prompt of the one real agent | 5.1 |
| `lesson.section` | (session) | section_index, section_title, section_concepts | streamed markdown | 5.2 |
| `lesson.qa` | (session) | question | streamed markdown | 5.2 |
| `checkpoint.generate` | cheap | section_transcript, guidance_section | `json/checkpoint.v1` | 5.2.1 |
| `lesson.summarize` | mid | lesson_transcript, guidance_doc, checkpoint_results | `json/lesson-summary.v1` | 5.4 |
| `wk.update` | mid | prior_wk_doc, lesson_summary_json | markdown (full replacement) | 5.4 |
| `homework.generate` | mid | lesson_summary_json, homework_focus | `json/quiz.v1` + suggested_reading[] | 6.2 |
| `exam.final` | top | syllabus_json, wk_doc | `json/quiz.v1` | 6.3 |
| `quiz.refresher` | cheap | scope_docs, scope_label | `json/quiz.v1` | 6.4/6.5 |
| `explainer.select` | cheap | selection, surrounding_chunk, subtopic, skill_level | short markdown (≤200 words) | 5.5 |

Deliberate absences: grading (code, 5.2.4), module rollup (derived, 3.3), GUIDANCE.md (template render, 4.5.3). `lesson.qa` is a pass-through by design — the session's instructions are already `lesson.system`, so the file exists to name the call, carry the session tier, and give per-turn framing somewhere to live if tuning ever wants it (package 5 session, 2026-08-25). `json/quiz.v1` = checkpoint schema generalized (n questions + scope); one schema, three consumers.
*Done: inventory ↔ codebase audit passes.*
- **2.3.3 Composition & cache order — MUST.** `lesson.system` = [persona | criteria | wk | guidance], most-stable-first (matches 5.1 cached prefix); persona prepends only here. *Done: order unit test; persona edit leaves other prompts byte-identical.*
- **2.3.4 Interview completion — MUST.** One tool `signal_complete(reason)`; Done button bypasses to same summarization. Provider-neutral. *Done: fake-provider test both paths.*
- **2.3.5 Prompt fixtures — SHOULD.** Golden tests for template rendering only; no model-output goldens (flaky by nature). *Done: 1:1 render coverage.*
