# Harkness Academy — what the app does

*Durable tier (ledger [D27](02-design.md)). Required by spec 9.2. This document describes the **running system**: what a student can do, in what order, and which part of the app answers. The specs in [`docs/specs/`](specs/) say what must be true per package; this says what happens between creating a course and passing its final exam.*

**It deliberately names no classes, no file paths and no method signatures.** Those live in the code, where a rename costs nothing. If a sentence here would need editing because something was renamed with no change in behaviour, that sentence is a defect.

---

## 1. The shape of the thing

A student picks a **subtopic** to learn at a **skill level** (101, 201, 301). The app interviews them twice, writes a syllabus for them, and then teaches it lesson by lesson, checking understanding as it goes. A final exam gates completion. Everything the app learns about the student accumulates in a per-subtopic **working knowledge** document that outlives any one course.

Three vocabularies run through the whole system and are worth holding before reading on:

- **The catalog** is `module → subtopic`. A module is a broad field (`language`); a subtopic is a thing you can take a course in (`spanish`). A student may have **one open course per subtopic**, at one level at a time. A name a student types becomes a subtopic row too, shared by everyone who types the same name, and stays out of the picker until an admin **promotes** it.
- **The memory tree** is a virtual filesystem in Postgres, keyed by path. `{module}/{subtopic}/WORKING_KNOWLEDGE.md` is the durable record of what a student knows, and it is scoped to the *subtopic* — a 201 course starts from what the 101 course taught. Under it, each attempt at a level has its own folder, `{level}/a{attempt}/`, holding that course's documents: `COURSE_CRITERIA.md` (what this student wants), one `GUIDANCE.md` per lesson (what the tutor is to teach), and a `SUMMARY.md` per finished lesson. So a second attempt never overwrites the first. Every document belongs to something: working knowledge to its subtopic, the rest to its course, and every version records the course that wrote it. All of it is model-facing prose; nothing the app queries lives here.
- **Concept slugs** are the connective tissue. The syllabus declares them, checkpoints tag questions with them, lesson summaries report on them, and exams and refreshers draw on them. A slug that was never declared is refused wherever it appears.

## 2. The course lifecycle, end to end

A course moves through seven positions, and two endings that are dates rather than positions.

**1. Created.** The student names a subtopic — from the catalog or as free text — and a level. The course row exists and nothing else does.

A typed name is checked against the module it was typed under **before anything is written**, so the picker can now refuse. A subtopic this app teaches under another module is turned away with an offer to start it there in one press; one nothing here teaches is declined outright; and a check that cannot be run declines too, rather than starting a course it could not vet. There is no override. A name that matches a starting point of the module it was typed under is not checked at all, and neither is the catalog path. A starting point is a subtopic the picker lists; a typed name someone else already used is still checked — a preset belongs to its module by construction, so picking one still works while the check does not. The student reads the app's own words for all three refusals; what the check itself said is recorded and never shown.

**2. Knowledge interview.** The tutor opens the conversation and asks about what the student already knows, budgeted at five questions — after the fifth the tutor is asked, on every turn, to wrap up and end the interview, but nothing forces it to. **The student is what bounds an interview**: a Done button is always visible and ends it at any point. **A turn that fails never strands the student.** The screen reloads, so it lands wherever the course now stands. If nothing moved, the unanswered answer goes back in the box, and sending it again replaces the lost one. Tutor text already on screen stays until the server's copy replaces it. On completion the transcript is summarized into that subtopic's `WORKING_KNOWLEDGE.md` on the queue, folding in whatever was already there rather than overwriting it. The screen says it is writing up, takes no more answers, and moves on when the document lands. A write-up that fails leaves the interview as it was, and Done tries again.

**3. Goals interview.** The same, budgeted at four questions — one for each section of the document they become: what they hope to gain, what drew them to the subject, what success would look like, how they like to be assessed. On completion the transcript becomes `COURSE_CRITERIA.md` for this level.

**4. Syllabus draft.** The student asks for a syllabus and the app generates one from working knowledge, criteria, the level, and depth parameters. It is a structured document: a course title, an overview, five to ten lessons each with an objective, sections, declared concepts and a homework focus, plus the concepts the exam should weight. It is a **draft row**, pinned to the exact versions of the two memory documents it was written from.

**5. Syllabus revision.** The student can approve or send free-text feedback and get a new draft, up to three times, after which approve-or-restart is the only move. A failed revision leaves the previous draft standing.

**6. Active.** Approving the syllabus does four things in one transaction: writes the lesson rows (the first unlocked, the rest locked), renders a `GUIDANCE.md` into the memory tree for each lesson, stamps the approved draft, and moves the course to active. From here the course is teachable.

