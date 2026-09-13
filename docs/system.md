# Harkness Academy — what the app does

*Durable tier (ledger [D27](02-design.md)). Required by spec 9.2. This document describes the **running system**: what a student can do, in what order, and which part of the app answers. The specs in [`docs/specs/`](specs/) say what must be true per package; this says what happens between creating a course and passing its final exam.*

**It deliberately names no classes, no file paths and no method signatures.** Those live in the code, where a rename costs nothing. If a sentence here would need editing because something was renamed with no change in behaviour, that sentence is a defect.

---

## 1. The shape of the thing

A student picks a **subtopic** to learn at a **skill level** (101, 201, 301). The app interviews them twice, writes a syllabus for them, and then teaches it lesson by lesson, checking understanding as it goes. A final exam gates completion. Everything the app learns about the student accumulates in a per-subtopic **working knowledge** document that outlives any one course.

Three vocabularies run through the whole system and are worth holding before reading on:

- **The catalog** is `module → subtopic`. A module is a broad field (`language`); a subtopic is a thing you can take a course in (`spanish`). A student may have **one open course per subtopic**, at one level at a time.
- **The memory tree** is a virtual filesystem in Postgres, keyed by path. `{module}/{subtopic}/WORKING_KNOWLEDGE.md` is the durable record of what a student knows, and it is scoped to the *subtopic* — a 201 course starts from what the 101 course taught. Under it sit per-level documents: `COURSE_CRITERIA.md` (what this student wants), and one `GUIDANCE.md` per lesson (what the tutor is to teach). All of it is model-facing prose; nothing the app queries lives here.
- **Concept slugs** are the connective tissue. The syllabus declares them, checkpoints tag questions with them, lesson summaries report on them, and exams and refreshers draw on them. A slug that was never declared is refused wherever it appears.

## 2. The course lifecycle, end to end

A course moves through seven positions, and two endings that are dates rather than positions.

**1. Created.** The student names a subtopic — from the catalog or as free text — and a level. The course row exists and nothing else does.

A typed name is checked against the module it was typed under **before anything is written**, so the picker can now refuse. A subtopic this app teaches under another module is turned away with an offer to start it there in one press; one nothing here teaches is declined outright; and a check that cannot be run declines too, rather than starting a course it could not vet. There is no override. A name that matches a starting point of the module it was typed under is not checked at all, and neither is the catalog path — a preset belongs to its module by construction, so picking one still works while the check does not. The student reads the app's own words for all three refusals; what the check itself said is recorded and never shown.

**2. Knowledge interview.** The tutor opens the conversation and asks about what the student already knows, budgeted at five questions — after the fifth the tutor is asked, on every turn, to wrap up and end the interview, but nothing forces it to. **The student is what bounds an interview**: a Done button is always visible and ends it at any point. **A turn that fails never strands the student.** The screen reloads, so it lands wherever the course now stands. If nothing moved, the unanswered answer goes back in the box, and sending it again replaces the lost one. Tutor text already on screen stays until the server's copy replaces it. On completion the transcript is summarized into that subtopic's `WORKING_KNOWLEDGE.md`, folding in whatever was already there rather than overwriting it.

**3. Goals interview.** The same, budgeted at four questions — one for each section of the document they become: what they hope to gain, what drew them to the subject, what success would look like, how they like to be assessed. On completion the transcript becomes `COURSE_CRITERIA.md` for this level.

**4. Syllabus draft.** The student asks for a syllabus and the app generates one from working knowledge, criteria, the level, and depth parameters. It is a structured document: a course title, an overview, five to ten lessons each with an objective, sections, declared concepts and a homework focus, plus the concepts the exam should weight. It is a **draft row**, pinned to the exact versions of the two memory documents it was written from.

**5. Syllabus revision.** The student can approve or send free-text feedback and get a new draft, up to three times, after which approve-or-restart is the only move. A failed revision leaves the previous draft standing.

**6. Active.** Approving the syllabus does four things in one transaction: writes the lesson rows (the first unlocked, the rest locked), renders a `GUIDANCE.md` into the memory tree for each lesson, stamps the approved draft, and moves the course to active. From here the course is teachable.

Each lesson is taught in **sections**, paced by code rather than by the model. The tutor streams a section's prose; the student can ask questions in line, or highlight a passage and ask for it to be explained without that exchange joining the lesson transcript. Between sections a **checkpoint** appears — a single multiple-choice question generated from what was just taught, marked in code. Checkpoint grading **fails open**: if it breaks, the lesson continues and nothing is lost.

