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

Rejected: a stored generated column (`GENERATED ALWAYS AS … STORED`) computing the same value in Postgres. Drift-free and queryable in SQL, but it buys only `where(...)` on a value nothing filters by yet, at the price of a Postgres-specific feature and the status literals back in the schema. Revisit if a screen needs to sort or paginate by standing.

Accepted cost: the enum holds two values the column may not. `isPipelinePosition()` names the distinction, and `CourseTransitionsTest` proves the service cannot write them.

## D8. WORKING_KNOWLEDGE scope — **subtopic, not level** (resolved 2026-08-23, package 3 session)

`{module}/{subtopic}/WORKING_KNOWLEDGE.md`. One document per subtopic, accumulating across levels; a completed 101 course is what a 201 course in the same subtopic starts from.
Rejected: level-scoped (`{module}/{subtopic}/{level}/WORKING_KNOWLEDGE.md`) — symmetric with 4.5.3's GUIDANCE path, but GUIDANCE is per-course *material* while WK is *student state*. Level shards make 4.3's knowledge interview re-elicit at 201 what 101 already recorded, and leave 6.4's subtopic-grain refreshers merging shards at read time.
Accepted cost, since retired by D7a: concurrent courses in one subtopic would have shared the document under 5.4.4's full-document replacement (last-writer-wins, plus 5.1 cached-prefix invalidation). D7a forbids the concurrency, so neither is reachable. The live risk is 4.3's blind `wk.create` instead — recorded as D7a's open item.
Rider for 3.3: pointer lines dedupe by path — one line per subtopic with at least one completed course, naming the levels completed, not one line per course.

## D9. How a conversation runs, and where its transcript lives (resolved 2026-08-24, package 4 session, Colin)

Two decisions 4.3 forced, both with consequences past package 4.

**A conversational prompt file is a system prompt, not a turn.** `interview.knowledge` and `interview.goals` are briefs — they tell the model how to run the conversation. The gateway could only send a rendered prompt file *as the message*, which is fine for the opening turn and useless after it: there was nowhere to put what the student typed. `PendingCall::userTurn()` sends the student's text as the message and renders the brief into the instructions instead. Restricted to `output: stream` prompts, so it cannot become a general way to send text no prompt file produced — the one guard that keeps "prompts are files in `prompts/`" true. The usage row still names the brief's key, because that is what framed the call.
Rider for 5.2: lesson Q&A turns take the same shape — `lesson.system` as instructions, the student's message as the turn.

