# Harkness Academy — Design Decision Ledger

*Recorded 2026-08-21. Each decision: chosen candidate, rejected candidates, deciding rationale.*

## Index

*Every entry, its subject, and whether it still binds. A superseded entry stays below at full length with a marker on its first line; a reader who wants only the current rule reads the entry named in the Status column.*

| # | Subject | Status |
|---|---|---|
| D1 | Model sourcing — Anthropic-direct behind a provider abstraction | current |
| D2 | Frontend — Inertia + React | current |
| D3a | Memory storage — a virtual file tree in Postgres | current |
| D3b | Memory writes — workflow-initiated only | current |
| D4 | Agent topology — one real agent, everything else scripted | current |
| D5 | Lesson lifecycle — code-paced sections | current |
| D6 | The v1 cut — what ships and what waits | current |
| D7 | Data model — the approved tables | current |
| D7a | Course exclusivity and exit — one open course per subtopic; exit deletes the row | **superseded by D7d** (exit-deletes first amended by D7b) |
| D7b | Withdrawal replaces deletion; attempts replace lockout | **superseded by D7d** |
| D7c | Both endings are dates, not statuses | **superseded by D7d** |
| D7d | One status vocabulary; `withdrawn` and `completed` are derived | current |
| D8 | WORKING_KNOWLEDGE scope — subtopic, not level | current |
| D9 | How a conversation runs, and where its transcript lives | current |
| R1 | Personas — repo prompt files, `courses.style` holds the key | current |
| R2 | PWA scope — manifest, icons, install page; no offline | current |
| R3 | Interview termination — `signal_complete` tool plus an always-visible Done | current |
| R4 | Hosting — Laravel Cloud; the host must not buffer SSE | current |
| W1 | Planning docs live in-repo under `docs/`, committed | current |
| W2 | Build workflow — per-package specs, worktree lanes late | **amended by D27** — PLAN.md is no longer the tracker half |
| W3 | Default branch — `master` | current |
| W4 | Branching — hybrid trunk-based | current |
| W5 | Session start — the `/package <n>` command | **amended by spec 9.6.2** — `/unit` is its companion for cross-cutting work |
| D10 | The syllabus is rows, and four smaller things 4.5 forced | current |
| D10.1 | Syllabus drafts are their own table, pinned to the inputs they saw | current |
| D10.2 | Concept slugs stay in the JSON; there is no concepts table | current |
| D10.3 | GUIDANCE.md renders from a PHP class, not a Blade template | current |
| D10.4 | A failed generation writes nothing and moves nothing | current |
| D10.5 | `lessons` carries exactly what D7 listed | current |
| D11 | Plan is a string on `users`; the plans table is deferred | current |
| D12 | The quiz tables land in package 5; 6.1 lands the service over them | current |
| D13 | `wk.update`'s erosion guard reports and does not block | current |
| D14 | A Q&A turn gets its own inventory row | current |
| D15 | The two transcripts share a rule, not a base class | current |
| D16 | The explainer's context is server state; the client sends the highlight | current |
| D17 | 5.6 is not strictly front-end; it splits at the first frame | current |
| D18 | What the quiz service owns, and what `quizzable` holds at each grain | current |
| D19 | Homework reaches working knowledge as its own prompt variable | current |
| D20 | An exam measures; it does not teach | current |
| D21 | The visual direction — New England prep school, light-first | current |
| D22 | Material carries meaning; assessment is not more lesson | current |
| D23 | The in-lesson checkpoint is a margin question | current |
| D24 | Radix and the copied shadcn primitives stay | current |
| D25 | A `top`-tier generation is queued work, not request work | current |
| D26 | A half-finished exam is a saved answer sheet | current |
| D27 | Documents are tiered by lifetime, and PLAN.md is scheduled for retirement | current |
| D28 | The mounted-component defect is a class, and is fixed at the boundary | **amended 2026-09-08** — the class is narrower than stated, and its fourth instance is not one |
| D29 | An acceptance test lives in a spec, one per package | current |
| D30 | Three shells, not one, and the two things all of them share | current |
| D31 | The resume table is code — `NextAction` — and a refusal speaks twice | current |
| D32 | The worksheet has one disclosure path, and choosing an option is the hand-in | current |
| D33 | The exam screen's three states are decided on the server | current |
| D34 | `final_exam_concepts` is emphasis, not scope, and keeps its name | current |
| D35 | Client-side behaviour is proven in jsdom, not in a browser | current |
| D36 | The interview opener is a wire artefact, not a turn | current |
| D37 | The question cap is not a third termination door | **superseded by D38** — a cap that ends the interview interrupts the student |
| D38 | The question budget asks for an ending and never imposes one | current |
| D39 | Over-escaped model prose is decoded where the syllabus is accepted | current |
| D40 | Where the correct option sits is the writer's, and it is dealt out rather than shuffled | current |
| D41 | A validation error names every rule the field broke, and the joining is Inertia's boundary | current |
| D42 | A free-text subtopic is checked against its module before a course exists | current |
| D43 | The password rule is eight characters, in every environment | current |
| D44 | The name, the motto and the monogram — Harkness Academy, *Licet Facere*, HA | **amended by D46** — the band beneath the shield was drawn and rejected |
| D45 | Entry is one shared invitation code, held in config | current |
| D46 | The wordmark stacks, the motto is engraved, and the front door gets its own ground | current |
| D47 | An answer the tutor never replied to is replaced by the next one | current |
| D48 | A checkpoint's wait runs out, and the course's request is read as the newest section's | current |
| D49 | Done's write-up goes to the queue with the tutor's | current |
| D50 | The demo is the real pipeline on a shorter plan | **amended 2026-09-16 and 2026-09-17** — a welcome page with one button; the demo is lesson 1 of an ordinary course |
| D51 | `plan` keys depth, and is not yet a table | current |

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

> **Superseded — the current rule is D7d.** Exit-deletes went first (D7b), then the terminal status (D7c), then the vocabulary itself (D7d). What survives unamended is the exclusivity invariant and the riders below; read D7d for the standing rule and come here only for why exit ever deleted a row.

Invariant: for each (user, subtopic) at most one course is *open*, where open = any state short of `completed`. Enforced by a partial unique index, not by the 4.2 transition service alone, so concurrent creation cannot slip past it. A completed 101 alongside an open 201 stays legal — only one course writes.
Exit deletes the course row and its lessons and quiz attempts, behind a confirmation ("exiting deletes all progress; you start again from the beginning"). `completed` stays the machine's only terminal state.
Rejected: an `abandoned` status — D7's unique (user, subtopic, skill_level) would keep the tombstone forever, so exiting a level once locks the student out of it for good.
Rationale: forbidding concurrency retires D8's accepted cost outright; deleting rather than tombstoning frees the level again with no loosening of D7's uniqueness.
Riders:
- Memory is not deleted with the course. The subtopic WK is student state (D8) and survives by design — the knowledge is still true whatever happened to the course. The course's `{level}` documents (CRITERIA, GUIDANCE) regenerate at the same paths on restart; orphans appear only where a regenerated syllabus drifts the lesson slugs. Unread garbage, accepted for v1 — the path API (3.2) has no delete.
- **4.3 feeds the existing WK in.** `wk.create` overwrites the subtopic document blind today (2.3.2 inputs: transcript/subtopic/skill_level), which under D8 erases accumulated knowledge whenever a *second* course starts in a subtopic — after an exit, and on every 101 → 201 progression. Settled: the prompt gains `prior_wk_doc`, always supplied — the accumulated document, or an explicit "none yet" for the first course in the subtopic. One inventory row rather than a second near-identical prompt, and 2.3.1 has no optional vars, so an explicit empty value is the shape that fits. Landing it updates the 2.3.2 inputs cell, so it lands with 4.3, not before. 5.4.4's erosion guard does not cover this path — it guards `wk.update` — so 4.3 carries its own regression test.

## D7b. Withdrawal replaces deletion; attempts replace lockout — **amends D7a** (resolved 2026-08-24, package 4 session, Colin; **terminal status superseded by D7c**)

