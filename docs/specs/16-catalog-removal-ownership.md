# Spec 16 — Catalog, Removal and Ownership

*Provides: one subtopics table for catalog and student subtopics, removal and restore, memory ownership, resuming any withdrawn attempt, and the refusals list. Consumes: 13 (the gate, the action log), 3 (the memory tree), 4 (course creation, the scope check). Amends [3.1](03-memory.md), [3.2](03-memory.md), [4.5.3](04-course-pipeline.md), [4.9.1, 4.9.2 and 4.9.7](04-course-pipeline.md), [6.3.4](06-assessment.md), [11.5](11-generation-off-the-request.md) and [12.5.1](12-demo.md). Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D8](../02-design.md) working knowledge is per subtopic · [D42](../02-design.md) a free-text subtopic is checked before a course exists · [D57](../02-design.md) a removal is a record that marks what it hides · [D58](../02-design.md) documents have an owner, and each attempt its own folder · [D59](../02-design.md) one subtopics table · [D64](../02-design.md) any withdrawn attempt may be resumed · [D67](../02-design.md) `subtopics` has no `created_by` · [D68](../02-design.md) nothing is hard-deleted but an unclaimed demo.

## The shape

**What each removal hides.**

| Removed | Also hidden | Left alone |
|---|---|---|
| module | its subtopics, their courses, those courses' documents, each subtopic's working knowledge | — |
| subtopic | its courses, their documents, its working knowledge | — |
| course | its own documents | the subtopic's working knowledge — what the student knows is still true |

**Restore brings back exactly what its removal hid.** A course removed on its own keeps its own removal when its subtopic is later removed and restored.

**Documents belong to something** (confirmed layout, 2026-09-18). Working knowledge belongs to the subtopic and is shared by every course in it (D8). Criteria, lesson guidance and summaries belong to one course, and move into an attempt folder so a later attempt no longer overwrites an earlier one.

---

## 16.1 One subtopics table

- **16.1.1 MUST** — `subtopics` gains `origin` (`catalog` | `student`) and `listed`. Existing rows become `catalog`, listed. Who first typed a student subtopic is the student on its oldest course; there is no `created_by` ([D67](../02-design.md)). *Done: after migrating, every existing row reads so.*
- **16.1.2 MUST** — A free-text subtopic that passes [4.9](04-course-pipeline.md) finds or creates a `student` row. *Done: two students typing the same name share one row.*
- **16.1.3 MUST** — `courses.subtopic_id` is added and backfilled from the frozen slugs; slugs absent from the catalog create `student` rows, and the migration prints what it created for review. Three are expected in dev (confirmed); whether any is a renamed catalog subtopic is unknown. *Done: every course has a `subtopic_id`; the printout matches.*
- **16.1.4 MUST** — The student's picker shows only listed, unremoved subtopics. *Done: feature test.*
- **16.1.5 MUST** — Admin → Catalog → Demand lists unlisted student subtopics with course count, distinct students, first and last seen. Promote lists one, optionally retitled, and is logged. *Done: a promoted subtopic appears in the picker; existing courses keep their paths.*
- **16.1.6 MUST** — Admin → Catalog → Refusals shows typed name, module, verdict, suggested module, the model's reason (admin-only, [4.9.6](04-course-pipeline.md)), and a link to the student. *Done: renders both dev rows.*

## 16.2 Removal and restore