Each lesson is taught in **sections**, paced by code rather than by the model. The tutor streams a section's prose; the student can ask questions in line, or highlight a passage and ask for it to be explained without that exchange joining the lesson transcript. After each section a **checkpoint** appears in the margin: a short multiple-choice question generated from what was just taught, and marked in code. It is written on the queue once the prose ends, so it arrives a moment after the section, and Continue never waits for it. Checkpoints **fail open**: if one has not arrived within two minutes, the margin says there is no question this time and offers Try again, and the lesson carries on.

Finishing a lesson is irrevocable, and everything after it runs on the queue: the lesson is summarized, working knowledge is updated from that summary, **homework** is generated, and the next lesson is unlocked. Homework is a short quiz plus suggested reading; its results feed the *next* working-knowledge update.

**7. Final exam.** When every lesson is complete the student can open the exam. It asks **two questions per lesson**, is written across every lesson the syllabus teaches, and discloses nothing — no answer key travels to the browser, and the whole paper is sat and handed in at once. Selections are saved as they are made, so a half-finished sitting survives a reload. Each sitting has its own sheet, kept after hand-in; a re-sit starts on a new, empty one. Passing (70%) stamps the course completed; failing records the sitting, leaves the course active, and the student can study and re-sit the same exam.

**The two endings.** `completed` and `withdrawn` are dates on the course row, not positions in the pipeline. A withdrawn course keeps everything it wrote — the working knowledge especially, which is true whatever happened to the course — and the student may take that level again as a new attempt, or pick any attempt they set aside back up while nothing else in its subtopic is open. Starting a subtopic again with attempts set aside at that level asks whether to continue one — which, when there are several — or start from scratch.

**Afterwards.** A completed subtopic can be revised with **refresher quizzes** at three grains: one lesson, one subtopic, or a whole module. The module grain walks a derived rollup of every subtopic under it that has working knowledge, so it draws on courses the student took months apart.

## 3. The screens, and what frames each

