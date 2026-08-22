# OpenTutor — Design Decision Ledger

*Recorded 2026-08-21. Each decision: chosen candidate, rejected candidates, deciding rationale.*

## D1. Model sourcing — **C: Anthropic-direct with clean provider abstraction**

Rejected: A (direct, no discipline — lock-in by accretion), B (OpenRouter — +5.5% fee, extra debug hop, beta features unreachable, buys flexibility with no v1 use).
Rationale: list price, first-party caching, betas reachable; the Laravel AI SDK's provider abstraction makes later multi-provider a config change. Every Anthropic-ism is logged in a portability ledger (spec 2.5). User model selector deferred, not foreclosed.

## D2. Frontend — **B: Inertia + React**

Rejected: Livewire (weaker fit for client-heavy quiz components and select-mode; founder conversant in React removes the velocity argument), Inertia+Vue (smaller AI-UI ecosystem).
Rationale: both signature UX features (generative quiz components, side-tab explainer) are client-heavy; AI-streaming ecosystem's center of gravity is React.

## D3a. Memory storage — **B: virtual file tree in Postgres**

`memories` (user, path, content) + `memory_versions`. Paths look like files to the model; rows to the app.
Rejected: real files (clumsy prefix queries, DIY versioning, tenant-isolation risk), hybrid (two systems to keep honest).
Rationale: prefix queries, transactions, versioning, tenant scoping, future pgvector — all DB-native. Model-facing interface identical either way, so reversible.

## D3b. Memory writes — **B: workflow-initiated**

App code fires summarization at defined moments (lesson end, exam pass, syllabus approval) and writes results; model gets read access.
Rejected: model-initiated (unreliable, format drift — refresher quizzes can only trust reliably-written memory), mixed (v1.1 option: model scratchpad).
Note: the brain dump itself specifies every write moment — it was already a workflow spec.

## D4. Agent topology — **C: one real agent + scripted workflows**

Only the lesson session is an open-ended agentic conversation. Interviews, placement, syllabus, summarization, homework, explainer, refreshers = scripted calls (or short bounded conversations) fired by a Laravel state machine. No orchestrator agent — Laravel is the orchestrator.
Rejected: mega-agent (one fragile prompt, worst cost, contradicts D3b), agent team (orchestration overhead without benefit — every non-lesson step has fixed inputs/outputs).
Each step = own session, fresh context; markdown artifacts are the interface between sessions. Per-call model tiers (cheap/mid/top). Fully provider-neutral.

## D5. Lesson lifecycle — **B: code-paced sections**

GUIDANCE.md fixes sections at planning time. Per section: stream prose → scripted checkpoint call (structured MCQ JSON → React component, code-graded) → free Q&A turns → student hits Continue → code advances. One conversation thread; code owns transitions.
Rejected: model-paced (checkpoints skippable, mid-stream parsing, unresumable), model-triggered transitions (drift, parser work).
Riders: context assembly order = cache order ([persona | CRITERIA | WK | GUIDANCE] stable prefix, then transcript); checkpoint grading is code (answer key in JSON).

## D6. v1 cut — **B, amended: +#14 module rollup (derived)**

v1 MUST: auth/18+ ToS · module/subtopic catalog + free creation (mismatch flagged in syllabus call, non-blocking) · 3 skill levels · knowledge interview→WK · goals interview→CRITERIA · syllabus gen + approve/revise · lesson engine (the bet) · side-tab explainer · quiz homework + suggested reading · lesson summarization→WK update · final exam · refresher quizzes (subtopic + lesson grain) · module WK rollup (derived, not materialized — renders pointer lines from completed courses at read time; zero drift) · minimal PWA shell · token instrumentation · artisan memory inspector.
v1.1: placement quiz + level gating · teaching-style selection (schema column ships now, one style) · student notes panel (founder idea, this session) · random-quiz-across-everything (~free once rollup exists) · email nudges.
Post-v1: billing/tiers · essay/project homework · model selector · offline · under-18.
Schedule ejection order if the month runs out: final exam → lesson-grain refreshers → side-tab (differentiators last).

## D7. Data model — approved

Organizing rule: **model reads/writes it as a document → memory tree; app queries/branches on it → relational row.** Nothing stored twice; lesson rows carry tree paths.
Entities: users · modules · subtopics · courses (user × subtopic × skill_level, status) · lessons (position, status, guidance_path) · quizzes (polymorphic: checkpoint/homework/final/refresher) · quiz_attempts · memories + memory_versions · usage_events.
State machine: created → knowledge_interview → goals_interview → syllabus_draft ⇄ syllabus_revision → active → final_exam → completed. Placement states slot in at v1.1.
Contested cells: transcripts as **messages rows** (not JSON blob — resume, queries, streaming appends); quizzes as **table** (not in-transcript — four features share it).

## R1. Personas — **repo prompt files** (`prompts/styles/*.md`), `courses.style` column stores file key. Prompts are code until users can edit them.

## R2. PWA scope — **minimal**: manifest, icons, installable, responsive, iOS install-instructions page. No offline (v1.2 candidate).

## R3. Interview termination — **model signals via `signal_complete` tool + always-visible Done button**. Either path fires the same summarization workflow.

## R4. Hosting — **Laravel Cloud, revisit post-v1.** Not an AWS alternative — a managed layer on AWS. Rationale: near-zero ops in the month that matters; app stays a standard Laravel app (low exit cost). Constraint: host must not buffer SSE.

## W1. Planning docs — **in-repo `docs/`, committed**

Rejected: GitHub wiki (outside agents' working tree, drifts from code), separate repo (coordination cost), gitignored (no history, absent in worktrees).
Rationale: specs/ledgers are the contract code is built against (ADR/docs-as-code practice); every AI session and worktree gets them for free only in-repo. "Working docs don't belong in the repo" applies to scratch, not contracts.

## W2. Build workflow — **per-package specs + PLAN.md; worktree parallelism selectively, late**

Rejected: monolithic spec (whole spec loads into every session — context waste), full parallel-from-day-one (weeks 1–2 are strictly serial: 1→2→3 are each other's substrate; solo dev becomes integration manager too early).
Chosen workflow:
1. Spec split along WBS package boundaries into `docs/specs/`; numbering preserved (5.2.4 stays 5.2.4) — spec numbers remain the shared vocabulary.
2. `PLAN.md` (repo root): global acceptance test, dependency graph, status table with done-condition checklists, WONTs.
3. `CLAUDE.md` (repo root, auto-loaded every session): pointers + conventions + session ritual.
4. One session = one package (or one deep node), serial through week 2; end-of-session ritual updates PLAN.md.
5. Parallel worktree lanes only in weeks 3–4, only for decoupled packages (7 PWA, tooling, 6.x against 6.1's fixed contract); founder reviews and merges every lane — never auto-merge.
6. Parallelism rule: **a lane may open only when every interface it consumes has landed on main.**

## Corrections during spec phase

- **GUIDANCE.md is model-facing only** — read by exactly one consumer (lesson engine). User-facing syllabus view renders from the same frozen syllabus JSON; consistency by construction. CI check: no route/component reads GUIDANCE.md.
- **Skill level ⊥ billing tier** (founder correction): 101/201/301 is pedagogy, price is economics. Depth = f(skill_level, plan); v1 plan is constant. 101 may legitimately be *longer* than 301. Any future depth-gating enters through `plan`, never skill level.