- **16.2.1 MUST** — `removals`, each tied to its `admin_actions` row; `deleted_at` and `removal_id` on `modules`, `subtopics`, `courses` and `memories`. *Done: additive migrations.*
- **16.2.2 MUST** — Removal marks what [the shape](#the-shape)'s table says. *Done: one test per level.*
- **16.2.3 MUST** — An already-removed row keeps its own removal; restoring a parent restores only its removal's rows. *Done: remove course A, remove its subtopic, restore the subtopic — A stays removed.*
- **16.2.4 MUST** — Removed rows are hidden by default: queries exclude them, `MemoryTree` excludes removed documents, and a removed course's URL is a 404 to the student. *Done: feature tests.*
- **16.2.5 MUST** — The open-course index gains `deleted_at IS NULL`. After a course is removed the student can start fresh; the attempt number continues. *Done: test.*
- **16.2.6 MUST** — Restoring a course is refused while another open course exists on its subtopic; the refusal names that course. Both outcomes are logged. *Done: test.*
- **16.2.7 MUST** — Typing a name that matches a removed subtopic is refused before the scope check. *Done: the test finds no `subtopic.check` usage row.*
- **16.2.8 MUST** — A queued job for a removed course checks at start and exits quietly; the course's open generation requests close. *Done: remove a course during its syllabus job — no syllabus is written.*
- **16.2.9 WONT** — Hard deletion from admin.
- **16.2.10 MUST** — A student deleting their own account soft-deletes it ([D68](../02-design.md)). Nothing under it is deleted, it cannot sign in, and its email stays taken. The demo sweep ([12.5.1](12-demo.md)) remains a hard delete of unclaimed demo rows only. *Done: after a self-deletion the account's courses, memory tree and usage rows are all present and sign-in is refused; `demo:sweep` still removes an unclaimed demo row with its courses, and a soft-deleted `standard` account survives it.*
- **16.2.11 MUST** — A generation request is kept when its work lands and when it fails; its state is a column, not its existence, and the one-row key becomes one open request per (course, kind) ([D68](../02-design.md), amending D25 and [11.5](11-generation-off-the-request.md)). *Done: a landed syllabus and a failed-then-retried exam each leave every request row they made, and no screen reads a settled row as a wait.*
- **16.2.12 MUST** — An exam's answer sheet is kept at hand-in; a re-sit opens a new, empty sheet ([D68](../02-design.md), amending [6.3.4](06-assessment.md)). *Done: fail and re-sit — the first sitting's sheet is still there, and the re-sit opens with nothing ticked.*
- **16.2.13 WONT** — Erasing an account's personal data on request. Its own question when a request makes it one.
- **16.2.14 MUST** — `NextAction` finds a completed course's module through the course's own link (course → subtopic → module), not by matching `module_slug`, and its dashboard fallback for a missing module is removed. The database refuses to delete a module with courses under it (16.1.3), and removing one hides its courses with it, so the fallback has nothing left to catch (Colin, 2026-09-23). *Done: grep finds no dashboard fallback in `NextAction`; a completed course lands on its module rollup.*

## 16.3 Memory ownership

- **16.3.1 MUST** — `memories.course_id` (null for working knowledge and the module rollup); `memory_versions.course_id` and `acting_user_id` (the latter already added by [13.4.3](13-admin-foundation.md)). *Done: migrations.*
- **16.3.2 MUST** — Course documents move to `{module}/{subtopic}/{level}/a{attempt}/…`. A data migration rewrites existing paths, every path stays within 255 characters, and [docs/deploy.md](../deploy.md) gains a `db:backup` step before it. *Done: every course's documents resolve under its attempt folder; `course_syllabi`'s pinned versions still resolve.*
- **16.3.3 MUST** — `MemoryTree::put` takes an owner — a course, or none — and every version records the course that wrote it. Sixteen call sites (confirmed) pass it. *Done: every working-knowledge version names its writing course.*
- **16.3.4 MUST** — Admin reads memory read-only, through a path-API method that can include removed documents. [3.2](03-memory.md)'s "no other door" test extends to admin controllers. *Done: the architecture test passes.*

## 16.4 Resuming any withdrawn attempt

- **16.4.1 MUST** — Any withdrawn, unremoved attempt can be resumed while no other course on its subtopic is open. The open-course index already enforces the second half. *Done: withdraw attempt 1, complete attempt 2, resume attempt 1 — it opens at its own lesson with its own guidance.*
- **16.4.2 MUST** — When a subtopic holds more than one withdrawn attempt, the return prompt asks which to continue, or to start from scratch. *Done: jsdom test with two withdrawn attempts.*
- **16.4.3 MUST** — `CourseTransitions`' "is the highest attempt" precondition, and the comments that cite D7b's rider, are removed. *Done: grep finds no "highest attempt" in `app/Courses` or `app/Lessons`.*

## 16.5 Catalog screen

- **16.5.1 MUST** — Admin → Catalog shows the module and subtopic tree with Remove, and a Removed view with Restore; both ask for a reason. *Done: feature test round trip.*
- **16.5.2 MAY** — Retitling a catalog subtopic; its slug stays frozen.

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. Remove a course. The student no longer sees it, starts fresh as attempt 2, and the new course reads the existing working knowledge.
2. Restore the old course — refused, naming attempt 2.
3. Withdraw attempt 2. Restore attempt 1; the student is asked which attempt to continue, picks 1, and lands on its own next lesson.
4. Remove the subtopic. Both attempts and the working knowledge disappear; typing the name is refused.
5. Restore the subtopic. Both attempts and the working knowledge return.
6. Promote a student subtopic; it appears in the picker.
