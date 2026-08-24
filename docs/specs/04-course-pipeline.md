# Spec 4 — Course Pipeline

*Provides: course state machine (4.2), syllabus + GUIDANCE artifacts consumed by 5/6. Consumes: 2 (gateway, prompts), 3 (path API). See PLAN.md for status.*

State machine (ledger D7): created → knowledge_interview → goals_interview → syllabus_draft ⇄ syllabus_revision → active → final_exam → completed. Placement states slot in at v1.1. Exclusivity (ledger D7a): at most one course per (user, subtopic) short of `completed`, held by a partial unique index; exit deletes the course row rather than tombstoning it.

- **4.1 MUST** — Preset catalog (Math, Science, History, Language, Econ, Philosophy + subtopics) + free-text creation; mismatch handled at 4.5.7. *Done: both paths create `courses` row in `created`.*
- **4.2 MUST** — State machine as single transition service; workflows fire on transitions; illegal transitions throw. *Done: per-state side-effect tests.*
- **4.3 MUST** — Knowledge interview (streamed; ends per 2.3.4) → `wk.create` → `{module}/{subtopic}/WORKING_KNOWLEDGE.md` (subtopic-scoped, ledger D8). Interview transcript discarded after summarization. **Open (D7a)**: the WK is shared across levels, so a second course in the subtopic must not `wk.create` over it blind — branch on whether the document already exists. *Done: WK file with solid/shaky/gaps; a 101→201 progression keeps the 101 document's claims.*
- **4.4 MUST** — Goals interview → `criteria.create` → `COURSE_CRITERIA.md`. *Done: analogous.*

## 4.5 Syllabus generation, review, approval

- **4.5.1 Generation — MUST.** Inputs: WK + CRITERIA + skill-level variant + depth params. Tier top. Strict JSON; 1 retry; then fail-**closed** (user-facing error — nothing downstream exists to protect). Global rule: *fail open only when failing closed would destroy student progress.* *Done: fixture produces valid syllabus; double-failure shows error path.*
- **4.5.2 `json/syllabus.v1` — MUST.** `{course_title, overview, lessons[{position, title, objective, sections[{title, concepts[slugs]}], homework_focus, estimated_minutes}], final_exam_concepts[], subtopic_mismatch|null}`. The `concepts` slug vocabulary is the connective tissue: declared here, tagged in checkpoints (5.2.2), reported in summaries (5.4.2), drawn on by exams/refreshers (6.3/6.4). *Done: schema enforced; every downstream slug resolves to a declared slug.*
- **4.5.3 GUIDANCE.md materialization — MUST.** At approval, each lesson renders `{module}/{subtopic}/{level}/{lesson-slug}/GUIDANCE.md` from JSON via template. **Model-facing only — read by exactly one consumer, the lesson engine (5.1).** User-facing syllabus view renders from the same JSON. Materialization safe because approved syllabus is immutable (revisit if that ever changes). *Done: approval writes N files + versions; golden-file template test; CI check: no route/component reads GUIDANCE.md.*
- **4.5.4 Review & revision — MUST.** Approve/revise UI; revise = free-text feedback → regenerate with [prev JSON + feedback + inputs]; cap 3 revisions then approve-or-restart. Drafts persist on course row (app state, not memory tree). *Done: E2E revise loop; 4th attempt blocked.*
- **4.5.5 Approval side-effects — MUST.** One transaction: lesson rows (lesson 1 `available`, rest `locked`) → materialization → state `active`. *Done: mid-transaction kill leaves zero or all effects.*
- **4.5.6 Depth parameters — SHOULD.** `depth = f(skill_level, plan)`, config-keyed `depth.{skill_level}.{plan}`. v1: plan constant; depth varies by skill level on **pedagogical** grounds (101 may be longer than 301). **Skill level ⊥ price** (founder correction). Post-v1 billing attaches to `plan` only. Billing itself **WONT** in v1. *Done: changing plan alone changes depth in fixture.*
- **4.5.7 Mismatch handling — MUST.** Non-null `subtopic_mismatch` → non-blocking banner on review screen. *Done: "Math/French Cooking" fixture shows banner; approval still possible.*
