# Spec 12 — The Public Demo

*Provides: a way in for a stranger with the link, and a course short enough to finish in one sitting. Consumes: 1 (accounts, the invitation gate), 4 (depth, the pipeline), 5 (the lesson engine), 6 (homework), 8 (the flow map, the waiting states). Opened 2026-09-15, so the app can be shown to recruiters, employers and investors without an invitation code. Buildable by a cold session on its own.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D11](../02-design.md) `users.plan` is a string until it carries what a key cannot · [D25](../02-design.md) a `top`-tier generation is queued work · [D41](../02-design.md) an error names every rule the field broke · [D45](../02-design.md) entry is one shared invitation code · [D46](../02-design.md) the front door navigates in two places · [D50](../02-design.md) the demo is the real pipeline on a shorter plan · [D51](../02-design.md) `plan` keys depth, and is not yet a table.

## The shape

**A demo visitor is an ordinary user row on a `demo` plan, and nothing past the front door knows the difference.** **A demo is lesson 1 of an ordinary course** (amended 2026-09-17): the visitor sits both interviews, gets the full syllabus any student would, takes lesson 1 with real checkpoints and has real homework graded. Lesson 2 onward asks them to sign up, and signing up opens the rest of that same course, because it is the same row.

Three properties of the running system make this small, each confirmed by reading the code on 2026-09-15:

- **`users.plan` had exactly one production reader** — `SyllabusGenerator::depth()`. The demo adds three: the lesson policy (lesson 2 onward), the exam controller, and the sign-up link on S7 and S8.
- **The `verified` middleware is a no-op.** `User` does not implement `MustVerifyEmail`, so `EnsureEmailIsVerified` passes any signed-in account. A demo row reaches every screen without its email being real.