Finishing a lesson is irrevocable, and everything after it runs on the queue: the lesson is summarized, working knowledge is updated from that summary, **homework** is generated, and the next lesson is unlocked. Homework is a short quiz plus suggested reading; its results feed the *next* working-knowledge update.

**7. Final exam.** When every lesson is complete the student can open the exam. It asks **two questions per lesson**, is written across every lesson the syllabus teaches, and discloses nothing — no answer key travels to the browser, and the whole paper is sat and handed in at once. Selections are saved as they are made, so a half-finished sitting survives a reload. Passing (70%) stamps the course completed; failing records the sitting, leaves the course active, and the student can study and re-sit the same exam.

**The two endings.** `completed` and `withdrawn` are dates on the course row, not positions in the pipeline. A withdrawn course keeps everything it wrote — the working knowledge especially, which is true whatever happened to the course — and the student may take that level again as a new attempt.

**Afterwards.** A completed subtopic can be revised with **refresher quizzes** at three grains: one lesson, one subtopic, or a whole module. The module grain walks a derived rollup of every subtopic under it that has working knowledge, so it draws on courses the student took months apart.

## 3. The screens, and what frames each

Thirteen screens. The full transition map — every edge, every URL, the resume table and the failure paths — is **[spec 8.6](specs/08-interface.md#86-flow-map)**, and is not repeated here.

**The front door** is what a signed-out visitor meets at `/`: the crest, the wordmark and the motto as one object, the headline, what the app does in a sentence of lifecycle, and two buttons — Register and Log in. Nothing else on it navigates: no bar, no footer, no third link. A member never sees it, because `/` redirects them to the dashboard.

| Screen | URL | Frame |
|---|---|---|
| Landing page | `/` | no shell, signed out only |
| Dashboard | `/dashboard` | the app layout |
| Module rollup | `/modules/{module}` | the app layout |
| Course creation | `/courses/create` | the pipeline shell |
| Knowledge interview | `/courses/{course}/knowledge-interview` | the pipeline shell |
| Goals interview | `/courses/{course}/goals-interview` | the pipeline shell |
| Syllabus approve/revise | `/courses/{course}/syllabus` | the pipeline shell |
| Lesson, and its completed state | `/lessons/{lesson}` | the study shell |
| Homework | `/lessons/{lesson}/homework` | the assessment shell |
| Final exam — closed, open, and its result | `/courses/{course}/final-exam` | the assessment shell |
| Refresher | `/refreshers/{refresher}` | the assessment shell |

Two pairs share a URL by design: a completed lesson and a sat exam are *states of the screen the student is already on*, not places they navigate to.

**Outside the thirteen**, and reached from the user menu or from the sign-in flow rather than from a course: the terms page, the install-instructions page, the three settings screens (profile, security, and an appearance screen that is deliberately unreachable while dark mode is unshipped), and the seven authentication screens the starter kit provides — sign in, register, forgot and reset password, confirm password, verify email, and the two-factor challenge.

**Registration is by invitation.** The register screen's first field — above Name, holding the focus — is an invitation code. It is one shared value, read from the environment and matched exactly; an environment that sets none admits nobody rather than everybody. A wrong code fails on its own field, naming the rule it broke. Nothing else about the account differs: an invited account is on the standard plan, because the gate is who gets in, not what they spend ([1.6](specs/01-foundation.md)).

**Three shells, plus the starter kit's app layout** ([D30](02-design.md)). The pipeline shell frames a course being set up, the study shell frames being taught, the assessment shell frames being measured. The dashboard and the module rollup are not any of those and are set in the app layout. All three shells draw the same crest at the head of their rail, and it is the link back to the dashboard — the only way out of a course that is not the browser's address bar (8.5.7). Settings, auth and the install page sit outside the thirteen and use the starter kit's own chrome, and the landing page is framed by nothing at all.

## 4. The routes, by subsystem

Four route files, each owning one subsystem. Every route below appears as an edge in 8.6's flow map, and a test holds the two to each other — a route added without an edge fails the build.

**[`routes/courses.php`](../routes/courses.php)** — the pipeline. Create a course; withdraw and resume one; both interviews (each is a screen, a stream that opens the conversation, an endpoint per answer, and a completion); the syllabus screen, its generation, its revisions and its approval. The two generation POSTs are throttled, because each spends a `top`-tier call.

**[`routes/lessons.php`](../routes/lessons.php)** — the teaching loop. The lesson screen; a stream per section; a question; a highlight explanation; a checkpoint answer; completion.

**[`routes/assessment.php`](../routes/assessment.php)** — everything graded outside a lesson. Homework and its answers; the final exam, opening it, saving a sitting in progress, and handing one in; the three doors that create a refresher (lesson, subtopic, module) and the refresher screen and its answers.

**[`routes/web.php`](../routes/web.php)** — the entry route, which renders the landing page for a signed-out visitor and redirects a member to the dashboard; the dashboard, the module rollup, the terms page and the install-instructions page. It requires the other three files, plus **[`routes/settings.php`](../routes/settings.php)**, which holds profile, password, security and the passkey endpoint password managers read. **[`routes/console.php`](../routes/console.php)** defines no routes.

## 5. The records

| Table | Holds |
|---|---|
| `users` | the account, its plan string, and two-factor/passkey credentials |
| `modules`, `subtopics` | the catalog a course is created against |
| `courses` | one row per (user, subtopic, level, attempt): position, the two ending dates, the style |
| `interview_messages` | both interview transcripts |
| `course_syllabi` | one row per generation, pinned to the memory versions it was written from; one approved row per course |
| `lessons` | position, status, and the path of the guidance document — nothing the syllabus already says |
| `lesson_messages` | the lesson transcript, including checkpoint turns |
| `lesson_pipeline_steps` | which post-completion steps have run for a lesson |
| `quizzes`, `quiz_attempts` | all four kinds — checkpoint, homework, final, refresher — through one service |
| `exam_answer_sheets` | a sitting in progress ([6.3.4](specs/06-assessment.md)) |
| `generation_requests` | one row per (course, kind) — the wait for a queued generation |
| `memories`, `memory_versions` | the memory tree and a version on every write |
| `usage_events` | one row per AI call: feature, tier, model, tokens in/cached/out, latency, cost |

Every question a student meets offers four options, and where the correct one sits is decided as the quiz is written rather than by the model, which keys A or B far more often than chance ([6.6](specs/06-assessment.md)). The order is stored, so a reload does not move an option out from under an answer.

The two vocabularies worth knowing: a course's **position** stores only the seven pipeline states — `withdrawn` and `completed` are read back from the two dates ([D7d](02-design.md)) — and a quiz's **kind** is what decides its length, its disclosure rule and whether it gates anything.

## 6. What runs on the queue

Three things, and the app is broken without a worker draining them ([T4](lessons.md)).

**Drawing up a syllabus** and **writing a final exam** are `top`-tier calls that can take minutes. Neither is done inside a request: the POST answers immediately, a `generation_requests` row *is* the wait, and the screen polls every five seconds until the artefact exists. Two consequences fall out of that row. A unique index on (course, kind) makes a double press free, where before every timeout-and-retry was another bill. And failure had to become persistent, because there is no longer a response to report it in — so both jobs write a student-facing message on the row, and both screens read it on every render, not once at mount ([D28](02-design.md)).

**The lesson completion chain** — summarize, update working knowledge, generate homework, unlock the next lesson — runs as a chain after a lesson is finished. Nothing in it is allowed to undo the completion. An environment with no worker completes lessons and silently never writes their homework.

All three jobs are idempotent, because a managed queue delivers at least once.

## 7. Cost

Every AI call in the system writes a `usage_events` row, priced from the tokens it actually used, cache reads and writes included. There is no call site exempt from this and no model ID anywhere outside `config/ai.php` — call sites name a tier (`cheap`, `mid`, `top`) and the config decides what that is. The total cost of a course is therefore one SQL query.

## 8. The working cycle, and that it has been run

Everything above composes into one cycle, and the cycle is this document's reason to exist. It was v1's definition of done, and it **passed on 2026-09-07** (ledger [D29](02-design.md)):

> One student completes one honest course cycle **in a browser, and again on a phone**: creates "Language / Conversational Spanish / Intro" → two interviews → approves a syllabus → completes every lesson with checkpoints → passes the final exam → takes a refresher quiz drawing on recorded working knowledge — with the total inference cost visible in `usage_events`.
> If any step needs a developer touching the database, it has not passed.

It is the whole-system test. Each package's spec carries its own acceptance test for the journey *its* items compose into; none of them repeats this one.
