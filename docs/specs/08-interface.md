# Spec 8 — Interface

*Provides: every screen the engines feed, package 4's missing HTTP layer, and the design language. Consumes: 1–6.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D21](../02-design.md) the visual direction · [D22](../02-design.md) material carries meaning · [D23](../02-design.md) the checkpoint is a margin question · [D24](../02-design.md) Radix and the copied shadcn primitives stay · [D25](../02-design.md) queued generations need a waiting state · [D26](../02-design.md) the answer sheet · [D28](../02-design.md) the mounted-component class (8.8.10) · [D30](../02-design.md) three shells, and what they share · [D31](../02-design.md) `NextAction` and `ResumeRefusal` · [D32](../02-design.md) the worksheet's one disclosure path · [D33](../02-design.md) the exam screen's states are the server's · [D34](../02-design.md) what the syllabus panel may claim about the exam.

*The design is settled. `docs/design/canvas/` is its source of record; the published canvas is where it is read. Direction, palette, material system and the checkpoint choice are ledger entries D21–D24 in [docs/02-design.md](../02-design.md) — don't re-litigate them.*

## 8.1 Design source of record — first

- **8.1.1 MUST** — Canvas source committed to `docs/design/canvas/`: the `.dc.html` artboards, `canvas.json`, and a `README.md` naming the seed command and the artifact URL. *Done: a fresh clone re-seeds the canvas without extracting the published page.*
- **8.1.2 MUST** — Artifact URL recorded in `docs/02-design.md`. *Done: the URL appears in D21.*
- **8.1.3 MUST** — `CLAUDE.md` gains the standing convention: the canvas is the design source of record; a change to the visual language changes `docs/design/canvas/` **and** `resources/css/app.css` in the same commit. *Done: a session reading only CLAUDE.md can find the canvas.*
- **8.1.4 SHOULD** — A `reference`-type memory entry carrying the URL. *Done: recall surfaces it in a new session.*
- **8.1.5 MUST** — Ledger entries D21–D24 written: direction and palette; material system; checkpoint and its phone reduction; Radix ratification. *Done: four entries, each with its rejected candidates.*

## 8.2 Fixtures

*Built with `/package 8 items 8.2`, not `/interface` — this section produces a seeder, not a screen. Built immediately after 8.1 and before anything is styled: no course has ever been generated, so nothing in the database can render a screen. A seeder writes rows directly — no inference, deterministic, re-runnable every session.*

*Content comes from the artboards in `docs/design/canvas/`, which is real Spanish teaching prose written to the schemas. Lorem ipsum is refused: uniform word lengths and no structure tell you nothing about whether a layout survives a long Spanish quotation at 44ch.*

- **8.2.1 MUST** — One seeder, re-runnable, producing all six states below against a known user. *Done: `php artisan db:seed --class=InterfaceFixtureSeeder` twice in a row yields the same six states with no unique-constraint failure.* *Amended 2026-09-02 (Colin): the done-condition named `migrate:fresh --seed`, and the seeder is no longer called by `DatabaseSeeder`. It clears the known student's courses before writing its own, which was harmless while that student existed only to be seeded and destroys real progress now that the same account is taking a real course. It is invoked by name or not at all; `DesignFixtureTest` is what runs it twice.*
- **8.2.2 MUST** — **Fresh course** — approved syllabus, no lesson started. Feeds 8.7's syllabus screen and 8.10's dashboard. *Done: the syllabus screen renders overview, five lessons with objectives and concept slugs, and `final_exam_concepts`.*
- **8.2.3 MUST** — **Lesson mid-flight** — `section_index > 1`, transcript rows present, one section of genuinely long prose. Feeds the measure checks (8.3.3) and the resume path (5.3). *Done: reopening restores the section and transcript; the column measures 72 ±2 characters at 1440px and ~50 at 390px (8.3.3, amended 2026-08-31).*
- **8.2.4 MUST** — **Unanswered checkpoint** in the mid-flight lesson, stripped per 5.2.3. Feeds 8.8.3. *Done: the payload contains no `correct_option_id` or `explanation`.*
- **8.2.5 MUST** — **Ungraded homework** on a completed lesson. Feeds 8.9.1's pre-answer DOM check. *Done: the rendered page contains neither answer-key field before submission.*
- **8.2.6 MUST** — **Course complete, exam unsat** — five lessons done, homework in, exam generated at 8–12 questions. Feeds 8.9.2 and 8.9.3. *Done: both the closed book and a re-sit render; a pass and a fail each render their gate.*
- **8.2.7 MUST** — **Completed subtopic and module** with a rollup to draw on. Feeds 8.9.4's three refresher grains. *Done: lesson, subtopic and module refreshers all generate.*
- **8.2.8 MUST** — Memory documents (WORKING_KNOWLEDGE, CRITERIA) are written **through the path API**, never by writing the memory tree directly (spec 3.2, and a standing convention). *Done: the seeder imports no path outside `App\Memory`'s door.*
- **8.2.9 WONT** — Running the engines to generate this content. The fixture is a stand-in for real output; the first check against real output is the dogfood cycle (8.11.4).

