# Spec 8 — Interface

*Provides: every screen the engines feed, package 4's missing HTTP layer, and the design language. Consumes: 1–6. See PLAN.md for status.*

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

- **8.2.1 MUST** — One seeder, re-runnable, producing all six states below against a known user. *Done: `php artisan migrate:fresh --seed` twice in a row yields the same six states with no unique-constraint failure.*
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
- **8.3.5 MUST** — A `<Crest/>` component: one SVG source serving the rail, the exam cover, completion states, the favicon, and package 7's icons. *Done: one source, five call sites; 7.1 imports no separate artwork.*
- **8.3.6 MUST** — Radix and the copied `components/ui` primitives stay (D24). *Falsified if:* any settled screen requires replacing a primitive's anatomy rather than restyling it.
- **8.3.7 WONT** — Storybook, component API docs, a versioned library. Tokens plus ~8 composites, then stop.

## 8.4 Material system

- **8.4.1 MUST** — Three grounds bound to purpose: paper column = reading; photocopy sheet = all practice; blue book = the final exam (D22). *Done: no practice surface renders on the paper ground, and no reading surface on the photocopy ground.*
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

## 8.7 Package 4's missing HTTP layer

*Inherited, not new: package 4 shipped engine-only, so these routes have never existed. Package 4 owns them by subject and package 8 by schedule; recorded here so nothing falls between.*

- **8.7.1 MUST** — Course creation: catalog browse, free-text creation, skill-level choice, over `CreateCourse::fromCatalog` / `::fromFreeText`. *Done: a course row exists after one pass with no developer touching the DB.*
- **8.7.2 MUST** — Knowledge interview: streamed conversation with an always-visible Done button, over `Interviews::knowledge`. *Done: the transcript survives a reload; Done writes the WK document.*
- **8.7.3 MUST** — Goals interview, same shape, over `Interviews::goals`. *Done: as 8.7.2, writing CRITERIA.*
- **8.7.4 MUST** — Syllabus approve/revise (4.5.4) including the revision request field. *Done: approve advances the course; revise regenerates against the typed request.*
- **8.7.5 MUST** — Level-mismatch banner (4.5.7) when `subtopic_mismatch` is non-null, non-blocking. *Done: a mismatched course still approves.*

## 8.8 Lesson screens

- **8.8.1 MUST** — Lesson screen consuming `LessonState`. *Done: reopening restores section and transcript (5.3).*
- **8.8.2 MUST** — Streaming render off the five events (`turn.start`, `turn.delta`, `turn.end`, `checkpoint.ready`, `stream.error`), painting section title and "N of M" from `turn.start` before the first token. *Done: the header is populated while the body is still empty.*
- **8.8.3 MUST** — Checkpoint as the margin question; at ≤767px it reduces to P1 — the hand becomes the question's lead-in line, brass rule beside the options (D23). *Done: at 390px the checkpoint is distinguishable from body prose without a container.*
- **8.8.4 MUST** — Selection explainer over `POST /lessons/{}/explanations`, warning at the 500-character cap from `limits` while the selection is being dragged. *Done: an over-cap selection is refused client-side before a request is sent.*
- **8.8.5 MUST** — Q&A composer honouring `limits.question_characters`. *Done: the counter reads the server's number, never a duplicated constant.*
- **8.8.6 SHOULD** — Lesson completion state. *Done: completing advances the course and the rail updates without a reload.*

## 8.9 Assessment screens

- **8.9.1 MUST** — Homework on the photocopy sheet, explanation revealed only after answering. *Done: the pre-answer DOM contains no `explanation` or `correct_option_id`.*
- **8.9.2 MUST** — Final exam: closed book → opened, sat whole, handed in once. *Done: no per-question feedback appears before hand-in (6.3, D20).*
- **8.9.3 MUST** — Exam result and the gate it holds, stating the 70% mark. *Done: pass and fail each render; a re-sit reopens the same exam.*
- **8.9.4 MUST** — Refreshers at all three grains on the photocopy sheet. *Done: lesson, subtopic and module grains render from one component.*

## 8.10 Course surfaces

- **8.10.1 MUST** — Dashboard replacing the starter-kit placeholder: courses in progress, next action per course. *Done: `placeholder-pattern.tsx` has no remaining import.*
- **8.10.2 SHOULD** — Module rollup view over `ModuleRollup`. *Done: a completed course's knowledge appears under its module.*

## 8.11 Amendments to existing plan documents

- **8.11.1 MUST** — Spec 7 shrinks to its literal text; its done-condition drops "Lighthouse installable" and names the manifest fields plus a real-device add-to-home-screen check. *Done: 7.1 names no check that no longer exists.*
- **8.11.2 MUST** — Spec 7 records browser-menu install only, and WONTs both the in-page install button and the service worker. *Done: 7.1 names one install path.*
- **8.11.3 MUST** — PLAN.md's global acceptance test becomes "in a browser, and again on a phone"; package 8 gains a status row; the dependency graph gains its node. *Done: all three edits present.*
- **8.11.4 MUST** — The design phase's "real generated course as mockup content" condition is dropped, recorded as met-by-substitute: mockup content is schema-faithful, not engine-produced. *Falsification moves to the dogfood cycle: if real output breaks a layout the mockups predicted would hold, the substitution was wrong.*
- **8.11.5 MUST** — PLAN.md's dependency graph moves package 7 to depend on 1 **and** 8, and drops it from the parallel-lane candidates. *Done: no path in the graph opens 7 before 8 lands.*
- **8.11.6 MUST** — PLAN.md's ejection order gains package 7, because the amended acceptance test is satisfied by a phone browser and needs no install. *Done: the ejection list is non-empty again.*
