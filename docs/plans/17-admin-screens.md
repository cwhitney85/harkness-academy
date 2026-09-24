# 17-admin-screens — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [spec 17](../specs/17-admin-screens.md) — 17.1 Users, 17.2 Courses, 17.3 Usage. Last package of `unit/admin-and-performance`; branch `pkg/17-admin-screens`.

## Items

- [x] **17.1.1** — Users list: name, email, plan, role, joined, course count, cost to date excluding act-as rows; search and plan filter. *Figures match SQL on a fixture.*
- [x] **17.1.2** — User detail: profile, courses (removed marked), cost by feature, memory tree read-only with history naming course and acting admin. Refusals' student link retargeted here. *Renders Colin's account.*
- [x] **17.1.3** — Change plan among config plans, logged. *Feature test; a plan not in config refused.*
- [x] **17.1.4** — Preview / act-as start from Users; the stand-in `/admin` list and its "View as a student" rail link go.
- [x] **17.2.1** — Courses list: student, module/subtopic, level, attempt, standing (incl. removed), cost, created; filters. *Fixture.*
- [x] **17.2.2** — Course detail: criteria, working knowledge, syllabus + revisions, lessons with sections and messages, checkpoints and results, homework/quizzes/attempts, final exam, generation requests and failures, cost by feature, usage rows. *Renders Colin's course.*
- [x] **17.2.3** — Remove and Restore on a course (16.2).
- [ ] **17.3.1** — Usage overview: cost by day, feature, plan over a date range. *Matches SQL.*
- [ ] **17.3.2** — Performance: per task × model — calls, median, p95, mean in/out tokens, cache share, cost/call, failure and failover rates. *Matches SQL on a fixture.*
- [ ] **17.3.3** — Act-as rows excluded by default, toggle. *Test.*
- [ ] **17.3.4** SHOULD — Event browser, filterable, rows link to user, course, lesson. *Fixture.*
- [ ] **17.3.5** SHOULD — Performance under 1s on a million seeded rows. *Benchmark.*

## Log

- Branch sits on `unit/admin-and-performance` at c4f202a; nothing new yet.
- **Task means prompt.** D52: a call names a task, the task a tier, the tier a model. The performance table groups by `prompt_key` × `model`, showing tier per row. Acceptance step 4's "`mid` × its model" is read as the `mid`-tier rows. For Colin at landing: say whether he meant tier.
- **Group by the `model` string, not `ai_model_id`.** 261 of 263 dev rows predate the catalog and have no `ai_model_id`; every row has `model`.
- **`usage_events.plan` is null on every dev row** (no backfill, 14.3.4). The by-plan view must show those as "before plans were recorded", not drop them.
- Admin routes join 8.6's route table, as 14–16 did; `AdminAccessTest` / `AdminWritesTest` get each route.
- **No dev usage row names its course.** All 227 of Colin's rows predate 14.3's `course_id`; there is no honest backfill (guessing which course an old call served writes guesses into a receipt table). So every course of his shows $0. The user page shows the unattributed sum as its own line — the subtopic check, and calls from before rows named their course. For Colin at landing: 17.2.2's cost-by-feature and usage rows will be empty for every existing course, which is what acceptance step 2 opens. Ask whether that is acceptable or whether he wants a heuristic backfill.
- **Restore from a course's page only when its own removal hid it.** A course swept up by its subtopic's or module's removal says so and points at Catalog → Removed; restoring there brings back the whole subtopic, too big to hide behind a course's button.
- **Stale docblock, spec 16's code:** `ExamAnswerSheet` still says a sheet "is deleted the moment the book is handed in"; 16.2.12 keeps it. Not this package's file — for Colin.
- **Interview transcripts:** 17.2.4 says they are discarded after summary (WONT), but system.md §5 says `interview_messages` are kept for the life of the course. The course page shows none either way; the two documents disagree — for Colin.