Fifteen screens. The full transition map — every edge, every URL, the resume table and the failure paths — is **[spec 8.6](specs/08-interface.md#86-flow-map)**, and is not repeated here.

**The front door** is what a signed-out visitor meets at `/`: the crest, the wordmark and the motto as one object, the headline, what the app does in a sentence of lifecycle, and two buttons — Register and Log in. Nothing else on it navigates: no bar, no footer, no third link. A member never sees it, because `/` redirects them to the dashboard.

| Screen | URL | Frame |
|---|---|---|
| Landing page | `/` | no shell, signed out only |
| Demo welcome | `/demo` | no shell |
| Demo sign-up | `/demo/account` | the auth layout, demo accounts only |
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

**Outside the fifteen**, and reached from the user menu or from the sign-in flow rather than from a course: the terms page, the install-instructions page, the three settings screens (profile, security, and an appearance screen that is deliberately unreachable while dark mode is unshipped), and the seven authentication screens the starter kit provides — sign in, register, forgot and reset password, confirm password, verify email, and the two-factor challenge. Forgot password emails a reset link from `noreply@harkness.academy`, the only email the app sends ([spec 18.1](specs/18-email-and-google-sign-in.md)).

**Registration is by invitation.** The register screen's first field — above Name, holding the focus — is an invitation code. It is one shared value, read from the environment and matched exactly; an environment that sets none admits nobody rather than everybody. A wrong code fails on its own field, naming the rule it broke. Nothing else about the account differs: an invited account is on the standard plan, because the gate is who gets in, not what they spend ([1.6](specs/01-foundation.md)).

**The demo is the first lesson of an ordinary course** ([spec 12](specs/12-demo.md), [D50](02-design.md)). It is how someone with no invitation code sees the app. The demo link opens a welcome page that says what the demo is, with one button; opening the link creates nothing. Pressing the button ends any session the browser had, creates a guest account on the `demo` plan, and lands on course creation. From there the visitor meets exactly what a student meets — both interviews, a full syllabus, lesson 1, its homework — with a Demo marker in every header linking to sign-up. Lesson 2 onward and the final exam send them to sign-up instead. Signing up is the register form onto the same account, invitation code and 18-or-older attestation included: the account moves to the standard plan and the course carries on, lesson 2 now open. Guest accounts nobody signed up are deleted after seven days by a daily command; what they cost stays recorded. That is the only thing the app deletes. A student who deletes their own account from the profile screen is kept: nothing under the account is touched, it can no longer sign in, and its email cannot be registered again.

**Four shells, plus the starter kit's app layout** ([D30](02-design.md), [D61](02-design.md)). The pipeline shell frames a course being set up, the study shell frames being taught, the assessment shell frames being measured, and the admin shell frames administering. The dashboard and the module rollup are not any of those and are set in the app layout. Every shell draws the same crest at the head of its rail, and it is the link back to the dashboard — the only way out of a course that is not the browser's address bar (8.5.7). Settings, auth and the install page sit outside the fifteen and use the starter kit's own chrome, and the landing page is framed by nothing at all.

**The admin area** ([spec 13](specs/13-admin-foundation.md)) is for accounts whose role is `admin`, and is reached from the user menu. Role is granted only from the server's command line, and never reads plan or skill level: an admin is also a student. `/admin` asks for a second factor, a passkey or a confirmed two-factor code, and sends an admin who has neither to set one up. Its rail lists seven screens: **Users**, **Courses**, **Catalog**, **Prompts**, **Models**, **Usage** and **Log**. **Users** ([17.1](specs/17-admin-screens.md)) is the admin's home: every account, including ones their students deleted, with its plan, role, course count and cost, searchable and filtered by plan. An account's page shows its courses, removed ones marked; what it cost by feature; and its memory tree, read-only, each version naming the course that wrote it and any admin who was acting as the student. It is also where a plan is changed, to another plan the app knows, logged; where an account its student deleted is restored, with a reason, logged, and signs in again with everything as it was; and where viewing as a student starts. **Courses** ([17.2](specs/17-admin-screens.md)) lists every course with its student, level, attempt, standing and cost, removed ones included, filtered by standing, level and module. A course's page takes it apart: its criteria and working knowledge, every syllabus drawn and the feedback behind each, every lesson with what was said in it section by section, every checkpoint, homework, exam and refresher with its answer key and each sitting marked as the student's screen marked it, its generation requests and why any failed, and its cost and usage rows. A course can be removed there, with a reason, and restored there if that removal was its own; a course hidden by its subtopic's or module's removal says so and comes back from Catalog. Cost everywhere in admin leaves out calls an admin made acting as a student. Calls from before usage rows named their course belong to no course, and an account's page shows their sum on its own line ([D71](02-design.md)). **Usage** ([17.3](specs/17-admin-screens.md)) looks at a date range, thirty days by default, and counts act-as calls only when asked. It has three views. The overview is cost by day, by feature and by plan; calls from before rows kept the plan are counted as unrecorded. Performance is one row per prompt and model, with its tier: calls, median and 95th-percentile latency and mean tokens over the calls that were answered, the share of input read from cache, cost per call, and how often calls failed or failed over. Events is every call, newest first, filtered by feature, model and outcome, each linking to its student, its course and its lesson. **Catalog** ([16.1](specs/16-catalog-removal-ownership.md), [16.5](specs/16-catalog-removal-ownership.md)) has four views. **Modules** is every module and subtopic, the ones students typed included, each subtopic with its course count. Removing a module or subtopic asks why, says what it will hide, and hides it from everyone with its courses and their documents; a subtopic can be retitled there too, which changes what the picker shows and moves no document. **Removed** lists every removal still in effect, courses included, with who removed it, when, why and how many courses it hides. Restoring one asks why and brings back exactly what it hid; it is refused, saying why, while a parent is removed or another course holds the subtopic. Every removal, restore and refusal is logged. **Demand** lists the subtopics students have typed that the picker does not offer, with how many courses and students each has had and when it was first and last started. Promoting one lists it for everyone, optionally under a better title, and is logged; its folder name, and so every existing course's documents, stays where it was. **Refusals** lists every name the scope check turned away, with the model's own reason, which no student sees. **Prompts** ([15.2](specs/15-prompt-registry.md)) lists every prompt the app can send with its active version, where that version came from, its tier, and the calls and cost it served over the last seven days; opening one shows its contract — the declared output and variables, which come from the prompt file and cannot be edited — above the body and tier, which can. A save is a new version and is logged. Its history lists every version with who saved it, any two can be compared line by line, and any can be put back into service — a rollback writes no version and is logged like a save, and it cannot reach a tier a save would be refused. **Models** ([14.4](specs/14-routing-and-usage.md)) shows the catalog — every model a call can reach, its provider, its four per-million-token prices, and the calls and cost it served over the last seven days — above the three tiers with their backups and timeouts. Prices are the one thing editable there: an edit is logged and reaches later calls only, because a call's cost is stamped when its row is written. **Log** is every admin write, newest first, filterable by who, what and about whom, and not editable by any path.

**Viewing as a student** ([D60](02-design.md)) starts as a **preview**. The admin sees every screen exactly as the student would, under a green banner naming them. Nothing can be written: every press that would save something is refused, no AI call starts, and a lesson shows only what was already taught. **Stepping up** asks for a reason and turns the banner wine. From then on, answers and AI calls go through as the student's, and each usage row and memory version they cause records the admin, queued work included. The admin screens are closed until Exit. In either mode the student's sign-in settings — password, two-factor and passkeys — stay closed too, and opening them returns the admin to the student's dashboard; their email can't be changed either, since password resets go there. Start, step-up and exit are each logged. A view ends at Exit, at logout, or after an hour.

## 4. The routes, by subsystem

Five route files, each owning one subsystem. Every route below appears as an edge in 8.6's flow map, and a test holds the two to each other — a route added without an edge fails the build.

**[`routes/courses.php`](../routes/courses.php)** — the pipeline. Create a course; withdraw and resume one; both interviews (each is a screen, a stream that opens the conversation, an endpoint per answer, and a completion); the syllabus screen, its generation, its revisions and its approval. The two generation POSTs are throttled, because each spends a `top`-tier call.

**[`routes/lessons.php`](../routes/lessons.php)** — the teaching loop. The lesson screen; a stream per section; a question; a highlight explanation; a checkpoint answer; completion.

**[`routes/assessment.php`](../routes/assessment.php)** — everything graded outside a lesson. Homework and its answers; the final exam, opening it, saving a sitting in progress, and handing one in; the three doors that create a refresher (lesson, subtopic, module) and the refresher screen and its answers.

**[`routes/admin.php`](../routes/admin.php)** — the admin area: users, an account's page and changing its plan; courses, a course's page and removing it; usage, its performance and every call; the subtopic catalog — its modules and subtopics, removing and restoring them, demand and refusals — the model catalog and its prices, the log, and starting, stepping up and leaving a view of a student. One gate and the second-factor check guard all of it, and every write it takes leaves one log row.

**[`routes/web.php`](../routes/web.php)** — the entry route, which renders the landing page for a signed-out visitor and redirects a member to the dashboard; the demo's welcome page, the button that starts it, and its sign-up; the dashboard, the module rollup, the terms page and the install-instructions page. It requires the other four files, plus **[`routes/settings.php`](../routes/settings.php)**, which holds profile, password, security and the passkey endpoint password managers read. **[`routes/console.php`](../routes/console.php)** defines no routes.

## 5. The records

| Table | Holds |
|---|---|
| `users` | the account, its plan (`standard`, or `demo` until a demo visitor signs up, or whichever an admin sets), its role (`student` or `admin`), and two-factor/passkey credentials; soft-deleted when the student deletes it |
| `modules`, `subtopics` | the catalog a course is created against; a subtopic is `catalog` or `student` by origin, and `listed` when the picker offers it |
| `courses` | one row per (user, subtopic, level, attempt): its subtopic row, and beside it the module and subtopic slugs frozen at creation that name its documents; position, the two ending dates, the style |
| `interview_messages` | both interview transcripts, kept for the life of the course |
| `course_syllabi` | one row per generation, pinned to the memory versions it was written from; one approved row per course |
| `lessons` | position, status, the path of the guidance document, and the model its session started on — nothing the syllabus already says |
| `lesson_messages` | the lesson transcript, including checkpoint turns |
| `lesson_pipeline_steps` | which post-completion steps have run for a lesson |
| `quizzes`, `quiz_attempts` | all four kinds — checkpoint, homework, final, refresher — through one service |
| `exam_answer_sheets` | one per sitting: what was ticked, kept after hand-in ([6.3.4](specs/06-assessment.md)) |
| `generation_requests` | every queued generation a course asked for, with its state — `open`, `landed`, `failed`, `expired` or `cancelled`; at most one open per (course, kind) |
| `memories`, `memory_versions` | the memory tree, each document with its owner, and a version on every write naming the course that wrote it and the admin if one was acting as the student |
| `usage_events` | one row per AI call: feature, tier, the catalog model that served it, the prompt version whose text was sent, tokens in/cached/out/reasoning, latency, cost, outcome (`ok`, `failed`, `failed_over`), the course and lesson it served, the student's plan at the time (null on rows written before [14.3.4](specs/14-routing-and-usage.md); no backfill), and the acting admin if there was one |
| `ai_models`, `ai_tiers` | the model catalog with each model's prices, and `cheap`/`mid`/`top` as rows: a primary, ordered backups, a timeout |
| `prompts`, `prompt_versions` | the 18 prompts of the [2.3.2](specs/02-ai-layer.md) inventory, and every version of each that has run. A version is never updated |
| `admin_actions` | one row per admin write: who, what, about whom, why. Nothing can update or delete a row |
| `removals` | one per admin removal of a module, subtopic or course, pointing at its log row. Every row it hides — in `modules`, `subtopics`, `courses` and `memories` — carries its id and a `deleted_at`, so restoring it brings back exactly those rows ([16.2](specs/16-catalog-removal-ownership.md)) |

Every question a student meets offers four options, and where the correct one sits is decided as the quiz is written rather than by the model, which keys A or B far more often than chance ([6.6](specs/06-assessment.md)). The order is stored, so a reload does not move an option out from under an answer.

The two vocabularies worth knowing: a course's **position** stores only the seven pipeline states — `withdrawn` and `completed` are read back from the two dates ([D7d](02-design.md)) — and a quiz's **kind** is what decides its length, its disclosure rule and whether it gates anything.

## 6. What runs on the queue

Five things, and the app is broken without a worker draining them ([T4](lessons.md)).

**Drawing up a syllabus** and **writing a final exam** are `top`-tier calls that can take minutes. Neither is done inside a request: the POST answers immediately, a `generation_requests` row records the wait, and the screen polls every five seconds until that row is no longer open. Two consequences fall out of that row. Only one request per (course, kind) may be open, which makes a double press free, where before every timeout-and-retry was another bill. Every request is kept once it is over, with how it ended. And failure had to become persistent, because there is no longer a response to report it in — so both jobs write a student-facing message on the row, and both screens read it on every render, not once at mount ([D28](02-design.md)).

**Writing a checkpoint** and **writing up an interview** are queued for a different reason: they are silent. Each is a non-streamed call that used to run at the end of a streamed response, after the tutor's last word, and the host cuts a response that has said nothing for 60 seconds ([T10](lessons.md), spec 11). They use the same `generation_requests` row. A checkpoint's wait runs out at two minutes, because it fails open ([D48](02-design.md)); one that ends without a question is recorded as failed, not landed. An interview's write-up waits as long as it takes, because it fails closed.

**The lesson completion chain** — summarize, update working knowledge, generate homework, unlock the next lesson — runs as a chain after a lesson is finished. Nothing in it is allowed to undo the completion. An environment with no worker completes lessons and silently never writes their homework.

Every job is idempotent, because a managed queue delivers at least once. And every job looks its course up when it starts: a course an admin has removed is not found, so the job exits having spent nothing, and the removal itself closes whatever the student was waiting on. A course removed while the model is answering saves nothing from that answer, though the call is still on the bill. The student meets the removal on their next screen of that course: the dashboard, with a message naming it.

## 7. Prompts

A prompt's contract and its text have different owners. `php artisan prompts:sync` reads `prompts/` on every deploy and writes each prompt's declared output and variables — the contract the calling code depends on, which no editor may change. A file's **body** is read exactly once, to seed version 1 of a prompt that has none; after that the editor owns the body and the tier, and every save is a new, immutable `prompt_versions` row that becomes the active one. So no deploy can change what a prompt says, and there is nothing to reconcile between the two halves (ledger [D65](02-design.md)). Nothing writes the other way: the files are starting text and may lag what runs, and an edit reaches the repo only when it is copied there by hand. A file that adds a variable the running text does not use is accepted — sync names it, Admin → Prompts marks it, and calls run without that material until the text is edited. Every usage row names the version whose text was sent, so what a call cost can be read back against exactly the words that produced it.

## 8. Cost

Every AI call in the system writes a `usage_events` row, priced from the tokens it actually used, cache reads and writes included. There is no call site exempt from this and no model ID in code — call sites name a tier (`cheap`, `mid`, `top`), the tier's row names a model in the catalog, and the call is priced at that model's prices when its row is written. A lesson stays on the model it started on, even if its tier is moved to another one partway through. The total cost of a course is therefore one SQL query. Every call names the course or lesson it serves, except the subtopic check, which runs before a course exists. The row keeps the student's plan, so what demo accounts cost can still be counted after the sweep deletes them.

## 9. The working cycle, and that it has been run

Everything above composes into one cycle, and the cycle is this document's reason to exist. It was v1's definition of done, and it **passed on 2026-09-07** (ledger [D29](02-design.md)):

> One student completes one honest course cycle **in a browser, and again on a phone**: creates "Language / Conversational Spanish / Intro" → two interviews → approves a syllabus → completes every lesson with checkpoints → passes the final exam → takes a refresher quiz drawing on recorded working knowledge — with the total inference cost visible in `usage_events`.
> If any step needs a developer touching the database, it has not passed.

It is the whole-system test. Each package's spec carries its own acceptance test for the journey *its* items compose into; none of them repeats this one.