> **Superseded — the current rule is D7d.** Withdrawal-not-deletion and the attempt counter still stand; how the two endings are *stored* was rewritten by D7c and then by D7d.

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

> **Superseded — the current rule is D7d.** The two dates are still the storage; D7d is what decides the vocabulary read back over them.

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

> **Amended by D27.** Points 2 and 4 below describe a PLAN.md that no longer exists: the tracker half is gone, package state lives in each spec's done-conditions, and the end-of-session ritual triages a plan file instead. Points 1, 3, 5 and 6 still bind.

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

  Amended 2026-09-04 (dogfood session, Colin): **this rider was arithmetically unsatisfiable and is replaced by per-lesson coverage (spec 6.3.1).** The incident is [T5](lessons.md); what follows is only why the rider fell. A question carries exactly one `concept_tag`, so an exam can never cover more concepts than it asks questions. Course 1 — the first real generated syllabus — declared **44** exam concepts against a 12-question ceiling, and three consecutive generations were refused at a cost of roughly $0.16–0.24 and 90 seconds each. The rule held only because every test fixture declares three exam concepts. What the exam owes the syllabus is now *every lesson reachable through some question*, checked against `SyllabusConcepts::declared()` rather than against `final_exam_concepts` (6.3.3). `final_exam_concepts` survives as the exam's declared emphasis — a hint the prompt reads, not a gate — which is what spec 8.7.7 corrects the syllabus screen to say.
- **A sitting is all-or-nothing.** Every answer is validated before any is written, so a malformed answer halfway down a submission cannot leave a half-recorded sitting that the student's real attempt then has to be numbered around.
- **The exam has a bounded length, because the pass mark is a fraction** (added 2026-08-30, Colin). `json/quiz.v1` asked for at least one question and set no maximum, and the `exam.final` prompt named no number, so the model chose the exam's length freely — and with it what `pass_mark: 0.7` actually demanded: 7 of 10 (70%) at one length, 3 of 3 (100%) at another. `QuizLength` states the count per purpose (exam 8–12, homework 3–6, refresher 3–5), which narrows the exam's effective threshold to 70–78%; the residual spread is inherent to counting whole questions. The exam's length is re-checked in code as well as in the schema, because it is the one quiz whose length changes the meaning of a configured number — the two kinds that gate nothing are left to the schema alone, since failing a homework generation over a question count would park a completed lesson's pipeline for a cosmetic reason (5.4.5). The prompts state the same range as the schema, and `QuizLengthTest` holds the two texts together the way 5.3's `limits` block holds published and enforced limits together.

  Amended 2026-09-04 (dogfood session, Colin): **the fixed 8–12 range becomes two questions per lesson (spec 6.3.2).** The reasoning above is unchanged and is what the amendment preserves: a bounded length is what stops `pass_mark: 0.7` meaning different things. What the range got wrong was treating a *ceiling* as the protection when the danger was the absent *floor* — 3 questions turned 0.7 into 100%. Two per lesson supplies a floor and narrows the effective threshold rather than widening it: depth parameters allow 5–10 lessons, so 10–20 questions resolve to **70–75%**, against the 70–78% the fixed range produced. `QuizLength::exam()` now takes the lesson count from the caller; `homework()` and `refresher()` are untouched. The prompt states the computed total through a variable rather than in prose, so that "student picks 1–5 per lesson" (v1.1) is a different number rather than an edited prompt file.

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

## D25. A `top`-tier generation is queued work, not request work (resolved 2026-09-02, dogfood session, Colin)

Found in production, on a phone. Pressing "Draw up my syllabus" returned a gateway timeout; pressing it again returned another; a refresh a minute later showed the syllabus sitting there. Laravel Cloud fronts the app with Cloudflare, whose origin timeout is about 100 seconds and is not a setting we get. `config/ai.php` already allows a `top` call 300 seconds — the app was willing to wait three times longer than the edge would let it.

Nothing was lost, and that is the tell: the PHP process kept generating after the browser was told the request had failed, finished, and wrote its row. The work was never the problem. Holding a request open for the length of it was.

**Both `top`-tier generations move onto the queue** — `SyllabusGenerator::first`/`revision` behind `DrawUpSyllabus`, and `FinalExam::open` behind `WriteFinalExam`. The screens they belong to gain a waiting state and reload onto it, which is package 5's shape for exactly this (5.4.5, and S8's `pending` in the 8.6 map) rather than a second invention.

Raising the timeout was rejected even where it was possible. A phone holding a three-minute POST loses it to a screen lock, a backgrounded Safari or a wifi-to-cellular handover, so the request shape is wrong independently of any ceiling. Splitting generation into smaller model calls was rejected too: the syllabus is coherent because it is written in one pass, and 4.5.2's schema check is a whole-document check.

**A row is the wait.** `generation_requests` holds one row per (course, kind), created on the press and deleted when the thing asked for exists — the artefact's own existence is the completion signal, as `quizzes` already is for homework. Two consequences worth naming. The unique index makes a double press free: before this, each 504-and-retry was its own Opus bill, which is what the production incident actually cost. And failure now has to persist, because the generators fail closed and there is no longer a response to say so in; the row survives its own failure carrying the student's sentence, and the next press clears it.

**Refusals that need no model stay in the request.** "All three revisions are used", "two lessons still to finish", "it already has a syllabus" are knowable immediately, and an answer delivered after a two-minute wait is a worse answer to the same question. Both services grew a `refusal()` that runs the same asserts the generators run — `CourseTransitions::refusal()`'s precedent (8.10.3) — so the two cannot drift.

**Infrastructure**: a Laravel Cloud managed queue. Flex is on every plan including Starter, and its 90 seconds is the grace period a worker gets when it must stop, not a cap on job length — so a three-minute generation completes normally and is only at risk when a worker is interrupted, where it is redelivered. Managed queues deliver at least once, so both jobs are idempotent: `FinalExam::open` adopts an exam that already exists, and `DrawUpSyllabus` settles rather than fails when it finds the draft already written. Pro (Growth plan) removes the interruption risk and is the upgrade if redelivery ever costs real money.

Rider: this is also the first thing in production that *needs* a queue worker at all. `CompletionPipeline` has dispatched `ShouldQueue` jobs since package 5 and nothing has ever drained them, so homework and the working-knowledge summaries were queued and silently unprocessed — a landmine that would have gone off at the first completed lesson. `docs/deploy.md` deferred Cloud-side queue enablement to "when package 4 does queue work"; that day had already passed unnoticed.

## D26. A half-finished exam is a saved answer sheet (resolved 2026-09-04, dogfood session, Colin)