## 8.3 Design language

- **8.3.1 MUST** — `resources/css/app.css` rewritten with the settled tokens: ground `oklch(0.962 0.010 95)`, surface `oklch(0.988 0.006 95)`, ink `oklch(0.24 0.028 155)`, muted `oklch(0.50 0.020 145)`, rail green `oklch(0.205 0.042 155)`, field green `oklch(0.34 0.075 155)`, wine `oklch(0.36 0.115 22)`, brass `oklch(0.70 0.110 82)`, gold `oklch(0.83 0.090 88)`, hairline `oklch(0.87 0.012 95)`. *Done: no `oklch(… 0 0)` remains in the file; every shadcn variable resolves to a token above.*
- **8.3.2 MUST** — Type: Newsreader body 17px/1.62, Instrument Serif display, Caveat for the tutor's hand, each with a fallback stack. *Done: a font-blocked page still renders legible prose.*
- **8.3.3 MUST** — Measure capped at **72 characters of prose** on desktop, narrowing with the viewport to ~50 at 390px, with no font-size reduction rule. *Amended 2026-08-31 (Colin): the numbers were written as CSS `ch`, which is the width of the digit `0`. Newsreader's digits run 0.566em against an average character of 0.407em, so `max-width: 72ch` sets a 693px line of ~100 characters, and `44ch` at 390px is 424px — wider than the phone, so unreachable at any gutter. The measure is therefore stated in characters, which is what the convention and D21's three cited products mean by it; the phone number becomes what a conventional 20px gutter yields.* *Done: measured 72 ±2 characters at 1440px and ~50 at 390px, with the same rule at both.*
- **8.3.4 SHOULD** — Wine is the only action colour; brass and gold are labels, rules and marks. *Done: no `<button>` carries a brass or gold background.*
- **8.3.5 MUST** — A `<Crest/>` component: one SVG source serving the rail, the exam cover, completion states, the favicon, and package 7's icons. *Done: one source, five call sites; 7.1 imports no separate artwork.* *Clarified 2026-09-07 (Colin): **`public/favicon.ico` stays the starter kit's Laravel mark.** The crest ships as `favicon.svg`, which every browser that understands it prefers; the `.ico` is the fallback for the ones that do not, and replacing it is not worth a session. Say the word and it goes with the same artwork.*
- **8.3.6 MUST** — Radix and the copied `components/ui` primitives stay (D24). *Falsified if:* any settled screen requires replacing a primitive's anatomy rather than restyling it.
- **8.3.7 WONT** — Storybook, component API docs, a versioned library. Tokens plus ~8 composites, then stop.

## 8.4 Material system

- **8.4.1 MUST** — Three grounds bound to purpose: paper column = reading; photocopy sheet = all practice; blue book = the final exam (D22). *Done: no practice surface renders on the paper ground, and no reading surface on the photocopy ground.* *Clarified 2026-09-01 (Colin): "practice" here means the worksheet quizzes D22 enumerates — homework and the three refresher grains. The in-lesson checkpoint is practice too, but it is part of the lesson, and D23 puts it on the paper column with no container on purpose (the photocopy treatment was drawn and rejected). It is not a counter-example to this rule.*
- **8.4.2 MUST** — Photocopy stock (`oklch(0.955 0.003 100)`, Courier Prime, name/date block, directions line, off-square rotation) serves homework and all three refresher grains. *Done: one component renders all four, differing only in its scope label.*
- **8.4.3 MUST** — Blue book: closed state, then opened cover-and-leaf; interior on a 30px rhythm with type on the rules and a red vertical margin. *Done: every line of interior body type sits within 2px of a rule at 1440px.*
- **8.4.4 SHOULD** — The closed→open transition is animated, once per sitting, skipped on a re-sit. *Done: a re-sit opens directly to the leaf.*
- **8.4.5 WONT** — Legal pad (reserved for the v1.1 notes feature) and index card (unassigned). Both stay in the canvas; neither ships.

## 8.5 Application shell

- **8.5.1 MUST** — Desktop: rail (rail green) / paper column / explainer (field green), three distinct values. *Done: verifiable from computed styles.*
- **8.5.2 MUST** — At ≤767px the rail collapses to a bar carrying the lesson title and section progress. *Done: no horizontal scroll at 390px; the section count is still visible.*
- **8.5.3 MUST** — At ≤767px the explainer rises as a sheet over dimmed prose. *Done: focus is trapped, Escape closes, the underlying scroll position survives the close.*
- **8.5.4 MUST** — Every interactive target ≥44px on touch. *Done: audited at 390px.*
- **8.5.5 WONT** — Dark mode (v1.1). Only light tokens are defined; the `.dark` block comes out of `app.css`. *Done: no `.dark` selector remains.*
- **8.5.6 MUST** — The appearance switcher is hidden, since 8.5.5 leaves it nothing to switch to. The component stays in the tree for v1.1. *Done: `settings/appearance` is unreachable from the UI.*

