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

## D7a. Course exclusivity and exit — **one open course per subtopic; exit deletes the row** (resolved 2026-08-23, package 3 session; **exit-deletes amended by D7b**)

Invariant: for each (user, subtopic) at most one course is *open*, where open = any state short of `completed`. Enforced by a partial unique index, not by the 4.2 transition service alone, so concurrent creation cannot slip past it. A completed 101 alongside an open 201 stays legal — only one course writes.
Exit deletes the course row and its lessons and quiz attempts, behind a confirmation ("exiting deletes all progress; you start again from the beginning"). `completed` stays the machine's only terminal state.
Rejected: an `abandoned` status — D7's unique (user, subtopic, skill_level) would keep the tombstone forever, so exiting a level once locks the student out of it for good.
Rationale: forbidding concurrency retires D8's accepted cost outright; deleting rather than tombstoning frees the level again with no loosening of D7's uniqueness.
Riders:
- Memory is not deleted with the course. The subtopic WK is student state (D8) and survives by design — the knowledge is still true whatever happened to the course. The course's `{level}` documents (CRITERIA, GUIDANCE) regenerate at the same paths on restart; orphans appear only where a regenerated syllabus drifts the lesson slugs. Unread garbage, accepted for v1 — the path API (3.2) has no delete.
- **4.3 feeds the existing WK in.** `wk.create` overwrites the subtopic document blind today (2.3.2 inputs: transcript/subtopic/skill_level), which under D8 erases accumulated knowledge whenever a *second* course starts in a subtopic — after an exit, and on every 101 → 201 progression. Settled: the prompt gains `prior_wk_doc`, always supplied — the accumulated document, or an explicit "none yet" for the first course in the subtopic. One inventory row rather than a second near-identical prompt, and 2.3.1 has no optional vars, so an explicit empty value is the shape that fits. Landing it updates the 2.3.2 inputs cell, so it lands with 4.3, not before. 5.4.4's erosion guard does not cover this path — it guards `wk.update` — so 4.3 carries its own regression test.

## D7b. Withdrawal replaces deletion; attempts replace lockout — **amends D7a** (resolved 2026-08-24, package 4 session, Colin; **terminal status superseded by D7c**)

D7a's exit-deletes-the-row is withdrawn. Quitting a course now stamps `courses.withdrawn_at` and keeps the row; `courses.attempt` (scoped to user × subtopic × skill_level) lets a level be taken again.

**What D7a got wrong.** It rejected an `abandoned` status for one reason: D7's unique (user, subtopic, skill_level) would hold the tombstone forever, locking the student out of that level for good. An attempt counter dissolves that reason — attempt 2 of Algebra 101 is a distinct row, so the tombstone blocks nothing. D7a then reached for deletion to escape a constraint that did not have to bind, and paid for it by destroying progress a student might want back. The unique key becomes (user, module_slug, subtopic_slug, skill_level, attempt).

**Withdrawal is not a status.** `status` answers *where in the pipeline*; `withdrawn_at` answers *are they working on it*. Conflating them forces a second column remembering the state to restore, and Colin's resume flow ("pick up where you left off, or start from scratch?") needs that state intact. Keeping them apart makes resume a null-out of one timestamp and restart an insert with `attempt + 1`.

**The exclusivity invariant survives, reworded.** Open = `withdrawn_at IS NULL AND completed_at IS NULL`. Still a partial unique index on (user_id, module_slug, subtopic_slug), still enforcing D8's real requirement — one writer per subtopic WORKING_KNOWLEDGE.md — and still catching concurrent creation the 4.2 service alone would miss (a double-tapped Start button is enough). Resuming re-occupies the slot, so it fails the index while another course is open. That is correct behaviour, not a bug.