**What a demo costs.** Measured from `usage_events` on 2026-09-15, excluding the six zero-token 60-second rows the [D25](../02-design.md) incident left behind: interviews $0.08, syllabus $0.157, three sections $0.140, three checkpoints $0.022, summarize $0.064, working-knowledge update $0.151, homework $0.081 — about **$0.70 a visit**. Nothing caps it; [12.6.1](#126-out-of-scope) says why.

---

## 12.1 The entry

- **12.1.1 MUST** — A public welcome page says what the demo is, and its one button creates a `demo`-plan user, signs them in, and lands them on course creation. Both sit outside the `auth` group, the way `home` does after [8.13.2](08-interface.md). Opening the link writes nothing, so a chat app unfurling it or a crawler following it makes no account. *Done: `GET /demo` renders the page to every visitor, signed in or not, and creates no row; `POST /demo` ends at `/courses/create` with a `demo`-plan row that did not exist before it.*
- **12.1.2 MUST** — The row satisfies the schema with no special-casing. `users.email` is NOT NULL and UNIQUE and `users.password` is NOT NULL, so the row carries a unique synthetic email, a random password and a name. *Done: two visits produce two rows; neither column is made nullable.*
- **12.1.3 MUST** — Only this route mints a `demo`-plan account. *Falsified if: a registration through `CreateNewUser` produces a plan other than `standard` ([1.6.2](01-foundation.md)).*
- **12.1.4 MUST** — The routes appear as an edge in [8.6](08-interface.md#86-flow-map)'s flow map in the same commit that adds it. `FlowMapTest` asserts both directions and fails the build otherwise. *Done: `FlowMapTest` passes.*
- **12.1.5 WONT** — The demo carries no 18-or-older attestation and asks for nothing before it starts. `tos_accepted_at` stays null on a demo row, and the welcome page has one button and no field ([D50](../02-design.md), amended 2026-09-16). *Falsified if: a field, a checkbox or a second screen appears between the link and course creation.*
- **12.1.6 MUST** — Every signed-in screen a demo visitor sees carries a Demo marker in its header, linking to sign-up ([12.4](#124-conversion)). The course shells carry it in the rail and in the phone bar; the dashboard carries it in its header. *Done: the marker renders for a `demo`-plan user and not for a `standard` one.*

## 12.2 The shortened course

- **12.2.1 MUST** — `demo` joins `config('courses.plans')`. *Done: `CoursesConfigTest`'s assertion that a user's plan is in the configured set passes with a demo row.*
- **12.2.2 MUST** — `depth.{101,201,301}.demo` is the standard depth, written once in config and keyed twice. *Done: `DepthParameters::for($level, 'demo')->render()` equals the standard render at every level.*
- **12.2.3 WONT** (dropped 2026-09-16, then moot 2026-09-17) — A fourth depth parameter for a whole short course. A one-lesson syllabus read as a whole course but packed 21 concepts into one lesson against 8–12 in a standard one; the demo stopped being a short course the next day.
- **12.2.4 MUST** — No prompt file learns about the demo. *Falsified if: any file in `prompts/` gains a demo-conditional passage or a demo variable.*

## 12.3 The wait

- **12.3.1 MUST** — The generation path is unchanged. `generation_requests` is the wait, the syllabus screen polls, and the demo meets the same states every student meets. *Falsified if: any dispatch, timeout or patience value branches on plan.*
- **12.3.2 WONT** (dropped 2026-09-17, Colin) — The syllabus waiting state names what the syllabus is being written from, for every student rather than for the demo alone. **This is [8.12](08-interface.md)'s item, not this spec's** — a demo-only copy change would be a second pipeline. *Done: recorded as an amendment to 8.12.2, or dropped.*

## 12.4 Conversion

- **12.4.1 MUST** — After lesson 1, the demo's next move is signing up. S7 and S8 say "Sign up to carry on", and lesson 2 onward sends a demo visitor to sign-up on the screen and refuses on every other lesson route. *Done: a `demo`-plan lesson 1 carries the sign-up link and lesson 2 redirects to it; a `standard`-plan course does neither.*
- **12.4.2 MUST** — Conversion attaches name, email and password to the existing row and moves it to `standard`. Nothing is copied, because the row does not change. *Done: the working-knowledge document's version before conversion is the version after it.*
- **12.4.3 MUST** — Conversion requires the invitation code, exactly as registration does. [D45](../02-design.md) stands untouched. *Done: a conversion with no code, or a wrong one, leaves the row on the `demo` plan and says so on the code field ([D41](../02-design.md), [1.6.3](01-foundation.md)).*
- **12.4.4 MUST** — The course carries on. Nothing about it changes at sign-up; lesson 2 simply opens. No interview is repeated and no syllabus is redrawn. *Done: after conversion the course has no `withdrawn_at` and lesson 2 renders.*

## 12.5 The sweep

- **12.5.1 SHOULD** — A command deletes unclaimed `demo`-plan accounts older than a configured age. This is the one hard delete in the app ([D69](../02-design.md)); once other accounts soft-delete ([16.2.10](16-catalog-removal-ownership.md)) it force-deletes, and touches no other plan. *Done: deleting one removes its courses, memory tree, quizzes, attempts, answer sheets and refusals, and leaves its `usage_events` rows with a null `user_id` — the FK rules are CASCADE throughout and SET NULL on `usage_events`, so the command is a delete and nothing else.*

## 12.6 Out of scope

- **12.6.1 WONT** — No spend cap, quota or ceiling on the demo. [1.6.5](01-foundation.md) extends to it unchanged: `usage_events` records what was spent and nothing reads it back as a limit. *Falsified if: a budget check appears in the demo or generation path.*
- **12.6.2 WONT** — No final exam in the demo. The exam screen sends a demo visitor to sign-up, and asking for an exam is refused. *Falsified if: `course.final_exam` writes a `usage_events` row for a `demo`-plan user.*
- **12.6.3 WONT** — No link on the landing page. [8.13.4](08-interface.md)'s "the only two things on the page that navigate are the buttons" holds and [D46](../02-design.md) is not amended. The link is one Colin sends. *Falsified if: S0 gains a third navigating element.*
- **12.6.4 WONT** — No email capture, no social sign-in, no analytics. Social sign-in was raised with this work on 2026-09-15 and deferred by Colin as its own problem; it will need a D45 answer of its own. *Falsified if: any of the three appears.*
- **12.6.5 WONT** — No demo branch in the lesson or completion pipelines. The chain order is untouched and homework arrives when it arrives, behind a completion screen the student already has. *Falsified if: `LessonPipelineJobs::forLesson` reads a plan.*

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)). Runnable without opening another spec.*

Against the deployed URL, over TLS, in a browser that has never seen the app, with a queue worker running:

1. Open the demo link. The welcome page says what the demo is and offers one button; no form, no code, no email. Press it — the next screen is course creation, with the Demo marker in its header.
2. Type a subtopic. The catalog check runs and refuses what it would refuse for any student.
3. Sit both interviews. Press Done on each; each write-up lands and the screen moves on.
4. Ask for the syllabus. It arrives as a full course.
5. Take the lesson. Sections stream, checkpoints appear in the margin, a question answers in line.
6. Finish it. The completion state renders immediately; the homework shows its waiting state until the chain reaches it.
7. Answer the homework. "Sign up to carry on" stands where the next lesson would; opening lesson 2 goes to sign-up.
8. Sign up with no code, and with a wrong one: nothing changes and the code field says why. Sign up with the right one: the account is `standard`, and lesson 2 of the same course opens.
9. `select feature, sum(cost_usd) from usage_events where user_id = …` returns the whole cost of that visit in one query.