## 8.6 Flow map

*Built before 8.7, not after it: five of the twelve screens have no routes yet, so this map decides package 4's URL structure once and as a whole rather than one controller at a time.*

- **8.6.1 MUST** — One diagram in this spec showing every transition between the twelve screens, including resume and failure paths, and naming the URL of each. *Done: every route in `routes/` appears as at least one edge, and every screen 8.7 will build has a URL named here before its controller is written.*

### The twelve screens

| # | Screen | URL | Route name | Status |
|---|---|---|---|---|
| S1 | Dashboard | `GET /dashboard` | `dashboard` | exists (starter-kit placeholder; 8.10.1 replaces it) |
| S2 | Course creation | `GET /courses/create` | `courses.create` | **8.7.1 builds** |
| S3 | Knowledge interview | `GET /courses/{course}/knowledge-interview` | `courses.knowledge-interview.show` | **8.7.2 builds** |
| S4 | Goals interview | `GET /courses/{course}/goals-interview` | `courses.goals-interview.show` | **8.7.3 builds** |
| S5 | Syllabus approve/revise | `GET /courses/{course}/syllabus` | `courses.syllabus.show` | **8.7.4 builds** (8.7.5's banner is a region of it) |
| S6 | Lesson | `GET /lessons/{lesson}` | `lessons.show` | exists |
| S7 | Lesson completion | `GET /lessons/{lesson}` — a state of S6, not a URL | `lessons.show` | 8.8.6 builds the state |
| S8 | Homework | `GET /lessons/{lesson}/homework` | `lessons.homework.show` | exists |
| S9 | Final exam | `GET /courses/{course}/final-exam` | `courses.final-exam.show` | exists |
| S10 | Exam result and gate | `GET /courses/{course}/final-exam` — a state of S9, after a sitting | `courses.final-exam.show` | 8.9.3 builds the state |
| S11 | Refresher | `GET /refreshers/{refresher}` | `refreshers.show` | exists |
| S12 | Module rollup | `GET /modules/{module}` | `modules.show` | **8.10.2 builds** |

Five have no route (S2, S3, S4, S5, S12) — that is 8.7's and 8.10.2's work. Of the seven that do, every one answers JSON today, because packages 5 and 6 shipped the wire and left the screen: 8.8 and 8.9 turn them into Inertia pages at the same URLs. Two pairs share a URL by design — a completed lesson and a sat exam are states of the screen the student is already on, not places they navigate to.

### The map

```
  GET /            ──► redirects, never renders: signed in ──► S1, otherwise sign in ──► S1
  GET terms        ──  linked from registration
  GET install      ──  linked from the user menu: how to put OpenTutor on a phone's home screen (spec 7.1)

  S1  DASHBOARD                                        GET /dashboard
      ├─ "Start a course" ────────────────────────►    S2
      ├─ "Continue" (per course; resume table below) ► S3 · S4 · S5 · S6 · S9
      ├─ "Set aside" ──► POST /courses/{course}/withdraw ──► S1, the card moved to the set-aside list
      ├─ "Pick back up" ──► POST /courses/{course}/resume ──► the resume table's URL for its status
      │     └─ 422 not resumable ──► S1, naming which: a later attempt replaced it, or its subtopic is occupied
      └─ a completed course's module ─────────────►    S12

  S2  COURSE CREATION                                  GET /courses/create
      ├─ POST /courses  (catalog pick or free text + level) ──► S3
      ├─ a withdrawn attempt at this subtopic and level (CourseTransitions::resumable) ──► S2 asks D7b's
      │     question — "pick up where you left off, or start from scratch?"
      │       ├─ pick up ───► POST /courses/{course}/resume ──► the resume table's URL for its status
      │       └─ start over ► POST /courses ──► S3, at attempt + 1
      └─ 422 subtopic already open ──► S2, offering that course's resume URL. The question above should have
            caught it; what reaches this edge is the double tap and the second tab

  S3  KNOWLEDGE INTERVIEW                              GET /courses/{course}/knowledge-interview
      │   opening question   GET  …/knowledge-interview/stream    (only while the transcript holds nothing but the opening turn)
      │   each answer        POST …/knowledge-interview/answers   (streams the reply)
      ├─ Done ──► POST …/knowledge-interview/complete  (writes WORKING_KNOWLEDGE) ──► S4
      ├─ stream.error ──► S3, transcript intact, the answer re-sent
      └─ 422 summary unusable ──► S3, still underway, Done pressable again

  S4  GOALS INTERVIEW                                  GET /courses/{course}/goals-interview
      │   same three endpoints under …/goals-interview; complete writes CRITERIA
      ├─ Done ──► S5
      └─ same two failure edges as S3

  S5  SYLLABUS                                         GET /courses/{course}/syllabus
      │   no draft yet ──► POST /courses/{course}/syllabus            (generates generation 1)
      ├─ Approve ──► POST /courses/{course}/syllabus/approval ──►     S6 (lesson 1)
      ├─ Revise  ──► POST /courses/{course}/syllabus/revisions ──►    S5 (a new draft; the course returns to syllabus_draft)
      ├─ 503 syllabus unusable ──► S5 unchanged; asking again is the recovery
      └─ 422 revisions used up ──► S5 with approve as the only move left

  S6  LESSON                                           GET /lessons/{lesson}
      │   section prose     GET  /lessons/{lesson}/sections/{index}/stream
      │   question          POST /lessons/{lesson}/questions            (streams; joins the transcript)
      │   highlight         POST /lessons/{lesson}/explanations         (streams; leaves the transcript alone)
      │   checkpoint        POST /lessons/{lesson}/checkpoints/{checkpoint}/answers
      ├─ reopen (5.3) ──► S6 at the stored section, transcript and checkpoints restored
      ├─ stream.error ──► S6; the same section URL replays a finished turn and re-teaches an unfinished one
      ├─ checkpoint grading fails open (5.2.1) ──► S6 continues; nothing is lost
      └─ Finish ──► POST /lessons/{lesson}/complete ──► S7

  S7  LESSON COMPLETE                                  (S6's URL, in its completed state)
      ├─ homework ────────────────────────────────►    S8
      ├─ next lesson (unlocked by the chain) ─────►    S6
      ├─ practise this lesson ──► POST /lessons/{lesson}/refreshers ──► S11
      └─ last lesson done ────────────────────────►    S9

  S8  HOMEWORK                                         GET /lessons/{lesson}/homework
      ├─ state: pending ──► S8 waits on the same URL; the chain is still running (5.4.5)
      ├─ answer ──► POST /lessons/{lesson}/homework/answers ──► S8 with the explanation revealed (8.9.1)
      └─ done ────────────────────────────────────►    S6 (next lesson) · S9 (if none remain)

  S9  FINAL EXAM — closed book                         GET /courses/{course}/final-exam
      ├─ not available yet ──► S6, naming the lessons still owed
      ├─ Open the book ──► POST /courses/{course}/final-exam ──► S9 opened (course moves to final_exam)
      ├─ 503 exam unusable ──► S9 still closed; nothing written, nothing moved (fails closed, 4.5.1)
      ├─ re-sit ──► S9 opens straight to the leaf, same exam, no animation (8.4.4)
      ├─ tick an answer ──► POST /courses/{course}/final-exam/answers ──► S9, the sheet saved, nothing marked (6.3.4)
      └─ Hand in ──► POST /courses/{course}/final-exam/sittings ──► S10

  S10 EXAM RESULT AND GATE                             (S9's URL, after a sitting)
      ├─ pass (≥70%) ──► S1, the course completed; ──► S12 for its module
      ├─ fail ──► the gate holds; the course returns to active ──► S6 · S11 to study, then S9 to re-sit
      └─ practise a subtopic ──► POST /courses/{course}/refreshers ──► S11

  S11 REFRESHER                                        GET /refreshers/{refresher}
      │   reached from three doors, one per grain:
      │     POST /lessons/{lesson}/refreshers   (lesson)    from S7
      │     POST /courses/{course}/refreshers   (subtopic)  from S10 and S12
      │     POST /modules/{module}/refreshers   (module)    from S12
      ├─ answer ──► POST /refreshers/{refresher}/answers ──► S11 marked
      └─ back ────────────────────────────────────►    S1 · S12

  S12 MODULE ROLLUP                                    GET /modules/{module}
      ├─ a subtopic's course ─────────────────────►    S1's card for it
      ├─ subtopic refresher ──► POST /courses/{course}/refreshers ──► S11
      └─ module refresher ────► POST /modules/{module}/refreshers ──► S11

  SETTINGS (outside the twelve; reached from the user menu, returns there)
      GET settings ──► GET settings/profile ⇄ GET settings/security
      GET settings/appearance is unreachable from the UI (8.5.6) and stays so until dark mode ships
      GET .well-known/passkey-endpoints answers password managers, never a student
```

### Where a resume lands

The dashboard's "next action" per course (8.10.1) is this table; nothing else may decide it.

| `courses.status` | Resumes at |
|---|---|
| `created` | `GET /courses/{course}/knowledge-interview` |
| `knowledge_interview` | `GET /courses/{course}/knowledge-interview` — the transcript is restored, the opening is not re-streamed |
| `goals_interview` | `GET /courses/{course}/goals-interview` |
| `syllabus_draft` | `GET /courses/{course}/syllabus` |
| `syllabus_revision` | `GET /courses/{course}/syllabus` — the previous draft still stands and is still approvable |
| `active` | `GET /lessons/{lesson}` for the lowest lesson not `completed`; if none remain, `GET /courses/{course}/final-exam` |
| `final_exam` | `GET /courses/{course}/final-exam` — the same exam, opened to the leaf |
| `completed` (derived) | `GET /modules/{module}` |
| `withdrawn` (derived) | the dashboard's set-aside list. `POST /courses/{course}/resume` clears the date and leaves `status` alone, so where the student lands is this same table, read a second time |

### The new URLs, in full

Seventeen routes, five of them screens. 8.7, 8.10.2 and 8.10.3 write them; this section is where they were decided.

| Method | URL | Name | Engine call |
|---|---|---|---|
| GET | `courses/create` | `courses.create` | catalog read |
| POST | `courses` | `courses.store` | `CreateCourse::fromCatalog` / `::fromFreeText` |
| GET | `courses/{course}/knowledge-interview` | `courses.knowledge-interview.show` | `Transcript` replay |
| GET | `courses/{course}/knowledge-interview/stream` | `courses.knowledge-interview.stream` | `Interviews::knowledge()->open()` |
| POST | `courses/{course}/knowledge-interview/answers` | `courses.knowledge-interview.answers.store` | `…->answer($text)` |
| POST | `courses/{course}/knowledge-interview/complete` | `courses.knowledge-interview.complete` | `…->doneButton()` |
| GET | `courses/{course}/goals-interview` | `courses.goals-interview.show` | `Transcript` replay |
| GET | `courses/{course}/goals-interview/stream` | `courses.goals-interview.stream` | `Interviews::goals()->open()` |
| POST | `courses/{course}/goals-interview/answers` | `courses.goals-interview.answers.store` | `…->answer($text)` |
| POST | `courses/{course}/goals-interview/complete` | `courses.goals-interview.complete` | `…->doneButton()` |
| GET | `courses/{course}/syllabus` | `courses.syllabus.show` | `Course::latestSyllabus()` |
| POST | `courses/{course}/syllabus` | `courses.syllabus.store` | `SyllabusGenerator::first` |
| POST | `courses/{course}/syllabus/revisions` | `courses.syllabus.revisions.store` | `SyllabusGenerator::revision` |
| POST | `courses/{course}/syllabus/approval` | `courses.syllabus.approval.store` | `ApproveSyllabus` |
| GET | `modules/{module}` | `modules.show` | `ModuleRollup::render` |
| POST | `courses/{course}/withdraw` | `courses.withdraw` | `CourseTransitions::withdraw` |
| POST | `courses/{course}/resume` | `courses.resume` | `CourseTransitions::resume` |

Three shapes were settled here rather than left to a controller:

- **`GET …/stream` opens an interview; `POST …/answers` continues it** — the same split as a lesson, where the tutor speaks first over GET and the student answers over POST. It carries one precondition, because `InterviewSession::open()` *discards* a half-finished transcript: **the stream URL may be requested only while the restored transcript holds nothing but the opening turn.** A reload paints from the screen payload and never re-opens. Getting this wrong loses a student's interview, which is the one thing 8.7.2's done-condition checks.

  *Narrowed from "only when the transcript is empty" (Colin, 2026-09-01, in the 8.7 session).* `open()` writes the student's `OPENING_TURN` **before** the tutor's first question streams, so a connection dropped during that question leaves a transcript that is not empty and holds nothing anyone said. Under the original wording that student was stranded: they could not re-open, and Done would have summarized one line they never typed over whatever earlier courses in the subtopic recorded (ledger D7a). Re-opening there discards nothing the student wrote, which is the whole of what the rule protects. Both halves are enforced in the engine rather than asked of the client — `open()` throws `InterviewAlreadyUnderway` once the tutor has spoken, and "underway" now means the transcript has a tutor turn in it, so Done refuses a conversation that never happened.
- **Generating the first syllabus is its own POST**, not a side effect of the goals interview's Done. A `top`-tier generation on a redirect path has nowhere to report failure; on its own endpoint, a 503 leaves the student on S5 with a button (4.5.1, and the same reasoning as `courses.final-exam.store`). Both syllabus generators carry `throttle:6,1`, matching the exam.
- **Withdrawal and resumption are POSTs from screens that already exist**, not screens of their own. Both are verbs on a course the student is already looking at, so they read the `lessons.complete` precedent rather than inventing a noun. `withdraw` needs the course open and leaves `status` untouched — that untouched value is what makes `resume` land somewhere; `resume` needs the course withdrawn, the highest attempt at its level, and nothing else open in its subtopic, and its 422 must say *which* of the three failed, because "a later attempt replaced it" and "your other course is open" ask the student for different things (D7b). Two doors reach `resume`: the set-aside list on S1, and S2's question when a student starts the same subtopic again.
- **Approval is a POST to a noun** (`/syllabus/approval`), so approving and revising are two URLs rather than one endpoint switching on a field. `CourseStatus` already treats them as two successors of `syllabus_draft`.

Model binding follows what is already there: `{course}`, `{lesson}`, `{module}`, `{refresher}` bind by id, since no model overrides `getRouteKeyName`.

### Every route in `routes/`, and the edge it is

| Route | Edge |
|---|---|
| `home` | entry ──► S1 |
| `terms` | linked from registration |
| `install` | linked from the user menu; spec 7.1's install-instructions page, since iOS offers no install prompt |
| `dashboard` | **S1** |
| `courses.withdraw` | S1 ──► S1, the card moved to the set-aside list |
| `modules.show` | **S12** |
| `lessons.show` | **S6** / **S7** |
| `lessons.sections.stream` | S6 paints a section; also S6's replay after `stream.error` |
| `lessons.questions.store` | S6 Q&A turn (8.8.5) |
| `lessons.explanations.store` | S6 selection explainer (8.8.4) |
| `lessons.checkpoints.answers.store` | S6 checkpoint answer (8.8.3) |
| `lessons.complete` | S6 ──► S7 |
| `lessons.homework.show` | S7 ──► **S8** |
| `lessons.homework.answers.store` | S8 ──► S8 with the explanation revealed |
| `courses.final-exam.show` | S7/S8 ──► **S9**; and **S10** after a sitting |
| `courses.final-exam.store` | S9 closed ──► S9 opened |
| `courses.final-exam.answers.store` | S9 ──► S9, the answer sheet saved (6.3.4) |
| `courses.final-exam.sittings.store` | S9 ──► S10 |
| `lessons.refreshers.store` | S7 ──► S11 (lesson grain) |
| `courses.refreshers.store` | S10, S12 ──► S11 (subtopic grain) |
| `modules.refreshers.store` | S12 ──► S11 (module grain) |
| `refreshers.show` | **S11** |
| `refreshers.answers.store` | S11 ──► S11 marked |
| `profile.edit` · `profile.update` · `profile.destroy` | settings, outside the twelve |
| `security.edit` · `user-password.update` | settings, outside the twelve |
| `appearance.edit` | reachable by URL only; no edge points at it (8.5.6) |
| `well-known.passkeys` | machine endpoint; no student edge |

Fortify's auth routes are not declared in `routes/`; they land the student at `home` or `dashboard`, which are edges above.

### What this map deliberately does not name

- **No withdrawal screen.** Setting a course aside and picking it back up are two POSTs and two regions of screens that already exist (8.7.6, 8.10.3) — the twelve stay twelve.
- **No syllabus history URL.** Revisions are rows (4.5.4.1) and the screen shows the current draft; nothing in 8.7.4 asks a student to read generation 2 of 4.
- **No per-question exam URL.** D20 forbids it: the exam is sat whole, which is why S9 has one URL and one hand-in.

## 8.7 Package 4's missing HTTP layer

*Inherited, not new: package 4 shipped engine-only, so these routes have never existed. Package 4 owns them by subject and package 8 by schedule; recorded here so nothing falls between.*

- **8.7.1 MUST** — Course creation: catalog browse, free-text creation, skill-level choice, over `CreateCourse::fromCatalog` / `::fromFreeText`. *Done: a course row exists after one pass with no developer touching the DB.*
- **8.7.2 MUST** — Knowledge interview: streamed conversation with an always-visible Done button, over `Interviews::knowledge`. *Done: the transcript survives a reload; Done writes the WK document.*
- **8.7.3 MUST** — Goals interview, same shape, over `Interviews::goals`. *Done: as 8.7.2, writing CRITERIA.*
- **8.7.4 MUST** — Syllabus approve/revise (4.5.4) including the revision request field. *Done: approve advances the course; revise regenerates against the typed request.*
- **8.7.5 MUST** — Level-mismatch banner (4.5.7) when `subtopic_mismatch` is non-null, non-blocking. *Done: a mismatched course still approves.*
- **8.7.6 SHOULD** — "Carry on, or start over?" — when `CourseTransitions::resumable` returns a withdrawn attempt at the chosen subtopic and level, creation asks D7b's question in D7b's words instead of creating a second course. *Done: picking up sends `POST /courses/{course}/resume` and lands on the resume table's URL for that course's status; starting over creates attempt + 1.* Pairs with 8.10.3 — see the note there.
- **8.7.7 SHOULD** — The syllabus screen states what the exam actually does. After 6.3.3 the exam draws on every concept the lessons declare, so the panel's "The final exam draws on" — followed by the `final_exam_concepts` list — is no longer true; the list is the exam's *emphasis*, which is that field's only remaining job once 6.3.1 stops using it as a gate. *Done: the panel's wording no longer claims the listed concepts are the exam's scope, and the question count shown is 6.3.2's computed number. Not in scope: whether 44 raw slugs is good layout.*

## 8.8 Lesson screens

- **8.8.1 MUST** — Lesson screen consuming `LessonState`. *Done: reopening restores section and transcript (5.3).*
- **8.8.2 MUST** — Streaming render off the five events (`turn.start`, `turn.delta`, `turn.end`, `checkpoint.ready`, `stream.error`), painting section title and "N of M" from `turn.start` before the first token. *Done: the header is populated while the body is still empty.*
- **8.8.3 MUST** — Checkpoint as the margin question; at ≤767px it reduces to P1 — the hand becomes the question's lead-in line, brass rule beside the options (D23). *Done: at 390px the checkpoint is distinguishable from body prose without a container.*
- **8.8.4 MUST** — Selection explainer over `POST /lessons/{}/explanations`, warning at the 500-character cap from `limits` while the selection is being dragged. *Done: an over-cap selection is refused client-side before a request is sent.*
- **8.8.5 MUST** — Q&A composer honouring `limits.question_characters`. *Done: the counter reads the server's number, never a duplicated constant.*
- **8.8.6 SHOULD** — Lesson completion state. *Done: completing advances the course and the rail updates without a reload.*

## 8.9 Assessment screens

- **8.9.1 MUST** — Homework on the photocopy sheet, explanation revealed only after answering. *Done: the pre-answer DOM contains no `explanation` or `correct_option_id`.* *Clarified 2026-09-01 (Colin): **answering is per question, and the `QuizPhotocopy` artboard's "Hand it in" button is therefore not built.** `POST …/answers` takes one question and discloses that question's key in its reply (6.2), so a button that batched them would either record nothing until the last question or do nothing at all — and the rail on the same artboard already says "Answers are kept as you go". This is the photocopy sheet's rule and the refreshers' (8.9.4); it is **not** the exam's, which is sat whole and marked once (8.9.2, D20) and does carry a real hand-in.*
- **8.9.2 MUST** — Final exam: closed book → opened, sat whole, handed in once. *Done: no per-question feedback appears before hand-in (6.3, D20).*
- **8.9.3 MUST** — Exam result and the gate it holds, stating the 70% mark. *Done: pass and fail each render; a re-sit reopens the same exam.*
- **8.9.4 MUST** — Refreshers at all three grains on the photocopy sheet. *Done: lesson, subtopic and module grains render from one component.*
- **8.9.5 MUST** — The exam screen shows a queued generation failure. `WriteFinalExam` records a student-facing message on the `GenerationRequest` row and the controller passes it as a prop, but the screen copies that prop into local state once at mount and the five-second poll leaves the component mounted, so the arriving failure is never read — the button silently re-enables and the student is told nothing (found 2026-09-04, three failed exam generations in a row). One file: the syllabus screen reads its own failure prop directly and is correct. *Done: with generation forced to fail, the screen states why within one poll interval and the button returns to its pressable state; with generation succeeding, no failure text appears.*

## 8.10 Course surfaces

- **8.10.1 MUST** — Dashboard replacing the starter-kit placeholder: courses in progress, next action per course. *Done: `placeholder-pattern.tsx` has no remaining import.*
- **8.10.2 SHOULD** — Module rollup view over `ModuleRollup`. *Done: a completed course's knowledge appears under its module.*
- **8.10.3 SHOULD** — Setting a course aside and taking it back up, over `CourseTransitions::withdraw`/`resume`: a course card carries "Set aside" behind a confirmation, and set-aside courses are listed apart from the ones in progress. *Done: a set-aside course leaves the in-progress list and returns to it at the status it left; a course a later attempt has replaced says so rather than offering a button that 422s.*

*8.7.6 and 8.10.3 ship together or not at all. With no way to set a course aside, no course is ever withdrawn, so 8.7.6's question never fires and `CourseTransitions::withdraw`/`resume` stay engine code no student can reach. Ejecting one ejects both.*

## 8.11 Amendments to existing plan documents

- **8.11.1 MUST** — Spec 7 shrinks to its literal text; its done-condition drops "Lighthouse installable" and names the manifest fields plus a real-device add-to-home-screen check. *Done: 7.1 names no check that no longer exists.*
- **8.11.2 MUST** — Spec 7 records browser-menu install only, and WONTs both the in-page install button and the service worker. *Done: 7.1 names one install path.*
- **8.11.3 MUST** — PLAN.md's global acceptance test becomes "in a browser, and again on a phone"; package 8 gains a status row; the dependency graph gains its node. *Done: all three edits present.* *Superseded 2026-09-08 by [9.7](09-documentation.md): the status table and the acceptance test have left PLAN.md — the test is [docs/system.md §8](../system.md), where "and again on a phone" is the clause this item added, and package state is each spec's done-conditions. The graph node stands.*
- **8.11.4 MUST** — The design phase's "real generated course as mockup content" condition is dropped, recorded as met-by-substitute: mockup content is schema-faithful, not engine-produced. *Falsification moves to the dogfood cycle: if real output breaks a layout the mockups predicted would hold, the substitution was wrong.*
- **8.11.5 MUST** — PLAN.md's dependency graph moves package 7 to depend on 1 **and** 8, and drops it from the parallel-lane candidates. *Done: no path in the graph opens 7 before 8 lands.*
- **8.11.6 MUST** — PLAN.md's ejection order gains package 7, because the amended acceptance test is satisfied by a phone browser and needs no install. *Done: the ejection list is non-empty again.* *Spent 2026-09-08: v1 landed with package 7 built, so nothing was ejected, and [9.7](09-documentation.md) removed the list with the rest of the tracker.*

## Dogfood repair (2026-09-07)

- **8.5.7 MUST** — The crest is a link home from every shell. `rail.tsx` draws `Crest` with no link, and `AppLayout` is the only screen a student can leave from, so a course is escapable only by typing a URL. *Done: clicking the crest on a lesson, a pipeline screen and an assessment screen lands on the dashboard.*
- **8.8.7 MUST** — Emphasis pairs correctly, including across a marked phrase and during streaming. `inline()` splits a paragraph around the explainer's marked phrase and calls `emphasise()` on each side separately, so an emphasis span straddling the mark becomes two unpaired halves; a paragraph still arriving holds an unclosed `*`. *Done: a paragraph whose emphasis span contains the marked phrase renders one `<em>` and one `<mark>`, not two broken halves; a partially-arrived paragraph renders no stray asterisk.*
- **8.8.8 SHOULD** — The Continue button after a checkpoint aligns on desktop as it does on phone. At 390px it sits under the last question's Answer button; at 1440px it sits left of it. **Cause not diagnosed.** *Done: the button sits under the Answer button at both widths.*
- **8.8.9 MUST** — Explain This works on a touch device. The selection handler is `onMouseUp` only, and iOS Safari fires no `mouseup` for a long-press selection, so the affordance never appears on a phone. *Done: a long-press selection on iOS Safari raises the explain affordance; the desktop path is unchanged.*
- **8.8.10 MUST** — The explainer closes when the student moves to the next lesson, and stays open across every other interaction in the current lesson. `selected` and `explaining` are `useState` in `lessons/show.tsx`, which every lesson URL renders, so the panel survives the visit. Belongs to the mounted-component unit — see [D28](../02-design.md). *Done: open the explainer, complete a checkpoint, continue — it is open; move to the next lesson — it is closed; close it by hand — it stays closed.*

### 8.12 Work in progress is visible

A student cannot tell a queued generation from a dead one. `syllabus.tsx` carries the only waiting message in the pipeline; homework, the final exam, refreshers and the summarization between interviews say nothing, so a reasonable student concludes the process died and presses again.

- **8.12.1 MUST** — Every model-backed generation shows that work is happening and disables the control that started it: syllabus, homework, final exam, refresher, and the summarization between interviews. *Done: pressing each of the five controls leaves the button disabled and a working indicator on screen until the result arrives or fails; no screen sits silent while a queued job runs.*
- **8.12.2 SHOULD** — The indicator names a generic phase tied to the artifact being made — writing the syllabus, setting the exam — rather than spinning anonymously. It reports no step the job does not already emit; real step-by-step progress reporting is on the roadmap. *Done: each of the five indicators names what is being made.*
- **8.12.3 MUST** — "Done — write it up" is a button, disabled once pressed, carrying 8.12.1's indicator. It is a copy string rendered as a link today. *Done: pressing it once leaves it disabled and indicating until the next screen paints; a second press is impossible.*
- **8.12.4 WONT** — No indicator claims a time or a percentage. Queued generations have no reliable duration. *Falsified if: any indicator shows either.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

Signed in as a student with one active course, at 1440px and then again at 390px, without a developer touching the database:

1. Walk every screen: dashboard → create a course → knowledge interview → goals interview → syllabus → a lesson with its checkpoint and its explainer → lesson complete → homework → the final exam closed, open and marked → a refresher at each grain → the module rollup.
2. At every one of them, the crest goes home. No screen scrolls sideways at 390px; every touch target clears 44px; body prose measures about 72 characters at 1440px and about 50 at 390px with no change of type size.
3. Materials hold: reading is on the paper column, homework and all three refresher grains are on the photocopy sheet, the exam is in the blue book with its interior type sitting on the rules. Wine is the only colour a button ever carries. The checkpoint reads as a margin question on the desktop and as a lead-in line with a brass rule on the phone. Nothing offers dark mode.
4. Press each of the five model-backed generations — syllabus, homework, exam, refresher, and the write-up between interviews. Each leaves its control disabled and says what is being made until the result arrives; none claims a time or a percentage. Force the exam generation to fail and the screen says why within one poll interval, with the button pressable again.
5. Set the course aside from its card. It leaves the in-progress list and is listed apart; taking it back up returns it to the position it left, and creating that subtopic again asks whether to carry on or start over rather than silently making a second course.
6. Every route in `routes/` is an edge on 8.6's flow map, and the test that holds the two together passes.