**Deferred, not rejected: a `course_status_types` lookup table** (Colin's proposal, pressed three times and rightly). The objection was specific — a partial unique index predicate may reference only that row's own columns, so the open-course rule could not join to an `is_active` column, and enforcement would have dropped to application code. **D7c removes that objection entirely: the predicate no longer reads `status` at all.** What remains is preference, not constraint: a status needs code — a screen, a button, an arrow into it — before a row means anything, and the arrows stay in code either way. So v1 ships a `CourseStatus` enum because nothing yet needs per-status metadata, and `courses.status` is free to become a foreign key the moment something does. A future session must read this as open.

Rejected: a `failed` end state (Colin's proposal). 6.3's done-condition already reads "fail records attempt, course stays `active`" — failing the final exam returns the student to studying, so it is not an ending. The pedagogical signal Colin wanted for a second attempt is better served by the `quiz_attempts` row 6.1/6.3 already writes, which names the concepts missed rather than only the verdict. A student who fails repeatedly and gives up has *withdrawn*; two end conditions suffice.

Naming: `withdrawn_at`, not `quit`/`exited`. Academic register, and it avoids `abandoned` — the word D7a attached to the design it rejected.

Riders:
- **Only the highest-numbered attempt is resumable.** Lesson GUIDANCE paths (4.5.3) carry no attempt number, so attempt 2 overwrites attempt 1's material at the same paths. Resuming attempt 1 afterwards would render attempt 2's teaching notes under attempt 1's lesson rows. Prior versions survive in `memory_versions` (3.1); nothing is destroyed, but `get()` returns the newer text. The rule closes the hole without putting attempt numbers in memory paths.
- D7a's memory rider stands unchanged: withdrawing does not touch the memory tree. The subtopic WK is student state (D8) and survives by design.
- Confirmation copy is no longer a deletion warning. On return: "Looks like you tried this once already — pick up where you left off, or start from scratch?"

## D7c. Both endings are dates, not statuses — **amends D7b** (resolved 2026-08-24, package 4 session, Colin)

`completed` leaves `CourseStatus`. A course that passed the final exam keeps `final_exam` as the furthest point it reached and carries `completed_at`; the pipeline has no end state. `CourseTransitions::complete()` stamps it, gated on standing at `final_exam` (6.3). A CHECK forbids `withdrawn_at` and `completed_at` both being set.

**Why.** D7b's open-course index read `WHERE withdrawn_at IS NULL AND status <> 'completed'`, freezing a status name into SQL that a partial predicate cannot derive from the enum. That was guarded by a drift test rather than made impossible — Colin's standing objection to hardcoded strings in a database rule, and he was right. Following D7b's own logic one step further removes them: withdrawal was already a date because `status` answers *where in the pipeline* and not *are they engaged*; completion is the same kind of fact. The rule becomes `withdrawn_at IS NULL AND completed_at IS NULL` — no strings, nothing to keep in step, no migration when a status changes.

**Second payoff.** The predicate no longer references `status`, so `courses.status` can become a foreign key to a lookup table without touching the index. That reopens D7b's deferred `course_status_types` proposal.

Rejected: keeping `completed` as a status *and* stamping `completed_at`. Two sources of truth for one fact, reconcilable only by a CHECK that hardcodes `'completed'` — the string this change exists to remove.

Accepted cost: the schema now makes completion *look* as reversible as withdrawal, since both are nullable dates. Nothing prevents un-stamping `completed_at`. `CourseTransitions` is the only writer and offers no such method; not worth a trigger in v1.

Rider — **superseded attempts' lesson rows point at replaced material.** Colin settled that students return only to the most recent attempt, so attempt numbers stay out of the 4.5.3 GUIDANCE path. Consequence: an earlier attempt's `lessons` rows keep `guidance_path` values whose contents a later attempt has since overwritten (where lesson slugs match) or orphaned (where they differ). Nothing reads them today — the lesson engine (5.1) only ever loads the current lesson of the open course. Any future feature that renders a *past* course's lessons must not read GUIDANCE for a course that is not the highest attempt at its level; it would silently show another course's material. Fixing it later means putting `{attempt}` before `{lesson-slug}` — the attempt scopes the whole syllabus, not one lesson — and migrating existing paths.

## D7d. One status vocabulary; two of its values are derived (resolved 2026-08-24, package 4 session, Colin)

`CourseStatus` lists all nine states a course can be in: the seven pipeline positions plus `withdrawn` and `completed`. The column stores only the seven. `Course::standing()` returns the ninth-case answer, reading `completed_at` and `withdrawn_at` first and falling back to the stored position.

**The gap Colin named.** After D7c no single value answered "what is going on with this course?"; a caller had to combine `status`, `withdrawn_at` and `completed_at`. Every list, badge and filter would re-derive that, and re-derive it slightly differently. Scattered null checks were a fair objection to D7c as shipped.

**Why the two endings are still not stored.** Writing `withdrawn` into the column overwrites the pipeline position — a student who withdrew during `syllabus_revision` has nowhere to be put back, and restoring it needs the `withdrawn_from` column D7b removed. Writing `completed` gives one fact two homes that can disagree, reconcilable only by a CHECK hardcoding `'completed'` — the literal D7c exists to delete. Neither value is declared a successor of anything, so the transition service cannot reach them; a test pins that shut.

Rejected: **a second enum** (`CourseStanding`) holding the endings plus copies of the seven pipeline cases — built, then removed the same session. Colin's objection was correct and general: duplicating a list to extend it is wrong in any medium, and the two near-identical names were a smell dressed up as a drift test. A lookup table would have held all nine rows with a flag and never invited the duplication; the enum should have done the same from the start.

Rejected: a stored generated column (`GENERATED ALWAYS AS … STORED`) computing the same value in Postgres. Drift-free and queryable in SQL, but it buys only `where(...)` on a value nothing filters by yet, at the price of a Postgres-specific feature and the status literals back in the schema. Revisit if a screen needs to sort or paginate by standing.

Accepted cost: the enum holds two values the column may not. `isPipelinePosition()` names the distinction, and `CourseTransitionsTest` proves the service cannot write them.

## D8. WORKING_KNOWLEDGE scope — **subtopic, not level** (resolved 2026-08-23, package 3 session)

`{module}/{subtopic}/WORKING_KNOWLEDGE.md`. One document per subtopic, accumulating across levels; a completed 101 course is what a 201 course in the same subtopic starts from.
Rejected: level-scoped (`{module}/{subtopic}/{level}/WORKING_KNOWLEDGE.md`) — symmetric with 4.5.3's GUIDANCE path, but GUIDANCE is per-course *material* while WK is *student state*. Level shards make 4.3's knowledge interview re-elicit at 201 what 101 already recorded, and leave 6.4's subtopic-grain refreshers merging shards at read time.
Accepted cost, since retired by D7a: concurrent courses in one subtopic would have shared the document under 5.4.4's full-document replacement (last-writer-wins, plus 5.1 cached-prefix invalidation). D7a forbids the concurrency, so neither is reachable. The live risk is 4.3's blind `wk.create` instead — recorded as D7a's open item.
Rider for 3.3: pointer lines dedupe by path — one line per subtopic with at least one completed course, naming the levels completed, not one line per course.

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

## W3. Default branch — **`master`** (founder convention).

## W4. Branching — **hybrid trunk-based**

Serial solo work commits directly to `master` at done-condition granularity (one spec item or small cluster per commit, item number in the message). Branches + founder-reviewed merges only for worktree lanes and risky spikes.
Rejected: everything-on-master forever (contradicts W2's lane rule — lanes need branches), branch-per-package with self-PRs (review ceremony duplicating the live in-session review AI-augmented work already gets).
Commit messages carry no signing, co-author trailers, or attribution lines (founder instruction).

## W5. Session start — **`/package <n>` slash command** (`.claude/commands/package.md`)

Scope + boundaries + commit rules + exit ritual, versioned in-repo. Rejected: minimal prompt (scope discipline implicit only), pasted template (drifts outside the repo).

## Corrections during spec phase

- **GUIDANCE.md is model-facing only** — read by exactly one consumer (lesson engine). User-facing syllabus view renders from the same frozen syllabus JSON; consistency by construction. CI check: no route/component reads GUIDANCE.md.
- **Skill level ⊥ billing tier** (founder correction): 101/201/301 is pedagogy, price is economics. Depth = f(skill_level, plan); v1 plan is constant. 101 may legitimately be *longer* than 301. Any future depth-gating enters through `plan`, never skill level.