Every other quiz in the system saves itself as you go. Choosing an option on a homework, a checkpoint or a refresher POSTs immediately, is graded, and hands back the key and the explanation — the row is in `quiz_attempts` the instant it is clicked. The final exam does none of that, and [D20](#d20-an-exam-measures-it-does-not-teach-resolved-2026-08-28-package-6-session-colin) is why: grading a final one question at a time would disclose the key mid-exam and a re-sit would be passable from memory. `QuizGrader::grade()` throws rather than allow it.

The exam itself was never the thing at risk. It is generated once into `quizzes`, held there by `quizzes_one_per_scope`, re-served on every reopen, and graded all at once at hand-in; a finished sitting survives in `quiz_attempts` and renders a week later. What is lost is only the set of options the student has *clicked and not yet handed in*, which live in React state and die with the tab.

**Saving and disclosing are separable, and only disclosure was ever the constraint.** A sitting in progress is recorded as an answer sheet — one row per (exam, student, sitting) holding question id → option id, replaced on each save, carrying **no verdicts**. Grading is untouched: hand-in validates and records every answer together exactly as it does today, `QuizGrader` stays the single writer of `quiz_attempts`, and `correct` stays `NOT NULL`. The sheet is cleared at hand-in so a re-sit opens an empty book — the same thing 8.9 had to fix once already on the client.

Rejected: **a separate table or model for final exams** (Colin's own question, 2026-09-04). The exam already shares `quizzes`, `quiz_attempts` and one grading service with the other three kinds; splitting it duplicates all three for no new behaviour, and the shared path is what makes one screen and one storage shape serve four quiz types.

Rejected: **browser-only persistence**. Same amount of design thinking, survives a reload but not a device, and the only `localStorage` precedent in the codebase is the starter kit's dead dark-mode hook.

Rejected: **saving ungraded rows into `quiz_attempts`**. It needs `correct` to become nullable, which weakens an invariant every reader of that table relies on, to store something that is not an attempt.

Weight: this is spec 6.3.4 and it is a **SHOULD** — the exam is sittable without it, and it is the one item in the 2026-09-04 repair that can be ejected without leaving a course broken.

## D27. Documents are tiered by lifetime, and PLAN.md is scheduled for retirement (resolved 2026-09-07, planning session, Colin)

**Chosen: tiered files, minimal move (A1), with an explicit retirement path to A2.**

Every document is one of three tiers. **Unconditional** — loaded every session, small, stable, current-only: `CLAUDE.md`. **Durable, addressable** — loaded when targeted, length is fine so long as the unit of retrieval is smaller than the file: `docs/system.md`, `docs/02-design.md`, `docs/specs/*.md`, `docs/lessons.md`. **Disposable** — one work unit, deleted on landing: `docs/plans/<unit>.md`. `README.md` is the human entry point and owns the roadmap.

The repo had no disposable tier, and that is what produced the bloat. Session narrative had nowhere to be retired to, so it accreted into PLAN.md permanently: the Status section was 45,419 of the file's 55,289 bytes, 58.7% of it a chronological work journal stored in table cells, against 2.9% that was actual tracker state. The package-8 cell alone was 25,598 bytes.

**PLAN.md is reduced rather than deleted, and retires once `docs/plans/` has carried three work units start to finish** (the documentation migration is the first). Deleting the tracker and authoring its replacement in one pass is the step here that can lose continuity, and 1.7KB costs almost nothing to keep while the disposable tier proves itself. On retirement the dependency graph moves to `docs/system.md`.

**Rejected — A2, spec-anchored with no central plan.** It is the end state, and 9.7.1 is the path to it; taken immediately it removes the one document that answers "where are we" before its replacement has been used.

**Rejected — A3, one file per decision (ADR style).** It gives the best retrieval granularity, but pays the largest migration cost for a gain the TOC in 9.3.1 mostly delivers, and it fragments `git log docs/02-design.md`, which today shows every decision and every supersession in one history.

**Rejected — A4, an index layer with no restructure.** It reduces nothing: unconditional load stays at 59KB and the journal keeps accreting. Kept only as the cost baseline the others were measured against.

## D28. The mounted-component defect is a class, and is fixed at the boundary (resolved 2026-09-07, planning session, Colin; **amended 2026-09-08, the mounted-component unit**)

> **Amended — the class is narrower, and instance 4 is not one.** Inertia keys the page component on every swap (`key: preserveState ? current.key : Date.now()`), so a visit that does *not* preserve state remounts the screen already. What leaves a component mounted is a **state-preserving** visit: `router.reload()`, which forces it, and `router.post/put/patch/delete`, which default to it. A GET — a plain `<Link>` or `router.visit(url)` — does not. Instances 1–3 stand. Instance 4 does not: both routes to the next lesson are GET links, the lesson screen remounts, and the explainer is already gone with it. The rule the class earned still holds, and is now a standing convention in `CLAUDE.md`.

**An Inertia visit between two URLs that render the same component leaves that component mounted, so `useState` and `useRef` do not re-initialize.** Any state that describes *this* URL must be keyed or reset on navigation.

Four instances are known, and the first two were each repaired at the call site without the class being named:

1. **8.9.5** — the exam screen read `useState<string | null>(reported)`, capturing its `failure` prop at mount, so a failure arriving during the five-second poll was never shown.
2. **The re-sit ticks** — a re-sit reopened with the previous sitting's selections still on the page.
3. **4.7** — both interviews render `courses/interview`, and the open is guarded by `opened = useRef(false)`; the knowledge → goals visit leaves the ref true, so the goals stream is never requested. The student sees an error that only a full page refresh clears.
4. **8.8.10** — `selected` and `explaining` live in `lessons/show.tsx`, which every lesson URL renders, so the explainer survives a move to the next lesson.

**Two local fixes and no rule is what produced the third and fourth instances**, so the rule lands in `CLAUDE.md` as a standing convention rather than in four call sites. 4.7 and 8.8.10 are scheduled as one cross-cutting work unit, ahead of the package units that would otherwise fix the same bug twice.

## D29. An acceptance test lives in a spec, one per package (resolved 2026-09-07, planning session, Colin)

**Chosen: per-spec acceptance tests; the v1 global test moves to `docs/system.md`.**

A spec's per-item done-conditions prove one item. An acceptance test proves the items compose into a journey a student can take, which is a different claim and needs its own home.

**It goes in the spec rather than the work unit's plan file** because a plan file is deleted when the unit lands (D27). A test kept there cannot be re-run against a later change, which is most of what an acceptance test is for. A plan file may hold the notes from running one.

**The v1 global test — one honest course cycle in a browser and again on a phone, no developer touching the DB — is spent.** It passed 2026-09-07. The cycle it describes is what `docs/system.md` exists to document, so it lands there as the working cycle rather than being restated in nine specs or deleted.

**Rejected — keeping one global test at the repo root.** It would be the last reason for PLAN.md to exist, and it describes v1 rather than any package, so it fits neither the tracker being retired nor a spec.

## D30. Three shells, not one, and the two things all of them share (resolved 2026-09-01, package 8 sessions 8.5/8.7/8.9/8.10, Colin)

**A shell is named for what it frames, and there are three of them.** `StudyShell` frames being taught (8.5.1). `PipelineShell` frames a course being set up — it is `StudyShell`'s rail reduced to the bar it already becomes at ≤767px, because a course with no approved syllabus has no lessons to list (8.7). `AssessmentShell` frames being measured: a rail of ✓ rows and a wine band, no column header — the sheet and the book print their own — and no explainer (8.9).

**`AssessmentShell` is the third shell, and deliberately a third rather than a generalisation of the other two.** The obvious move on reaching the third case is to lift the common parts into one configurable shell; that is the reading this entry exists to refuse. What the assessment artboards draw is neither of the other two, and a shell that takes enough props to be all three stops being named for anything.

**Two things are shared, and they are shared as code, not as a base class.** `components/rail.tsx` holds the crest-and-wordmark head and its hit box, which all three draw. `App\Courses\LessonRail` is the rail's lesson list written once, because the exam rail draws it too — a failed sitting's whole recovery is going back to a lesson, so the exam screen needs the same list the lesson screen has.

**The starter kit's `AppLayout` frames what is not a course.** The dashboard and the module rollup are set in it rather than in a fourth shell (8.10): it is already rail green, it carries the crest and the user menu the flow map reaches settings through, and neither screen's rail holds a progress list for a shell to draw. `app.tsx`'s layout switch returns `null` for `courses/`, `lessons/` and `refreshers/` — those pages are the whole viewport — and `AppLayout` for everything else.

Rejected: **one shell with a `variant` prop.** It puts three unrelated framings behind one name and makes every later change to one of them a change to all three.

## D31. The resume table is code — `NextAction` — and a refusal speaks twice (resolved 2026-09-01, package 8 session 8.7, Colin)

**8.6's "where a resume lands" table is written once, as `App\Courses\NextAction`.** Three callers want to decide where a course picks up: the dashboard card (8.10.1), a resume from the set-aside list (8.7.6), and every pipeline screen's redirect when it finds the course has moved past it. Three implementations of one table is how the table drifts, and the map states plainly that nothing else may decide it.

Its `completed` row lands on the dashboard until 8.10.2 builds `modules.show`, and `NextActionTest` asserts that route's *absence*, so the day it appears the test fails loudly instead of the row staying wrong quietly.

**`ResumeRefusal` splits `CourseNotResumable`'s message in two — the developer's reason and the student's.** The flow map requires a refused resume to say *which* of the three checks failed, and the sentence that tells a developer which invariant held is not the sentence a student should read. One exception carrying one string cannot do both, so the refusal is its own value object with both.

Rejected: **deciding the next action in the React card.** It is the same rule in TypeScript, at the one layer that cannot be tested against the state machine that owns it.

## D32. The worksheet has one disclosure path, and choosing an option is the hand-in (resolved 2026-09-01, package 8 session 8.9, Colin)

**`Worksheet` holds what happens *on* the photocopy sheet** — the answer, the reveal, the count, the onward move — so homework and all three refresher grains share one disclosure path rather than two that can drift. `QuizSheet` is the paper; `Worksheet` is the behaviour on it.

**The artboard's "Hand it in" button is not built** (8.9.1). `POST …/answers` takes one question at a time and discloses that question's key in its reply (6.2, 6.4), so a batching button would either record nothing until the last question — throwing away every answer a student made along the way — or be a no-op over answers already recorded. The rail's own words on the same artboard are "Answers are kept as you go", which is the truth of the endpoint. Choosing an option *is* the hand-in, and the footer carries the count and the onward move in the button's place.

This is the opposite of the final exam, and deliberately: the exam is sat as a whole and discloses nothing until hand-in (D20), because it is a gate. A worksheet is practice, and practice that withholds the answer teaches nothing.

## D33. The exam screen's three states are decided on the server (resolved 2026-09-01, package 8 session 8.9, Colin)

S9 and S10 are one URL (8.6), so the exam screen has three states — closed, open, and result — and **the server picks which one renders**, from `courses.status` and the last sitting. Deciding it in the browser would put the rule in two places, and the browser's copy is the one no test of the state machine reaches.

**`FinalExam::lastSitting()` exists for this**, over `QuizGrader::lastSitting()`, which keeps *attempt-is-the-sitting-number* in the class that writes the attempt row. A pass or a fail has to survive a reload rather than living only in the reply to the POST that produced it, because S10 is a state of S9 and the student can arrive at it by typing the URL.

## D34. `final_exam_concepts` is emphasis, not scope, and keeps its name (resolved 2026-09-06, packages 6 and 8, items 6.3.3 and 8.7.7, Colin)

Since 6.3.1 the exam is written across every lesson whatever this list says, so the field no longer states the exam's scope. It states where the exam puts its **weight**. The claim was corrected in both places it was made:

- **On the screen** (8.7.7). The syllabus panel read "The final exam draws on" over the list, so a student revising the four slugs shown was revising for a fraction of what the exam asks.
- **In the schema** (rider, same session). `SyllabusSchema`'s field description read "Slugs the final exam draws on", and `prompts/syllabus/generate.md` never names the field — so that one string was the *only* instruction the generator got about it. A list described as a scope is how the first real course declared 44 slugs against a 12-question exam and made the old coverage rule unsatisfiable. It now says where the weight falls, that the exam is written across every lesson whatever the list holds, and that a longer list buys no coverage.

**The field keeps its name.** Renaming it to `course_concepts` "so refreshers can use it" was considered and dropped: all three refresher grains already build their vocabulary from the lessons themselves, so the rename buys no caller and costs a migration plus every stored syllabus document.

**`concept_tag` stays a single value.** "Prefer questions that combine two concepts" remains advice in the prompt; the schema is shared with checkpoints, homework and refreshers, and widening it there to serve the exam alone would change four consumers for one.

No separate table or model for final exams — see D26.

## D35. Client-side behaviour is proven in jsdom, not in a browser (resolved 2026-09-08, the mounted-component unit, spec item 4.7, Colin)

**Chosen: `vitest` + `jsdom` + `@testing-library/react`, run by `npm test` and gated in `composer ci:check`.**

4.7 is the first done-condition in the repo that no PHP test can reach. It is a claim about what React does when Inertia hands a mounted component a new URL's props, and the whole of it happens after the last HTTP response the server test suite can see.

jsdom proves exactly that claim and nothing around it. A test renders the screen at one `kind`, re-renders at the other — which is the props change a state-preserving visit delivers — and asserts the second stream was requested. Remove the `key` and it fails; that is the regression, stated directly.

**Rejected — Laravel Dusk, which was the first choice.** It cannot be installed: it pins `guzzlehttp/guzzle ^7.5` against this project's guzzle 8, and resolving it means downgrading the HTTP client the AI gateway streams over. Trap [T6](lessons.md). It would also have needed the gateway faked *inside* `php artisan serve`, because the reproduction only appears when the knowledge stream opened in the same mount — so a real end-to-end run is not available at any price today.

**Rejected — Playwright.** A real browser and the real redirect, with no composer churn, but it carries the same served-process fake problem as Dusk plus a browser download in CI, to prove a proposition that is entirely about React's reconciler.

**Rejected — no automated test, amending 4.7 to a manual check.** The class has now produced four claimed instances and cost four sessions; the one thing that stops the fifth is a test that fails when the key comes off.

**What this does not buy.** Nothing here exercises Inertia itself. That the redirect preserves state, and that a GET link does not, is read from `@inertiajs/*` and recorded in [D28](02-design.md) — it is not under test, and an Inertia upgrade that changed either would pass this suite.

## D36. The interview opener is a wire artefact, not a turn (resolved 2026-09-08, package 4 item 4.6.1)

**Chosen: `InterviewSession::OPENING_TURN` is assembled in `ask()` and never written to `interview_messages`.**

4.6.1 asked the question in the right order — is the opener needed at all, before deciding how to store it — because removal is a smaller change than hiding. It is needed, for two independent reasons, either of which alone settles it:

1. `PendingCall::assertCallable()` refuses a streamed call with an empty user turn ("there is nothing to answer"), and `open()` has nothing else to send.
2. Anthropic's Messages API takes no assistant message ahead of a user one. `ask()` pops the newest student turn off the history and sends it as the turn, so for every turn after the first the remaining history begins at the tutor's opening question — an assistant message. The opener is what keeps that history legal.

**So "the opener is not needed" was false, and "the opener is a student turn" was the actual defect.** It was written down as a row because a row was the nearest place to put it, and everything that reads rows then had to know about it: the screen painted "I am ready to start." as something the student typed, `wk.create` and `criteria.create` were handed it as the first line of the transcript, and 8.6's re-open precondition had to be narrowed around a transcript that was not empty and held nothing anyone said.

**Unstoring it retires that narrowing rather than restating it.** A student turn only ever follows a tutor turn, so `hasTutorTurn()` and `isEmpty()` now agree; the predicate that survives is `hasTutorTurn()`, because it is the one that says what the rule protects — a conversation the tutor has spoken in — rather than how the rows happen to fall.

Rejected: **keeping the row and filtering it out at each reader.** Three readers today (the screen payload, two summarizing prompts) and every future one, each carrying the same exception, to store one string nobody said.

## D37. The question cap is not a third termination door (resolved 2026-09-08, package 4 items 4.3.1 and 4.4.1; **superseded by [D38](#d38-the-question-budget-asks-for-an-ending-and-never-imposes-one-resolved-2026-09-08-package-4-items-431-and-441-colin)** — read D38 for the current rule)

> **Amended.** The first build had the cap summarize *instead of* asking again and stream a fixed `CLOSING_LINE` in place of the sixth question. Running it, the conversation just stopped — "it works, but it's still missing a step" (Colin, 2026-09-08). **The cap now picks the moment and the tutor still says so itself**: the capped answer gets an ordinary turn, the brief's `pacing` variable carries a wrap-up instruction, and `ask()` summarizes when that stream finishes whether or not the model also called `signal_complete`. The two paragraphs on fixed text below are what that replaced; everything else in this entry still binds.
>
> **The cap stays hard, and does not rest on the model obeying.** A tutor that ignores the wrap-up and asks a sixth question ends the interview anyway — it just ends it rudely. That is the difference between instructing the model and being governed by it, and it is what makes the number in `questionCap()` a cap rather than a stronger hint. Cost is now one call more than the uncapped interview's ending, not one less; that call is the ending the student actually sees, which is what it buys.

**Chosen: `InterviewSession::answer()` counts the tutor's turns and makes the capped turn the last one. No `CompletionSignal`, no new `CompletionSource`.**

[R3](#r3-interview-termination--model-signals-via-signal_complete-tool--always-visible-done-button-either-path-fires-the-same-summarization-workflow) has two doors and both are *outside parties* saying the interview is over: the model, with a reason worth recording, and the student, pressing Done. `CompletionSignal` exists so those two arrive in one shape. The cap is neither — it is this class declining to ask a sixth question — so routing it through a signal would mean a third `CompletionSource` in package 2 recording a reason nothing reads, to describe a party that does not exist.

**~~The cap ends the interview one model call earlier, never one later.~~** *(Superseded by the amendment.)* The capped answer was summarized rather than answered, so a five-question interview cost five conversation turns and a summary — and the tutor's closing line was fixed text rather than a generated turn, because generating it would have spent the call the cap had just saved.

**~~And the closing line is not a transcript row.~~** *(Superseded.)* It went out as `turn.start` + `turn.delta` with no `turn.end`, since the summarization it followed had already discarded the transcript. The closing turn is now an ordinary tutor turn, which reaches the client the same way and — because the transcript goes with the summary — still carries no `turn.end`. That shape survived the amendment; only its author changed.

Rejected: **stating the number in the prompt.** That is what `prompts/interview/knowledge.md` already did ("Eight to twelve exchanges is usually enough") and what the dogfood course proved worthless: a number in a prompt is guidance, and the interview ran until the student pressed Done. The prompts still name no number — `pacing` says "ask your next question" or "this is your last turn", never how many are left, so the model cannot pace itself towards an ending the cap has not reached.

## D38. The question budget asks for an ending and never imposes one (resolved 2026-09-08, package 4 items 4.3.1 and 4.4.1, Colin)

**Chosen: spending the budget swaps one line of the brief and does nothing else. R3's two doors remain the only ways an interview ends.**

[D37](#d37-the-question-cap-is-not-a-third-termination-door-resolved-2026-09-08-package-4-items-431-and-441-superseded-by-d38--read-d38-for-the-current-rule) was built twice and both builds interrupted the student. The first stopped the conversation dead and streamed a fixed line. The second asked the model to wrap up and then summarized regardless — so a model that asked one more question showed the student a question and immediately took the screen away. **"I'd rather it drag on too long than rudely interrupt"** (Colin, 2026-09-08), and that ordering is the whole decision.

**There is no way to make a model stop at an exact turn, so the code stops pretending to.** `Interview::questionBudget()` is now a budget: at five questions (knowledge) or four (goals), `InterviewSession` swaps `KEEP_GOING` for `WRAP_UP` in the brief's `pacing` variable and leaves the conversation alone. The tutor ends the interview by calling `signal_complete`, exactly as it always has.

**What makes this stronger than a sentence in the prompt file — which is what 4.3.1 was written to replace — is that it repeats.** A static "keep it brief" is read once and then sits under a growing transcript getting less salient every turn. `WRAP_UP` appears on the turn the budget runs out and on every turn after it, so the pressure to end arrives exactly when it is wanted and does not decay. The number is still never stated to the model: it is told "ask your next question" or "wrap up now", never how many are left, so it cannot pace itself towards a finish line the budget has not reached.

**The student is the real bound, so the screen now says so and shows it.** The Done button was a small underlined link in the footer; it is now `size="action" variant="outline"`, the same secondary action the syllabus and dashboard screens use, and the line above the conversation reads "You decide when it's enough" rather than describing the button's existence. An interview that is only bounded by the student must not make the student hunt for the exit (8.7.2, 8.12.3).

**Accepted cost: an interview can run long, and nothing in the product stops it.** That is the trade taken deliberately. The reversed trade — a bounded interview that sometimes cuts a student off mid-thought — was built, run against a real course, and rejected on the evidence.

Rejected: **removing the budget and keeping only prompt guidance**, which is what "tell the model to try to keep it limited" would have been. It gives up the escalation for nothing: the pacing variable is already there, and a fixed instruction cannot know when the interview has gone on long enough.

## D39. Over-escaped model prose is decoded where the syllabus is accepted (resolved 2026-09-08, package 5 item 5.8 → package 4 item 4.8, Colin)

**Chosen: `SyllabusGenerator` decodes a string whose whole body reads as JSON, once, on the way in.**

The bug was a literal `\u2014` on the lesson completion screen. The renderer was the first suspect and the wrong one: `Prose` passes text through unchanged, and no `lesson_messages` row has ever held the sequence. One `syllabus.generate` call on 2026-09-02 escaped its own prose a second time — eight fields with `\u2014`, `\u2013` or `\n\n` where an em dash, an en dash or a paragraph break belonged — and every consumer downstream printed what it was given. The other three syllabi in the database are clean, so this is a model that misbehaved once, not a code path that always does.

**The repair belongs where the syllabus is accepted because that is the last place anything knows the difference.** Past that point the string is display text to the syllabus screen, to the lesson completion screen, and to the GUIDANCE document the tutor reads back as its own context — three consumers, one of which is a prompt, none of which can tell a dash from an escape of one.

**The decode is conservative, and that is a deliberate limit.** A string is repaired only if the whole of it parses as a JSON string body; a stray backslash repairs nothing rather than something. The cost is that a syllabus which genuinely wanted to write `\n` would have it taken at its word. No syllabus has wanted to, and a course that teaches escape sequences would name them in a lesson section, not in a course objective.

**Repair rather than reject.** 4.5.1 fails closed, so a rejection here would cost a `top`-tier retry and then refuse the student a course over a dash. The defect is cosmetic and the fix is lossless in every case the guard fires on.

Rejected: **normalizing in the gateway (2.1) for every structured response.** It is the widest coverage and the wrong altitude — no spec item asks for it, and it would silently rewrite model text for a tutor that may one day teach JSON. The syllabus is the only place the defect has been seen and the only place all three consumers meet.

Rejected: **repairing the data and adding no guard.** One generation in four is not a rate worth ignoring, and the next occurrence would cost the same investigation from scratch.

## D40. Where the correct option sits is the writer's, and it is dealt out rather than shuffled (resolved 2026-09-08, package 6 items 6.6/6.6.1, dogfood repair)

Nothing reordered a question's options: they reached the student in the order the model emitted them, with the model also naming the key. A model asked for one right answer among four writes it first or second far more often than chance, and the checkpoints Colin met in his own course clustered on A and B. A student who notices that stops reading the options, which is the whole question gone.

**`Quizzes::create()` places the key, because it is the single writer (D18).** Four kinds are generated in four classes and all four arrive here; a rule applied at the four generation sites is a rule the fifth kind forgets. The mechanics live on `QuizDocument` as `withDistributedAnswers()`, next to the other two writes a document accepts.

**Positions are dealt least-used-first within the document, ties broken at random, and only the key moves.** The distractors keep the order they were written in, so an option that reads as a closing alternative still closes; the key is lifted out and put back at the position it was dealt.

Rejected: **shuffling each question's options independently**. One call, and the obvious answer. It is uniform only in expectation, so a ten-question quiz can still hand a student four answers on B, and the done-condition — no position over 40% across forty questions — is then a claim about a random variable rather than about the code. Dealing makes the spread a fact about every quiz and lets the test assert it without seeding a generator.

Rejected: **rotating the key through the positions**. Perfectly even and perfectly learnable: the second question's answer is always B.

Rejected: **shuffling in the client at render**. It is one line in the component and it moves options under the student on every re-render, and the saved answer sheet (D26) records an option id against a paper whose order the server does not know.

Rider: **exactly four options, not three to four** (6.6.1). `MIN_OPTIONS` was 3, so a large share of questions had no D for the dealer to choose, and a three-option question is a third of a coin flip more guessable than a four. The four prompts that write questions now say four, and say why the options must stand alone — the application decides where each one appears, so "all of the above" is wrong by the time the student reads it.

## D41. A validation error names every rule the field broke, and the joining is Inertia's boundary (resolved 2026-09-08, package 1 item 1.5, dogfood repair)

The password rules reached the register, reset and settings-security screens only as the WebKit `passwordrules` attribute, which renders no visible text. A student therefore learned the rules one failed submission at a time — and could not learn them faster than that, because Inertia's `resolveValidationErrors` keeps `errors.field` a string by dropping all but the first message a field failed on. Two rules broken, one rule named, one more round trip.

**The lines are derived from `Password::defaults()`, not written out.** `App\Auth\PasswordRequirements::inWords()` reads `appliedRules()` — public on the rule — and returns one readable line per condition. The rule differed by environment at the time (`min(8)` locally, `min(12)` plus four character classes and the breach check in production; since [D43](#d43-the-password-rule-is-eight-characters-in-every-environment-resolved-2026-09-11-package-1-item-15-dogfood-repair) it is one rule), so a hand-written list would have been wrong in one of the two, and wrong silently.

**The joining happens in `HandleInertiaRequests`, once, for every field.** Inertia already carries `$withAllErrors`; turning it on and joining each field's messages back into one string means `errors.password` names all of them while staying the type every `<InputError message=>` in the app reads.

Rejected: **`$withAllErrors = true` alone**, leaving `errors.field` a `string[]`. Two lines of middleware, and then every screen, every `InputError` and every prop type in the app changes shape to serve one field on three screens.

Rejected: **joining only the password field**, at the three call sites. Cheaper to write and it makes the same defect a standing one: the next field with two rules on it is silently back to naming one.

Rejected: **marking the failed lines in the visible list** instead of naming them in the error. It needs the failures matched back to the lines that produced them — a second mapping to keep true — and the error text would then be the only thing on screen that did not say what was wrong.

## D42. A free-text subtopic is checked against its module before a course exists (resolved 2026-09-09, package 4 item 4.9, dogfood repair)

**Chosen: one `cheap`-tier check on submit, refusing before any row is written.**

"algebra" typed under Language started a Language course about algebra. So did "medieval history", and so did "physics" — three tries, three courses, no flag (Colin, 2026-09-09). 4.1 said mismatch was handled at 4.5.7, and 4.5.7 handles a different mismatch: `subtopic_mismatch` is *goals against subtopic*, raised by `syllabus.generate` after two interviews and a `top`-tier call. Nothing had ever asked whether the subtopic belonged to the module.

**Mismatch is a flag at syllabus time and a blocker at creation time, and the difference is what it would cost to be wrong.** `prompts/syllabus/generate.md` still says to note a mismatch rather than refuse, because by then the student has answered nine questions and a refusal throws them away. At submit nothing exists yet, so a refusal costs a sentence.

**The catalog is not an ownership table, and the model has to be told so twice.** A first prompt carrying the six modules and their subtopics refused `logic` under Math (Philosophy lists it), `game theory` under Math (Econ lists it), `history of science` under Science and `statistics` under Econ — stably, with reasons that named the mechanism outright: *"listed as a core topic under Math, not Economics."* A paragraph stating that placement is not exclusive fixed the topics absent from the catalog — business writing, rhetoric, quantum computing, Python — and moved none of the four that are in it. A sentence rendered only on a verbatim slug collision, saying the match does not settle the question, fixed all four, 3/3 each, and did not loosen a single misfile: `algebra`/Language, `Spanish`/History, `chemistry`/Math and `microeconomics`/Math still redirect. Three probes, 228 live calls, $0.36; 37 of 42 pairs stable and defensible.

**The student reads our words.** The same probes had the model call business writing an Economics subject, three times out of three, in a fluent sentence a student would believe. The `reason` string is generated and stored and never rendered.

**A collision is detectable mechanically and not resolvable mechanically.** `logic` under Math wants acceptance and `algebra` under Language wants redirection; both are slug matches against another module's catalog. A lookup cannot tell them apart, which is why the collision is a hint to the model rather than a verdict of its own.

Rejected: **widening 4.5.7 to cover module mismatch.** Cheapest to build, and it reaches the student after two interviews and the most expensive call in the app.

Rejected: **refusing flatly and pointing at support.** Colin's first framing. No support channel exists anywhere in the app, and all three reported cases are topics OpenTutor teaches one module over — the student would be sent to a human to be told what a button could tell them. The redirect costs nothing structurally: `SubtopicName` slugs free text and catalog entries identically, so "algebra" under Math *is* the catalog's Algebra, same slug, same working-knowledge document.

Rejected: **an override button — "keep it under Language".** It makes every wrong verdict a one-tap annoyance instead of a dead end, and it makes the refusal advisory, which is the non-blocking flag already rejected one screen earlier. The cost is carried instead: `epistemology` under Science is refused to Philosophy, 3/3, and that student has only a retry that will not flip.

Rejected: **failing open when the provider is unreachable.** Chosen in framing and reversed by Colin during design: *"I don't think the course should start if it's a broken call."* Failing closed is 4.5.1 as written — nothing downstream exists to protect — so this needs no exception, and the catalog path still starts any preset subtopic during an outage.

Rejected: **dropping the `reason` field once the copy became ours.** It is generated after the verdict and reading it costs nothing, and it is the only record of why a student was turned away. It is stored against the refusal for an admin UI to read back (Colin, 2026-09-09).

## D43. The password rule is eight characters, in every environment (resolved 2026-09-11, package 1 item 1.5, dogfood repair)

The app is going to friends and family, and the production rule -- twelve characters, mixed case, a number, a symbol, and the breach-list check -- was the first thing they met. It is more than the accounts protect, and composition rules are the part people feel and the part that buys least: the usual answer is `Password1!`.

`Password::defaults()` is now `Password::min(8)` with no environment branch. The register, reset and settings-security screens derive their text from it (D41), so the visible list shrank to one line with no screen touched.

Rejected: **`min(8)->uncompromised()` in production** -- the Have I Been Pwned k-anonymity check refuses passwords already in leaked lists and asks nothing of the student while typing. It is the one rule worth adding back if the app ever holds more than course notes; it is not needed to start, and it adds an outbound call to registration.

Rejected: **keeping the production rule and relaxing only locally** -- it is production that friends and family register against.

## D44. The name, the motto and the monogram (resolved 2026-09-11, unit `harkness`, Colin; **the monogram's band amended by [D46](#d46-the-wordmark-stacks-the-motto-is-engraved-and-the-front-door-gets-its-own-ground-resolved-2026-09-11-unit-harkness-spec-item-813-colin)**)

> **Amended — there is no band.** The motto's placement below was this entry's preference, not a drawn result; drawing it on canvas page B · brand refuted it, and D46 sets the motto as its own engraved line instead. No third `Crest` variant exists — `filled` and `outline` are still the whole component. The name, the pedagogy rule, the motto's words and the two letters all stand.

The product was named OpenTutor from 2026-08-21 until this entry. The name described the category and claimed nothing, which was right while the only user was the founder and wrong the moment the app had a domain and a front door.

**Chosen: Harkness Academy**, at `harkness.academy`. The name is Colin's college dormitory, where he met most of his friends; that origin is the real one and is never told publicly. The Harkness *pedagogy* — twelve students round an oval table at Phillips Exeter, the teacher mostly silent — was discovered after the name was picked, and is a coincidence rather than a claim.

**The pedagogy is not claimed, and no user-visible copy may claim it.** The product is a one-to-one tutor; there is no table and there are no peers. What the name is allowed to carry is the *register* — the New England prep-school anchor this ledger already settled in D21, whose named references are *Dead Poets Society*, *School Ties*, *The Holdovers* and *Scent of a Woman*. The register lives in type, colour and material. It does not live in sentences about seminars.

**Chosen motto: `LICET FACERE`** — "it is permitted to do." It is the Latin of the phrase *you can just do things*, which is the brand's actual claim, and **that phrase never appears in English anywhere in the product**. The motto is set the way a school motto is set: two words, engraved, unglossed, and funny only to a reader who translates it.

Rejected: **`Age Modo`** — "just act." More idiomatic Latin and punchier as a mark, and semantically wrong: it is Nike's *Just Do It*, a claim about effort and discipline. The claim being made is about permission, which is the opposite end of the same sentence.

Rejected: **`Licet Tibi Res Facere`** and **`Licet Tibi Facere`** — literal calques of the English. The first is a sentence rather than a motto and cannot be engraved; the second keeps the direct address at three words and reads as address rather than as inscription. Motto register is two to three words — *Non Sibi*, *Veritas*, *Finis Origine Pendet*.

**Chosen monogram: `HA`, inside the crest, with the motto on a band beneath the shield at hero size only.** `HA` reads as a laugh, which is the point: a traditional surface with an irreverent core, the same joke the Latin is telling. The band is a third `Crest` variant used by one call site; `filled` and `outline` are untouched, so the rail, the exam cover, the auth layout and the icons are unaffected.

Rejected: **the plain letter swap with no band** — correct and cheaper, and it leaves the motto as a line of copy beside the mark rather than part of the object. Kept as the small-size reduction, which is what every existing call site draws.

Rejected: **an entwined `HA` ligature** — better craft, unproven at 16px where a joined pair closes up, and it introduces a drawn letterform the canvas has none of.

The crest itself is unchanged from D21: wine shield, gold rule, chevron, Georgia caps. This entry changes two letters.

**What keeps the old name, deliberately.** The Postgres databases stay `opentutor` and `opentutor_test`, and with them `phpunit.xml`, `.env.example`'s `DB_DATABASE`, the `createdb` line in the README and the CI workflow — renaming a live database holding a real course buys nothing. The git repository and working directory stay `open-tutor` for the same reason. So does body prose in the historical tier (`00-frame.md`, `01-research.md`, `03-spec.md`, `PLAN.md`): those are dated records of what was decided under the old name, and each carries one line under its title pointing here instead.

## D45. Entry is one shared invitation code, held in config (resolved 2026-09-11, unit `harkness`, spec item 1.6)

The landing page puts a Register button on a public domain, and `users.plan` has one value with no quota, price or entitlement behind it (D11): nothing in the app limits what an account spends. Friends and family are wanted; strangers who find the URL are not.

**Chosen: one shared code, read from the environment through `config/`, checked in `CreateNewUser` beside the existing 18-or-older attestation.** It is the least new structure that makes the wanted thing true — no table, no migration, no model. Rotating it is an environment change and a deploy.

**The invitation is not a plan.** An invited account is `standard` like every other. Skill level is pedagogy and plan is economics (spec 4.5.6); entry is neither, and must not become a third axis.

Accepted cost: **one person cannot be revoked without cutting off everyone.** Rotating the code invalidates it for all future registrations; accounts already created are unaffected. At friends-and-family scale that is a message in a group chat, not an outage.

Rejected: **per-person codes in a table** — revocable and attributable, and it buys a migration, a model, an admin surface to mint codes and a second entity in the data model, for a list of people Colin can name. The trigger to build it is the day he cannot.

Rejected: **no gate at all, and a spend cap instead** — a cap reads `usage_events` back as a total and refuses work mid-course, which is failing closed on a student who did nothing wrong (spec 4.5.1). The gate belongs at the door, not in the lesson engine.

Rejected: **no Register link at all, with invited friends sent straight to the form** — the door works and is unguessable, and a returning member who forgets whether they have an account finds nothing to tell them.

## D46. The wordmark stacks, the motto is engraved, and the front door gets its own ground (resolved 2026-09-11, unit `harkness`, spec item 8.13, Colin)

D44 settled the name, the motto's words and the two letters. It left four things to be decided by drawing them rather than arguing them, on canvas page **B · brand**. This entry is the answer to all four, and the artboards titled CHOSEN there are what it means.

**Chosen wordmark: W3, stacked letterspaced caps** — one word per line, Instrument Serif, `0.3em` tracking. The 252px rail decided it and nothing else needed to: `Harkness Academy` set as one line wraps there at any size large enough to read, where the stacked form fits with 58px to spare. The crest is 34px tall and two 15px lines are 34px, so mark and name align as one object, with the course on its own line beneath a hairline — beside the mark it read as a tagline for the product rather than as which course you are in.

Rejected: **W1, the name on one line** — what every screen drew until now, and the form the longer name broke. It survives nowhere; `<Wordmark/>` splits `APP_NAME` on whitespace, so the config value stays the one owner of the string (H1.2) and a one-word name would still render.

**Chosen motto placement: P2, engraved as its own line** between brass rules, hero size only. It costs no new artwork: the motto is copy, so it sets in the page's own type and reads at any width. It hangs off **the mark**, not off the headline — a school motto belongs to the crest, and under "Enroll yourself." it read as a subtitle for the sentence above it.

Rejected: **P1, on a band beneath the shield** — D44's own preference, and drawing it is what refuted it. At every size the page actually uses, a band 11 units tall under a 38-unit shield does not read as a band; it reads as a smudge under the crest, and the two words inside it are illegible below hero size. **This is the entry to revisit if the crest is ever punched up** — the idea is right and the current shield is too small to carry it. No third `Crest` variant was built, so `filled` and `outline` remain the whole component.

**Chosen page: L2, the argument leads.** "Enroll yourself." is the first thing on the page; the mark is the page's one object, at the right, rather than a header.

Rejected: **L1, the mark leads** — a centred crest over the name over one sentence. It is the more conventional academy front door and says nothing in the first second: a visitor who has never heard of the product learns the name before learning what it does.

**Chosen ground: G3, a step below the reading ground** — the front door is the one screen with no student inside it, and the step down is what says the course has not started yet. It is **`--door`, `oklch(0.915 0.016 95)`**, and not the `--desk` the G3 artboard was drawn on.

Rejected: **G1, paper** — the reading ground, which made the front door look like a lesson already in progress. Rejected: **G2, rail green** full bleed — the deepest value in the palette, and wine on it is a 0.16 lightness step, so the primary action needs a gold hairline to hold its edge that it needs on neither light ground. Adding an outline to the one action colour to make it survive its background is the background being wrong (8.3.4).

**The desk could not carry it, which is why `--door` exists.** G3 was drawn on `--desk` and judged invisible: the desk is 2.4% under `--ground`, which is plenty where the two touch — the blue book lying on the page — and nothing at all full-bleed, where there is no `--ground` beside it to be read against. `--door` is a 1.15 contrast step against `--ground` where the desk was 1.07.

Rejected: **deepening `--desk` itself** — one value instead of two, and it changes the exam screen to fix the landing page. The desk's job is to be the surface an object is photographed on, and it is correct at its current value for that job.

**`app.css`'s `--desk` comment therefore survives, unchanged in substance.** It claims nothing is ever set on the desk, which is why it is not a fourth ground, and with the front door on `--door` that is still true. `--door` is a fourth *ground* in the plain sense and still not a fourth material in D22's: the three materials are bound to what a student is doing — paper reads, photocopy practises, blue book examines — and on the doorstep nobody is doing anything yet.

`--muted-ink` holds **4.62:1** on `--door`, against 5.33 on `--ground`. Prose on the front door still clears AA; there is no room to go darker without moving the ink.

**The signed-out bar and the footer are both cut**, which amends spec item 8.13.4. Both were drawn; every link either carried has another owner. The motto is in the hero (P2), the 18-or-older attestation is on the registration form (D45), "put it on your phone" is a member route off the user menu, and Log in and Register are the hero's two buttons — a bar repeating them put two primary actions above the fold competing with each other. **Terms** was the one item with no other owner on a public page, and it was kept at first as a single quiet line at the foot. Looking at it cut that too: one orphan link at the foot of an otherwise linkless page does not earn the row, and the registration form it is reachable *from* is where a visitor meets it anyway. The front door navigates in two places, and both of them are buttons.

Rejected: **keeping the bar for the crest's sake**, so the mark appears at the top of the page as it does on every other screen. The other screens are shells around a course; this one is not, and L2 already puts the mark on the page as an object.

## D47. An answer the tutor never replied to is replaced by the next one (resolved 2026-09-13, package 10 item 10.3)

**Chosen: `Transcript::answer()` overwrites a student turn that ends the transcript, and appends otherwise.**

The student's row is written before the provider is called, so every failure after that point — a provider error, a cut connection, an empty reply — leaves an answer with no reply behind it. The screen hands the answer back to be sent again (10.3), and sending it appended a second copy. The old `stream.error` copy, *"send that again"*, had been producing that double-post all along.

**A student turn with nothing after it can only be an answer whose reply was lost.** The tutor always speaks between two answers, so the replacement never erases anything the model saw.

Rejected: **deduplicating on the client, or on equal text.** "Yes" twice in a row is an ordinary interview, and a client cannot see whether the first one reached the server.

Rejected: **writing both rows only when the reply lands.** A lost reply then leaves nothing behind to replace, but `ask()` must stop reading the newest turn off the transcript, and an answer sent just before Done is dropped from the summary.

## D48. A checkpoint's wait runs out, and the course's request is read as the newest section's (resolved 2026-09-13, package 11 items 11.3/11.5, Colin)

**Chosen: one checkpoint request per course, as 11.5 keys it; it belongs to the furthest section taught; and it stops being awaited after two minutes.** Two minutes is `CheckpointGenerator`'s two attempts at the `cheap` tier's 60-second timeout, and lives as `GenerationKind::patience()`. After that the margin says there is no question for this section this time and offers **Try again**, with Continue beneath it as the way on.

A checkpoint belongs to a section, and `generation_requests` is unique on (course, kind), so the row cannot say which section it is for. Sections are taught in order and the end of the newest one is what asks, so the newest taught section is the only honest reading. And a queued job that never runs, as happens when the queue is stopped, leaves the row there for good. Every other wait in the app polls until the row goes, and 11.3 forbids that here.

The cost is named. A section that finishes while another section's request is still pending gets no checkpoint until the student presses Try again. With a working queue that needs a 25-second job to outlast a streamed section plus the reading after it, so in practice it happens when the queue is slow or stopped.

Rejected: **a row that names its section** (lesson and section columns, a wider unique key). It is exact, but it rewrites the key 11.5 fixes, to serve a case only a stopped queue produces.

Rejected: **the wait ending silently** (Colin). The note disappearing leaves the student with no idea that a question was due, and in a stopped-queue hour it happens on every section.

Rejected: **waiting as long as it takes**, which is what the syllabus and the exam do. They fail closed and hold something the student cannot go on without. A checkpoint fails open, so a screen still promising one after its job could have finished is promising something that will not come.

## D49. Done's write-up goes to the queue with the tutor's (resolved 2026-09-13, package 11 item 11.4, Colin)

**Chosen: `InterviewSession` asks the queue for the write-up from both doors, and the interview takes no answers while it is pending.** 11.4 names the answer request, but the model's `signal_complete` and the Done button have always reached one write-up, and Done's POST was just as silent for the length of `wk.create`. While the write-up runs, the answer box, Send and Done are all disabled. An answer sent then would land in a transcript the write-up has already read (Colin).

A failed write-up is kept on the request row rather than returned as a 422. The flow map's "422 summary unusable" edge becomes "write-up unusable", reached by the screen's reload.

Rejected: **queueing only the answer request's write-up.** It gives the one write-up two paths, and it leaves Done exposed to the 60-second ceiling T10 is about.

## D50. The demo is the real pipeline on a shorter plan (resolved 2026-09-15, spec 12, Colin)

The app can only be entered with an invitation code (D45), and the people Colin most wants to show it to — recruiters, employers, investors — are strangers with a link. A gated app is not a portfolio piece.

**Chosen: a public route mints an ordinary user row on a `demo` plan, and nothing past the front door knows the difference.** The `demo` plan resolves to one lesson through the depth mechanism 4.5.6 already owns, so the visitor meets the real interviews, the real syllabus, a real lesson with real checkpoints, and real graded homework. One route, one config key, one ending screen.

**The entry carries no 18-or-older attestation** (Colin). `tos_accepted_at` is stamped at registration and read nowhere in the app, so a null on a demo row changes no behaviour. Accepted cost: the attestation the registration form carries is absent from an account that can hold a course; conversion asks for it (12.4.3).

**Amended 2026-09-16, package 12 build, Colin: the entry is a welcome page with one button, and a Demo marker rides every header.** The first cut had nothing to press — the link itself minted the account and dropped the visitor on course creation. Seen running, it was not a demo: a stranger arrived at a bare form with nothing saying what it was, how long it would take, or that it could be kept, and every chat app that unfurled the link minted an account. Minting moved to a POST behind "Start the demo"; the GET writes nothing. The marker (12.1.6) is what keeps the visitor oriented after the welcome page, and the way to the offer before the lesson is done. Rejected: **the bare GET with the sweep cleaning up after it** — the sweep deletes preview rows, it does not stop a visitor arriving with no idea where they are.

**Conversion withdraws the demo course rather than growing it.** Depth is read only at syllabus generation, so a one-lesson course stays one lesson whatever the plan becomes; withdrawal is the move the app already has, and a withdrawn course keeps everything it wrote — the working knowledge especially, which is what the student's next course starts from. Rejected: **redrawing the syllabus at standard depth on conversion**, which spends another $0.157 and throws away a draft the student approved. Rejected: **leaving the course active at one lesson**, which hands a converted student a course that is finished the moment it starts.

Rejected: **a `courses.is_demo` column.** It invents a third axis beside pedagogy and economics, which 1.6.2 and 4.5.6 forbid in as many words.

Rejected: **shortening through skill level.** It violates skill level ⊥ price, which `config/courses.php` records as a founder correction.

Rejected: **a separate `syllabus.demo` prompt.** A different prompt is a different pipeline, and the two drift; the same argument would then be had again for `lesson.section` and `homework.generate`. Size reaches the model through `depth_params`, which is the job 4.5.6.2 gives it.

Rejected: **a session-only visitor with no user row.** Courses, memories, quizzes and attempts all key on `users.id`, and `users.email` and `password` are NOT NULL — so "keep this course" would have been a fresh start dressed as continuity.

Rejected: **a demo-only auto-dispatch of the syllabus, or a demo-only reordering of the completion chain.** Both make the demo faster and both make the demo a second pipeline. The two moves are worth having on their own merits, for every student, and are not this package's to take.

**Amended 2026-09-17, package 12 build, Colin: the demo is lesson 1 of an ordinary course, not a one-lesson course.** Built as a one-lesson course, keeping it at sign-up had no good answer: withdrawing it left a short course the student could pick back up, and replacing it with a full course meant redrawing the syllabus or sitting the interviews again. Colin: "It should just be one preview lesson." So the demo draws the syllabus any student gets, and a demo visitor may take lesson 1 and nothing after it; signing up changes nothing about the course and lesson 2 opens. The gate is `LessonPolicy`, which every lesson route already asks, plus the exam controller. Superseded here: **the one-lesson depth**, and with it the conversion-withdraws-the-course paragraph above. Cost: a full syllabus ($0.157) where a one-lesson one was $0.055 — and one generation at one lesson packed 21 concepts into twenty minutes, which the preview does not risk.

## D51. `plan` keys depth, and is not yet a table (resolved 2026-09-15, spec 12 item 12.2.1)

D11 says `users.plan` becomes a `plans` table the moment a plan carries "a price, a quota, anything a row can hold that a key cannot". D50 adds the second plan value the app has ever had, so the trigger is worth reading rather than assuming.

**Chosen: `demo` is a config key, and D11's trigger has not fired.** Depth is already keyed by plan in `config/courses.php` — `depth.{skill_level}.{plan}` — so `demo` adds a key to a keyed structure rather than an attribute to a plan. Nothing about `demo` is a row's worth of data; it is three integers in a file that already holds three integers per plan. `users.plan` still has exactly one production reader.

Falsified if: the second non-standard plan needs anything config cannot key — a price, a quota, a per-account entitlement, an expiry. That is the day this becomes a table, as D11 says.