**Interview transcripts get their own short-lived table.** `interview_messages`, keyed to the course and to which of its two interviews, deleted the moment the document they produced is written. Rows, not a JSON blob on the course, per D7.
Rejected: building the general `messages` table now, shared with 5.2.6's lesson transcripts. Package 4 would be designing package 5's schema before 5.2.6's requirements (replay, reconnect, message ids) are known, and the two have opposite retention rules — lesson turns are kept, because 5.3 resumes from them; interview turns are garbage the moment they are summarized. One table holding both makes "is this row garbage?" a question you answer by reading a discriminator column.
Rider: summarization runs when the stream *ends*, never inside the `signal_complete` tool call. The model is still writing when it calls the tool, so summarizing there would delete the transcript and then append the model's closing line to nothing. The student's Done button has no stream in flight and summarizes immediately; both doors reach the same method (2.3.4).
Rider: an empty summary fails **closed** — transcript kept, course unmoved, student can end the interview again. 4.5.1's global rule points the same way here: failing closed destroys no progress, while writing the empty document would erase what earlier courses in the subtopic recorded (D7a).
Accepted cost: a client that disconnects mid-stream after the model signalled leaves the interview un-summarized. It self-heals — the transcript is intact and the Done button still works — but nothing notices on its own.

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
5. Parallel worktree lanes only in weeks 3–4, only for decoupled packages (tooling, 6.x against 6.1's fixed contract); founder reviews and merges every lane — never auto-merge. **Package 7 was the first name on this list and came off it 2026-08-31** (spec 8.11.5): package 8 owns responsive behaviour, the palette the manifest's `theme_color` must match, and the crest its icons derive from, so 7 consumes an interface that does not exist until 8 lands and rule 6 below refuses it a lane.
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

## D10. The syllabus is rows, and four smaller things 4.5 forced (resolved 2026-08-25, package 4 session, Colin)

### D10.1 Syllabus drafts are their own table, pinned to the inputs they saw

`course_syllabi`: one row per generation, carrying the JSON, the feedback that caused it, pointers to the exact WK and CRITERIA versions the model read, and `approved_at` on the one the student accepted. A partial unique index allows one approved row per course; a second index allows one row per generation, so a double-tapped Revise cannot produce two.

Spec 4.5.4 said drafts persist on the course row. The load-bearing half — app state, not memory tree (D7) — is unchanged; only the storage moved. The spec text is amended.

**Why the table.** Colin wants the rejected drafts and the feedback that killed them, as material for prompt tuning. That is a real consumer, and it is the one thing a column cannot serve: a column holds the current draft and forgets the argument that produced it.

**Why the inputs are pinned.** WORKING_KNOWLEDGE accumulates across every course in a subtopic (D8), so a row storing only feedback and output would replay months later against a document the model never saw — training on a pair that never happened. `memory_versions` already records every `put` (3.1), so the pin costs two foreign keys and no new machinery.

**`generation`, 1-based, not `revision`, 0-based.** A counter starting at zero is falsey, and app logic branching on it misreads the first one (Colin). The codebase already agreed: `courses.attempt` defaults to 1 and `memory_versions.version` starts at 1, so 0-based would have been the odd one out. Renaming is what keeps the change honest — "revision 1" would mean the original, which is not a revision. Generation 1 is the first generation, generations 2–4 are the three revisions 4.5.4 caps, and generation 5 never exists.

**Row existence means successful generation.** A failed call writes no row, so the cap counts what the student actually saw rather than what was attempted.

Rejected: three columns on `courses` (`syllabus_json`, `syllabus_revisions`, `syllabus_approved_at`) — literally what the spec said, one migration, no table, and the revision loop only ever needs the previous draft. YAGNI pointed here until prompt tuning was named as a consumer.
Rejected: a live-draft column plus an append-only history table — one fact with two homes, which is what D7c exists to delete.
Rejected: storing the fully rendered prompt text rather than version pointers — exact and self-contained, but duplicates content `memory_versions` already holds.

Riders:
- **The path API gained a version-aware read.** `MemoryTree::get()` returns content only, and `MemoryDoorTest` (3.2) fails any class under `app/` that names `MemoryVersion`, so package 4 could not learn which version it had read. `MemoryTree::document()` returns content and version id from the same row — one read rather than two, so no `put` can land between them and make the pin name a version the caller never used. `MemoryDocument` is the value it returns.
- **`wk_version_id` gets a database foreign key but no Eloquent relation**, for the same door rule: a `belongsTo(MemoryVersion::class)` in `app/Models/` would trip the test. Package 4 records the identity of what it read and never dereferences it. Whatever eventually resolves a pin back to text goes through the path API.

Revisit if prompt tuning never materialises: the table would then be keeping history nothing reads, and the column form was right.

### D10.2 Concept slugs stay in the JSON; there is no concepts table

The approved syllabus JSON is the slug vocabulary. `SyllabusConcepts` reads it and resolves downstream slugs (5.2.2, 5.4.2, 6.3, 6.4); packages 5 and 6 depend on that class, not on a schema package 4 invented for them.

Rejected: a `course_concepts` table written at approval, with downstream slugs as foreign keys. Database-level integrity and full query-ability, but it designs package 6's schema before 6.4's requirements exist — precisely what D9 refused to do for `messages`. The 2.3.2 inventory gives 6.4 `scope_docs, scope_label`: documents, not concept rows. Nothing queries by concept yet.
Rejected: test-only checking with no runtime resolution — cheapest, and catches nothing a fixture did not already anticipate.

Rider: one rule the JSON schema cannot state is checked in code before any row is written — every slug in `final_exam_concepts` must be declared by a lesson section. That is 4.5.2's done-condition, and the exam is the first downstream consumer, inside the same document.

Revisit when a screen filters or groups by concept. That is the trigger for the table, and the migration is a write-once backfill from approved JSON.

### D10.3 GUIDANCE.md renders from a PHP class, not a Blade template

A renderer class builds the markdown; a golden file is the contract.

Blade would put model-facing text in `resources/views/`, the directory that holds user-facing things, and 4.5.3's CI check — no route or component reads GUIDANCE.md — would then need an exemption for its own template. A rule that starts life with an exception is weaker than one without.

GUIDANCE also has nested repetition (lessons → sections → concepts), which is where flat-file templating stops paying for itself.

Rejected: Blade in `resources/views/` — Laravel's default, easy golden-file test, fussy about markdown whitespace, and the exemption above.
Rejected: a frontmatter-free file in a repo-root `templates/`, mirroring `prompts/` — the better answer the day Colin wants to tune teaching notes without touching code, but it needs a template engine for the nesting, and 2.3.2 already records GUIDANCE as *not* a prompt.

Rider — **the `{lesson-slug}` segment is position-prefixed** (`01-letters-as-numbers`). Two lessons in one course may honestly carry the same title — "Practice", "Review" — and a bare title slug would put their notes at the same path, so the second would silently overwrite the first. The position makes the segment unique and sorts in teaching order for free. A title that transliterates to nothing falls back to the position alone.

Revisit if teaching-note wording starts changing more often than the renderer's structure.

### D10.4 A failed generation writes nothing and moves nothing

Generation performs no transition at all. A failure leaves the course exactly where it stood, with no row written; a failed *revision* leaves it at `syllabus_revision` with the previous draft intact, so the student can retry or approve what they already have.

This is D9's interview rule at a second site: transcript kept, course unmoved. `syllabus_draft` means "in the drafting stage", and the existence of a `course_syllabi` row is what answers "is there a draft?" — so there is no null-JSON special case for a caller to interpret.

The one retry is a plain re-issue of the same call.

**Corrected during implementation.** This decision was first drafted as "a failure leaves the course at `goals_interview`". That is unreachable: `GoalsInterview::next()` is `SyllabusDraft`, and `InterviewSession::summarize()` performs that transition inside the same transaction that writes COURSE_CRITERIA.md, so the course is already at `syllabus_draft` before generation is ever called. Following the row-existence rule instead removes the need for any transition here, which is the smaller design.

Rejected: transitioning into `syllabus_draft` only on success — what the first draft said, and impossible without reopening 4.4.
Rejected: a null draft meaning "generation failed" — a status that no longer means what it says.
Rejected: an error status — explicit, but adds a state nothing may transition out of, and D7d already had to pin two such states shut.
Rejected: a repair turn ("your last output was invalid") as the retry — better odds, but it breaks the cached prefix (2.4) and bills the invalid output back as input.

### D10.5 `lessons` carries exactly what D7 listed

Position, status, `guidance_path`, and nothing else. Titles and objectives are read from the approved syllabus JSON — the same source the student's syllabus view renders from, so the two cannot disagree.

D7's organising rule decides it: the app queries position and status, and the model reads the material. `LessonStatus` ships with the two cases 4.5.5 needs, `locked` and `available`; package 5 owns what happens once a lesson is opened, and naming those states here would be package 4 guessing at package 5's lifecycle.

Rejected: denormalising title, objective and slug onto the row — convenient for a lesson list, but the row and the JSON then drift, and a regenerated syllabus has two places to fix.
Rejected: denormalising the whole lesson JSON — model-facing material in relational columns, against D7 outright.

Revisit if a lesson list needs to sort or paginate by title. Same shape as D7d's deferred generated column, and the same answer for now.

## D11. Plan is a string on `users`; the plans table is deferred with a named trigger (resolved 2026-08-25, package 4 session, Colin)

`users.plan`, a string, defaulting to one configured value. 4.5.6's depth map stays config-keyed in `config/courses.php`, as the spec has it.

The column ships with one value and no price, entitlement or branch attached — the pattern D6 already used for teaching style. Billing remains a v1 WONT.

**Where Colin's instinct beats the D7b precedent.** D7b deferred `course_status_types` on the reasoning that a status needs code — a screen, a button, an arrow into it — before a row means anything. That argument does not transfer: a status's meaning *is* its arrows, which live in code and cannot move into a row, whereas a plan's meaning is its attributes — price, limits, entitlements — which are exactly what a row holds. Plans are a better lookup-table candidate than statuses ever were.

**Why not yet.** Every attribute that would justify the table today is a billing attribute, and billing is a v1 WONT. The table would ship with one row whose only meaningful column is the string `users.plan` already holds, and would charge every user factory in packages 5, 6 and 7 for a seed with no consumer.

**The trigger is named, unlike D7b's.** The foreign-key conversion happens the moment a plan carries its first real attribute — a price, a quota, anything a row can hold that a config key cannot. This is deferred on schedule, not on principle. A future session must read it that way.

Rejected: a `Plan` enum with one case (my proposal) — right about avoiding a bare string, wrong about the eventual shape, since an enum cannot grow attributes and a plan will.
Rejected: a `plans` table holding the depth parameters — gives the table real content in v1, but contradicts 4.5.6's config-keyed depth map, which would have to be reopened as its own decision.

Riders:
- **The default plan is written down three times**, and that is the design rather than an oversight: `config/courses.php` is the source of truth, the migration needs a schema default baked in at migrate time, and the model needs `$attributes` because a freshly created Eloquent model never reads back what Postgres filled in — without it the first caller to ask a new student for their plan gets null. `CoursesConfigTest` pins all three together.
- **An unknown plan surfaces at depth resolution and nowhere else**, because that is the only thing in v1 that reads the column. The check lives where the value is consumed rather than at every site that passes it along.
- **The trigger is narrower than "a plan attribute appears" — sharpened 2026-08-25, same session.** The closing observation of the 4.5 build was that `courses.max_syllabus_revisions` is the obvious first thing a paid plan would vary, so the table would arrive well before billing. That is wrong, and the way it is wrong is what the trigger actually is. Varying the revision cap by plan needs no table and no column: it needs the key rewritten as `max_syllabus_revisions.{plan}`, exactly the shape `depth.{skill_level}.{plan}` already has. Config keying absorbs an arbitrary number of per-plan *constants*, so none of them is the trigger.

  What config cannot do is hold plan data that changes without a deploy, or that another table needs to point at. Concretely, the table becomes necessary at the first of these:
  - a plan attribute someone edits at runtime rather than in a commit — a Stripe price id, a promotional quota;
  - anything that needs a foreign key *to* a plan — a subscription row, a per-plan usage report, an audit of who was on what and when;
  - a plan whose membership is not a static list — trials, grandfathered tiers, per-user overrides.

  Until one of those lands, adding a plan is a config edit and `users.plan` stays a string. Note that the third condition is the one most likely to arrive first and the least likely to be recognised: a single grandfathered user is enough to break a config list, and the temptation will be to special-case it in code rather than admit the list became data.

## D12. The quiz tables land in package 5; package 6.1 lands the service over them (resolved 2026-08-25, package 5 session, Colin)

Spec 6.1 owns "polymorphic `quizzes` + `quiz_attempts` serving all four types through one service". Spec 5.2.2 and 5.2.4 name both tables in **package 5's own** done-conditions — a checkpoint is a quiz, and grading one writes an attempt — so the lesson engine could not reach a done-condition without them. The dependency graph already drew `6.1 → 5` for exactly this reason, and 6.1 had not landed.

**Decision: package 5 lands the schema half; package 6.1 lands the service half.** Both tables ship polymorphic and four-kind from the start, with `App\Assessment\QuizKind`, `QuestionSchema` and the `json/quiz.v1` schema class. Package 5 writes two kinds — `checkpoint` (5.2.1) and `homework` (5.4.1's chain) — and leaves `final` and `refresher` to 6.3 and 6.4.

Rejected: narrow `checkpoints`/`checkpoint_answers` tables now, generalized by 6.1 later. A cleaner package boundary on paper, but it buys that boundary with a rewrite of 5.2.4's endpoint and its tests in the package 6 session, and the four kinds genuinely differ only in what they hang off.
Rejected: stopping package 5 to run a 6.1-only session first. Strictly correct per the dependency graph and a whole session's cost for a schema that has one honest shape either way.

Riders:
- **The four kinds are named in the enum now, though package 5 writes two.** A half-listed vocabulary invites a second one.
- **What a subtopic-grain refresher hangs off is deliberately not decided.** It is the one grain with no obvious row, and guessing at it here would be package 5 designing package 6 — the mistake D10.2 exists to refuse. `quizzable` is a morph; 6.4 decides what goes in it.
- **`quizzes_one_per_scope` is an expression index, not a plain unique.** `section_index` is null for every kind but a checkpoint, and Postgres treats nulls as distinct, so a plain unique would let one lesson accumulate homework quizzes without limit. `COALESCE(section_index, -1)` needs no Postgres version floor, and −1 cannot collide because sections are numbered from one.
- **The answer key lives in `payload` and never leaves the server unbidden.** 5.2.3's stripping is a method on the value object that reads the column, not a flag on it, so there is no argument anyone can get wrong.

## D13. `wk.update`'s erosion guard reports and does not block (resolved 2026-08-25, package 5 session)

Spec 5.4.4 asks for "a regression test asserts every prior concept slug survives". A regression test against a *faked* model asserts nothing about the model, so the guard was built as code — `ErosionGuard` diffs the concept vocabulary of every course in the subtopic across the update — and the three-lesson fixture became a regression test of the guard.

**It logs and lets the write through.** Spec 4.5.1's rule points this way: `memory_versions` still holds the previous document, so erosion is diffable and recoverable, while failing closed would park a completed lesson's pipeline over a document that is merely worse. Blocking would trade a recoverable problem for an unrecoverable one.

Rider: the vocabulary spans the subtopic, not the course. WK accumulates across skill levels (D8), so a 201 course's update can erase what a 101 course recorded, and a course-scoped guard would not see it.

## D14. A Q&A turn gets its own inventory row (resolved 2026-08-25, package 5 session, Colin)

Ledger D5 makes free Q&A turns part of the lesson lifecycle and 5.2.5 names `qa` as a turn kind, but the 2.3.2 inventory had no prompt row for one. The package 5 build shipped Q&A billing under `lesson.section`'s key with a `lesson.qa` feature label, and flagged it rather than diverging. Colin's call: it gets its own row.

**The case is cost attribution, not tidiness.** Q&A volume is the least predictable driver in a lesson's unit economics — a student who asks ten questions per section costs very differently from one who asks none, and every one of those turns bills at the session tier against a growing transcript. Sharing `lesson.section`'s key merged teaching and questioning into one `usage_events` bucket, so the number most worth knowing before the dogfood cycle was the one number that could not be read.

**The row alone would not have been enough.** `PendingCall::userTurn()` displaces a prompt's body with the caller's text and `instructions()` displaces it again, so a `lesson.qa` file called that way would have had a body that never reached a provider — a label wearing a prompt's clothes, which breaks "prompts are files in `prompts/`" more quietly than the wrong key did. So the lesson path came off `userTurn()`: `lesson.qa`'s body is `{{ question }}` and is rendered as the outgoing message, exactly as `lesson.section`'s body is rendered as "Teach section N: …". Same bytes on the wire, live template, honest key.

Rejected: a `lesson.qa` body carrying framing ("the student has interrupted; answer and return to teaching"). `lesson.system` already says that, and per-turn repetition of a cached instruction is cost with no content. The file is the place to put such framing *if tuning ever wants it*, which is the argument for the file, not for the framing.

Riders:
- **`tier: session`, and not merely for consistency.** Anthropic's cache is keyed per model, so a Q&A turn at any other tier would miss the whole `[persona | CRITERIA | WK | GUIDANCE]` prefix and pay to rewrite it mid-lesson.
- **D9's rider survives in substance, not in mechanism.** It reads "`lesson.system` as instructions, the student's message as the turn" — still exactly true — but it was written expecting `userTurn()`. A future session reading D9 should not go looking for `userTurn()` in the lesson path.
- **`PendingCall::userTurn()` now has one caller: interviews.** D9 introduced it as the general answer to "a conversational prompt is a brief, not a turn", and it is turning out to be interview-specific. Worth watching before package 6 builds a third conversation on it; not worth moving today.
- **The change surfaced a live defect.** The old call passed the whole transcript as history *and* the question as the turn, so the student's words reached the model twice in two consecutive user turns. `LessonTranscript::messagesPreceding()` now stops the history short of the turn being answered — the same shape `InterviewSession::ask()` has always used. `LessonTranscript::messagesBefore()` was renamed `messagesBeforeSection()` in passing: its docblock claimed "up to and including" while the query excluded, and one of the two had to be wrong. That the interview path had this right all along and the lesson path did not is the observation [D15](#d15-the-two-transcripts-share-a-rule-not-a-base-class-resolved-2026-08-25-package-5-session) is about.

## D15. The two transcripts share a rule, not a base class (resolved 2026-08-25, package 5 session)

D14's defect is the reason this is written down. `InterviewSession::ask()` pops the newest turn off the history and sends it as the message; `LessonSession::answerQuestion()` sent the newest turn *and* left it in the history, so the student's words reached the model twice. One rule, two implementations, one of them wrong for as long as it existed. The obvious reading is that D9 split too much and the two transcripts want a common base — **that reading is wrong, and this entry exists so a future session does not act on it.**

**What D9 actually split, and what it did not.** D9 split the *tables*, on retention: interview turns are garbage the moment their document is written, lesson turns are what 5.3 resumes from. That argument is about storage and remains completely correct. It says nothing about the classes, and the package 5 session extended the split to them by default — a second `Speaker` enum, a second transcript class, a second copy of the turn-assembly rule.

**Most of that duplication is load-bearing.** `Transcript` has `discard()` and `LessonTranscript` must never have it. They are scoped by different keys (course + interview kind vs lesson). A lesson turn carries `turn_id`, `section_index` and `kind`; an interview turn carries none of those, because nothing replays an interview. A shared base would have to drag retention and scoping into itself to share the twenty lines that are genuinely common.

**And the population is two, by design — not two so far.** D4 makes the lesson session the only real agent; interviews are the one bounded conversation beside it and everything else in the system is a scripted call. 5.5's explainer is stateless by contract, so it has no transcript to share. Unless v1.1 adds a conversation, there is no third case coming, and two is not a pattern.

**So the duplication is not the root cause, and de-duplicating is not the fix.** The bug was not caused by there being two classes; it was caused by nothing asserting that the outgoing message is absent from the history it travels with. A test would have caught it under either architecture, and only a test propagates to a third conversation that does not exist yet.

**What to do instead, when someone has ten minutes:** one test that walks every conversational call site and asserts the newest turn appears exactly once — as the message, not also in `messages()`. Not written this session; Colin closed the session at the note. It belongs with whichever package next touches a conversation.

**The trigger for revisiting the split itself:** a third conversation. If one arrives — a v1.1 notes panel, a tutor-initiated check-in, anything with a kept transcript and a turn loop — the rule has been copied twice and the abstraction has earned itself. Until then, copying twenty lines is cheaper than a base class that has to know about retention.

Rejected: extracting a shared `Speaker` enum now. It encodes a fact about the provider (student speaks as user, tutor as assistant) rather than anything either package owns, which is a real argument for sharing it — but it would re-couple the two packages at exactly the seam D9 split, to save twelve lines that have never changed.

## D16. The explainer's context is server state; the client sends only the highlight (resolved 2026-08-28, package 5 session)

`explainer.select` declares four variables — `selection`, `surrounding_chunk`, `subtopic`, `skill_level` — and the obvious reading of a side-tab is that the client, which owns the selection, sends the passage around it too. **It does not.** The request names a `lesson_messages` row and quotes the highlight; the server reads the passage out of that row.

**The case is the bill, not tidiness.** 5.5 is the one endpoint in the product that spends tokens without advancing anything: no section is taught, no progress moves, no row is written. A client-supplied `surrounding_chunk` would have made it an authenticated general-purpose completion — a few kilobytes of arbitrary text per request, on our account, indistinguishable in `usage_events` from honest use. Reading the passage server-side caps the client-authored text in the whole call at one 500-character highlight, and `throttle:20,1` caps the rate. Both bounds are cheap; neither is recoverable after launch if the endpoint ships open.

**The passage is tutor text only.** A student's own Q&A turn is in the same transcript, and pointing at one would have re-opened the same hole through the back door — the student writes that row's content. So the row must be `speaker: tutor`. The cost of the rule is that a student cannot highlight their own question, which is not a thing anyone wants to do.

**A highlight that is not in the source widens rather than failing.** Highlights are made against *rendered* markdown, so one spanning a `**` or a link never appears verbatim in what we stored. Normalising both sides and mapping offsets back is fragile and wrong in a way nothing would notice; a hard 422 makes the signature feature fail on the student's ordinary behaviour. So a miss sends the whole passage instead of a window. The only cost of a miss is tokens, and the model always has real context.

**500 characters, confirmed rather than assumed** (Colin, 2026-08-28). The number was challenged inside the same session: a whole section is jargon-dense and example-free often enough that highlighting one end to end sounded like the commonest real use, and the cap refuses that with a validation error on the product's signature feature. The alternative — bounding the highlight by the length of the passage it came from, which admits a section while still keeping the client from authoring text we pay to read — was built and then reverted. Colin's call: the whole-section case was hyperbole, and 500 is the right place to start. It stands as a chosen number with a known cost, not a default.

**The trigger for revisiting:** students hitting it, which `SelectionExplanationController` now logs at `info` (Colin, 2026-08-28) — lesson, user, the length they tried, the cap. A number chosen without evidence deserves at least the evidence needed to change it, and grepping one message beats an opinion about whether 500 is too small. The refusal itself is written for the student — *"That is more than the side-tab explains at once. Highlight a shorter passage and try again."* — because the client renders it verbatim and Laravel's default names a form field and a character count, neither of which is a fact about their lesson.

**A refusal is the fallback, not the mechanism** (Colin, 2026-08-28). Being told no *after* highlighting the passage, pressing the button and waiting is a bad way to learn a limit, and a button that silently stops working is worse. So the limits travel in the lesson payload — `limits.highlight_characters` and `limits.question_characters`, each read from the class that enforces it — and the screen can say what is happening while the selection is still being dragged. The server-side refusal stays, because a limit enforced only in a browser is not enforced; it just stops being how a student normally meets it. Package 7 owns the wording and the moment. A test drives each boundary from the *published* number, so a published limit that disagreed with the enforced one fails rather than making the screen confidently wrong.

Rejected: taking character offsets from the client and slicing the source with them. Same trust problem in a different coordinate system — offsets are as forgeable as text, and they still do not survive the markdown/rendered mismatch that motivated them.

**The trigger for revisiting:** a student wanting to highlight something that is not a transcript row — a checkpoint option, a homework question, a syllabus line. None of those are in `lesson_messages`, and each would need its own passage source rather than a general "trust the client" escape.

## D17. 5.6 is not strictly front-end; it splits at the first frame (resolved 2026-08-28, package 5 session)

5.6 reads "first token <3s perceived; mobile-usable", with a manual phone check as its done-condition, and the session opened with a fair question: is this simply package 7's? **No — it has a server half, and the seam is the first frame on the wire.**

**What the server owns.** Everything between the request arriving and the first byte leaving is our latency, and none of it is the provider's: session auth, the policy check, the openability pre-flight, the section lookup, the `in_progress` write. After that comes the part we do not control — assembling `[persona | CRITERIA | WK | GUIDANCE]` and waiting on the provider's first token. The engine already puts `turn.start` ahead of that boundary, so a client has a section title and an "N of M" to paint immediately; nothing pinned it there. And R4's "the host must not buffer SSE" is enforced by three headers that no test read. Both are now regression-tested in `StreamingFloorTest`.

**What package 7 owns.** How fast the first token *feels* — a skeleton, a caret, delta paint cadence — and whether the screen is usable one-handed on a phone. That is rendering, and no amount of it can recover a budget the server has already spent.

**What neither owns yet.** The done-condition itself. A phone on LTE is the only instrument that settles "perceived", and there is no device in this session. The engine tests stand in; they do not close the item, and 5.6 stays open until the manual check runs.

**One residual risk, recorded rather than fixed.** Between `turn.start` and the provider's first streamed event the connection is silent, and `heartbeatIfDue()` cannot fire during it — PHP is blocked, and there is no concurrency to fire from. If a provider ever took longer than 15s to its first event, 5.2.6's heartbeat interval would be breached on that one window. In practice Anthropic emits `message_start` almost immediately, so the window is short. `EventStream`'s docblock claims heartbeats happen "around the calls that can stall … the wait for the first token" — read that as *the clock is reset immediately before each stall*, which is the best a synchronous transport can do, not as a beat during one.

## D18. What the quiz service owns, and what `quizzable` holds at each grain (resolved 2026-08-28, package 6 session)

D12 split spec 6.1 in two and left three questions for this session. All three are answered here.

**The service is the only writer.** `App\Assessment\Quizzes` creates every quiz and `App\Assessment\QuizGrader` writes every attempt — the rule MemoryTree holds for memory and CourseTransitions holds for `courses.status`. Package 5's `CheckpointGenerator` and `GenerateHomework` each carried their own copy of the create-or-adopt race; both now call the service, and `CheckpointGrader` is gone. Colin approved editing package 5's call sites for this: leaving them alone would have satisfied "do not modify other packages" while making 6.1's done-condition false by inspection.

Consolidating found a defect in that duplicated race. A losing insert aborts the surrounding Postgres transaction, so the adopting SELECT that follows fails with "current transaction is aborted" and the loser raises at the student after all — the exact outcome the adopt was written to prevent. It only bites when the caller has opened a transaction, which is why the test suite (`RefreshDatabase`) found it and production had not. The service inserts inside a savepoint.

**`quizzes_one_per_scope` excludes refreshers.** The index was written for the three kinds generated once and re-served. A refresher is the opposite: coming back to the same lesson next month is the point, and re-serving last month's questions is the "system has lost track of me" failure the prompt is written to avoid. A partial index (`WHERE kind <> 'refresher'`) rather than a new column, because the kind already says whether the scope is exclusive. The predicate mirrors `QuizKind::isRepeatable()`, and `QuizServiceTest` reads `pg_indexes` to hold the two together.

**What each refresher grain hangs off**, the question D12 explicitly deferred:

| Grain | `quizzable` | `course_id` |
|---|---|---|
| Lesson (6.4) | the `Lesson` | its course |
| Subtopic (6.4) | the newest `Course` in the subtopic | that course |
| Module (6.5) | the `Module` | **null** |

Rejected for the subtopic grain: a `subtopics` row. A catalog row is a suggestion and not identity — a subtopic typed freehand has none at all (4.1) — so half of every student's subtopics would have nothing to hang from. Rejected: the `memories` row holding the subtopic's WORKING_KNOWLEDGE.md. It is the most accurate answer available and it is forbidden: a morph to `memories.id` is memory access outside the path API (3.2).

`quizzes.course_id` becomes nullable for the module grain. The column means "the course whose syllabus declares the vocabulary these tags resolve against", and a module quiz spans every subtopic the rollup points at; naming one of them would make the column say something untrue rather than say nothing. Null means "more than one course", never "unknown".

## D19. Homework reaches working knowledge as its own prompt variable, cumulatively (resolved 2026-08-28, package 6 session, Colin)

6.2 says the homework result "feeds next WK update", and it cannot reach it through the summary: `lesson.summarize` runs at chain step one and `homework.generate` at step three, so lesson N's summary is finished before lesson N's homework exists. **`wk.update` gains a third input, `homework_results`** (2.3.2 updated, `prompts/wk/update.md` and `UpdateWorkingKnowledge` with it), rendered by code the way `checkpoint_results` is — the model interprets grades and never recomputes them.

Rejected: firing a standalone `wk.update` when homework is submitted. No inventory change, but it doubles WK write frequency and cost, and "feeds next WK update" reads as feeding a call that already happens rather than adding one.

**The variable is the student's whole homework record for the subtopic, not a delta.** The delta version has to decide whether an attempt predates the last WK update, and the only available comparison is `quiz_attempts.created_at` against `lesson_pipeline_steps.completed_at` — two clocks owned by different subsystems, written milliseconds apart, into `timestamp(0)` columns. Ordering them is a coin flip that either drops a student's homework or double-counts it. Widening both columns was tried and abandoned: Laravel's model date format truncates to the second regardless, so the fix would have been a migration *and* a `$dateFormat` override on two package-5 models, all to buy a delta that was never worth its own failure mode.

The record is ordered structurally instead — course id, then lesson position, which is a total order because only one course per subtopic may be open at a time (D7a) — and the prompt is told it is a record rather than news. Late homework is picked up whenever it is finally done, and a full-document replacement re-reading evidence it has already absorbed changes nothing. The lesson's own homework is excluded: at step two it does not exist, and after a retry it exists unanswered, so reporting it would put "not answered" against every question of a quiz the student has not been shown.

Rider: `AnsweredQuestions` now holds the first-attempt rule both `checkpoint_results` and `homework_results` depend on. Two copies of "the first attempt is the one that counts" is exactly the rule that drifts.

## D20. An exam measures; it does not teach (resolved 2026-08-28, package 6 session, Colin)

`quizzes_one_per_scope` holds a course to one final exam, so a re-sit after a failure is the same exam. That only measures anything if the first sitting did not hand back the answers.

**A final exam never discloses `correct_option_id` or `explanation`** (`QuizKind::disclosesAnswerKey`), and it is sat as a whole rather than a question at a time — passing is a property of the set, and a per-question endpoint would hand out the answers mid-exam. What a sitting returns is the score and the concepts missed, which is precisely the signal D7b named when it rejected a `failed` end state: "the `quiz_attempts` row 6.1/6.3 already writes, which names the concepts missed rather than only the verdict."

Rejected: full disclosure like a checkpoint (5.2.4). One grading path and simpler, but then the re-sit is passable from memory of the explanations and the gate stops measuring. Rejected: regenerating a fresh exam per sitting. It fixes the memorisation hole properly, but needs the index relaxed for `final` too and spends a `top`-tier generation per attempt.

Two riders:

- **"Scoped by `final_exam_concepts`" is checked in code**, not hoped for in the prompt: every concept the syllabus declared for the exam has to be reachable through some question, or the exam is regenerated once and then refused. Refused means the course has not moved and nothing was written (D10.4) — the exam fails *closed*, unlike a checkpoint, because nothing the student did is lost and failing open would put the meaning of the gate itself in doubt. A refresher fails closed too, and even more cheaply: it gates nothing.
- **A sitting is all-or-nothing.** Every answer is validated before any is written, so a malformed answer halfway down a submission cannot leave a half-recorded sitting that the student's real attempt then has to be numbered around.
- **The exam has a bounded length, because the pass mark is a fraction** (added 2026-08-30, Colin). `json/quiz.v1` asked for at least one question and set no maximum, and the `exam.final` prompt named no number, so the model chose the exam's length freely — and with it what `pass_mark: 0.7` actually demanded: 7 of 10 (70%) at one length, 3 of 3 (100%) at another. `QuizLength` states the count per purpose (exam 8–12, homework 3–6, refresher 3–5), which narrows the exam's effective threshold to 70–78%; the residual spread is inherent to counting whole questions. The exam's length is re-checked in code as well as in the schema, because it is the one quiz whose length changes the meaning of a configured number — the two kinds that gate nothing are left to the schema alone, since failing a homework generation over a question count would park a completed lesson's pipeline for a cosmetic reason (5.4.5). The prompts state the same range as the schema, and `QuizLengthTest` holds the two texts together the way 5.3's `limits` block holds published and enforced limits together.

## D21. The visual direction: New England prep school, light-first (resolved 2026-08-31, package 8 design session, Colin)

The front end was untouched starter-kit boilerplate — `resources/css/app.css` shipped every colour at `oklch(… 0 0)`, chroma exactly zero, and only `pages/terms.tsx` had ever been authored. The brief was "sharp and intuitive, not a clunky afterthought", judged by reaction to candidates rather than by specification.

**Canvas: https://claude.ai/code/artifact/5d5d7052-86b8-4230-8c2a-606cba1b6f46** — source in [`docs/design/canvas/`](design/canvas/).

**Chosen: direction A, "Chalkboard", recast light-first, on a hunter green + burgundy + brass palette.** Anchor is the prep-school register of *Dead Poets Society*, *School Ties*, *The Holdovers* and *Scent of a Woman* — the last supplying a formality the other three lack. Anatomy is rail (rail green, chrome, recedes) / paper column / explainer (field green, content, comes forward), the two green fields separated by value so they read as roles rather than as a matched pair.

Type is Newsreader at 17px/1.62 for body, Instrument Serif for display, Caveat for the tutor's hand. Measure caps at 72ch, which is where three independently-measured products converge — Claude.ai 75ch, iA 74ch, Tailwind Docs 76ch — and narrows to ~44ch at 390px with no font-size rule, which is Claude.ai's own strategy (`max-width` plus fixed padding) rather than iA's fluid `calc()`.

Rejected: **B "Foolscap"** (EB Garamond on warm cream, the course as a printed object) and **D "Assembly Hall"** (Spectral and Cormorant on ivory, engraved formality) — both ranked below A, and both folded into it as the burgundy and brass. Rejected: **C "Field House"** (navy and crimson, cool and institutional) — it scored best on forms and worst on the anchor, and could not absorb the warmth the fourth film added without ceasing to be itself. Rejected: **dark-first**, which was A's original form; light-first is a standing constraint, and A's dark mode would reconstruct it anyway.

Also rejected, after being drawn: the repp stripe (noisy), varsity numerals (did not read as varsity), a ribbon bookmark (noisy), and a stamped completion seal. Kept: colour fields, the drop cap, the crest, and a handwritten margin annotation in the tutor's voice.

Rider (added 2026-08-31, Colin): **the measure is 72 *characters*, not 72 CSS `ch`.** The three products were measured in sans-serifs, where the digit `0` and the average character are close to the same width; Newsreader's digits are 0.566em against an average character of 0.407em, so taking `ch` literally would have set a 693px line of ~100 characters and made the phone number arithmetically unreachable (44 `ch` is 424px at 390px). The column is `--measure: 29.3em` plus a fixed 20px gutter — ~72 characters at 1440px, ~50 at 390px, one rule at both widths and no font-size rule. Spec 8.3.3 and 8.2.3 carry the amended numbers.

Rider (recorded 2026-09-02): **the design phase was to be judged on a real generated course, and was judged on written substitutes instead** (spec 8.11.4). No course had ever been generated when the artboards were drawn — the engines for packages 4–6 all landed engine-only — so the twenty artboards carry Spanish teaching prose written to the schemas the engines emit, and `InterfaceFixtureSeeder` writes that same prose through the classes that own each column rather than through the models (8.2.9 WONT). The condition is dropped as **met by substitute**: the content is schema-faithful, not engine-produced, which is enough to test a layout and not enough to test it against real output. **Falsification moves to the dogfood cycle**: if a real generated course breaks a layout these mockups predicted would hold — a lesson section longer than any drawn, an exam question that will not sit on the 30px rhythm, an objective that wraps past the rail — the substitution was wrong and the layout owes a fix.

Rider: the annotation is the one place the tutor speaks *as a person* rather than as prose, and it is keyed to what the student said in their interview. It is the cheapest personality in the whole design and the closest to twee — watch it in the dogfood cycle.

## D22. Material carries meaning; assessment is not more lesson (resolved 2026-08-31, package 8 design session, Colin)

Every candidate direction was weakest on the same surface — the graded form. Five of the twelve screens are quizzes, none of the five reference products measured had one, and a quiz rendered on the reading ground reads as more lesson.

**Three grounds, each bound to a purpose.** The paper column is reading. A photocopied 1990s classroom quiz sheet — Courier Prime, a name/date block, a `DIRECTIONS:` line, the sheet a third of a degree off square — is *all* practice: homework and all three refresher grains. A blue book is the final exam: cover, then stapled interior on a 30px rhythm with type sitting on the rules and a red vertical margin.

The blue book states D20's rule as an object rather than as a warning dialog: the cover says the book is collected whole and marked once, nothing disclosed until it is handed in, and a re-sit is the same examination. The exam opens closed and is clicked open — the one deliberately animated moment in the app, on the highest-stakes screen.

Rejected for practice: a **yellow legal pad**, which was the more charming of the two and lost on two counts — yellow sits near-opposite hunter green, and ruled lines fight option lists. It is not discarded: it is the material for the v1.1 notes feature, which PLAN.md already defers. Rejected: leaving quizzes on the paper card, which is what made them read as corporate training in the first place.

## D23. The in-lesson checkpoint is a margin question (resolved 2026-08-31, package 8 design session, Colin)

A checkpoint interrupts streamed prose mid-column, and the container that makes it unmissable is the same container that makes it feel like a form.

**Chosen: the tutor interrupts from the margin** — the question posed in the hand, options inline under a brass rule, no card. It is the lightest interruption of the four drawn and it reuses D21's annotation rather than introducing a fifth object.

**At ≤767px there is no margin, so the note moves into the column as the question's lead-in line**, still in the hand, still rotated, brass rule beside the options only. Rejected phone reductions: a tinted full-bleed band (safest, least distinctive — it reintroduces the card the desktop version removed) and running the brass rule the full height as the container (faithful, but the note stops reading as marginal once it is inside the rule).

Rejected treatments: the **paper card** (reliable and boring — it was the fallback), a **torn slip** of the homework stock (consistent with D22, but a torn edge mid-prose is a bigger interruption than a checkpoint deserves; it stays with homework), and an **index card** (most charming, wears thin at four checkpoints a lesson; parked for a later use).

## D24. Radix and the copied shadcn primitives stay (resolved 2026-08-31, package 8 design session, Colin)

The question was fair: `components/ui/` and `app.css` are untouched Laravel starter-kit boilerplate — nothing in them was written for this project — so why keep them at all? The decision was deferred until a direction existed, against one test: does the direction mean **editing** the primitives or **fighting** them.

**Editing.** Every one of the twenty artboards expressed itself as tokens, borders, type and colour fields — precisely the axes the copied files expose. The one element that looked like it might need different anatomy, the phone explainer sheet, is served by the existing `ui/sheet.tsx`, which wraps Radix Dialog and brings focus trapping and escape handling with it.

Rejected: a clean-slate front end. It would delete no work of ours (only `pages/terms.tsx` was authored here), but it means rewriting accessible behaviour — focus trap, keyboard navigation, ARIA wiring — plus login, register, 2FA and passkey screens that already work, in the last package before the deadline, and none of that rebuild makes the product look better.

Rider: shadcn is a generator, not a dependency. Those 25 files are ours to edit or delete file by file, so this decision commits us to Radix (14 packages) and to nothing else. Falsified if a settled screen ever needs a primitive's anatomy replaced rather than restyled.
