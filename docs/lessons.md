# OpenTutor — traps and postmortems

*Durable tier (ledger [D27](02-design.md)). Required by spec 9.5. What this file owns: **tooling traps** — behaviour of Laravel, Vite, the AI SDK or the host that cost a session and will cost the next one — and **incident postmortems**, where something reached a student or a bill before it was found.*

**What does not belong here.** A rule goes in the spec that sets it, a decision goes in the ledger, a runbook step goes in [`docs/deploy.md`](deploy.md), and what a session tried before it found the answer goes in that unit's plan file and is deleted with it. An entry here earns its place by being a thing you can walk into again.

---

## T1. `withoutVite()` in `TestCase::setUp` — the stale-manifest trap

*Found 2026-09-01, in the 8.8 session.*

An Inertia screen test renders `app.blade.php`, which resolves `@vite` against `public/build/manifest.json`. So **every screen test 500s the moment that manifest is older than the page under test** — which it is for the whole of any session that adds a screen, until someone runs a build.

The error names a Vite manifest and looks nothing like the stale build it actually is. Two sessions can lose an hour to it before the penny drops.

**The fix is standing:** `tests/TestCase.php`'s `setUp` calls `withoutVite()`. No test in the suite renders a built asset, so nothing is lost. Verified with `public/build` deleted outright.

CI was never affected — `composer setup` builds before `composer ci:check` — which is exactly why this stayed a local-only trap for as long as it did.

## T2. `fakeModel()`, not `GatewayAgent::fake()`

*Found 2026-08-25, in the 4.5 session.*

`laravel/ai` keys its fakes on the **exact agent class**. Faking `GatewayAgent` alone therefore leaves `StructuredGatewayAgent` unfaked, so **structured calls reach the live API from the test suite** — a real bill, real latency, and a green test that proves nothing about the fixture it claims to use.

**Use `TestCase::fakeModel()`** for anything with a `json/` output. It fakes both classes.

## T3. `install:features` stripped package 1's auth scaffolding

*2026-08-23. Resolved; recorded because the shape recurs.*

Installing `laravel/ai` fired the starter kit's one-shot `install:features` command through Composer's `post-update-cmd`. Running non-interactively it took defaults, stripped package 1's passkey and 2FA scaffolding, and then — by its own design — deleted itself, so the cause was gone before the damage was noticed.

**Not a standing hazard: it cannot fire again.** The command and its `chisel.php` self-cleanup block were deleted 2026-08-23.

`chisel.php`, `chisel-paths.php`, the `laravel/chisel` dependency and the `/* @chisel-* */` markers in eight package-1 files are unreachable now — nothing requires them. Left in place; removing them is package-1 tidying, not a fix.

**The general shape, which is the reason this entry exists:** a starter kit's `post-update-cmd` runs on every `composer require`. Read what a starter kit installs into that hook before adding a dependency.

## T4. Production had no queue worker and no API key

*2026-09-02, first deploy that met a student. Ledger [D25](02-design.md); the runbook that now prevents it is [`docs/deploy.md`](deploy.md) steps 6 and 8.*

Two omissions, one deploy, two very different symptoms.

**No `ANTHROPIC_API_KEY`.** Nothing injects it, so the app deployed clean and died at the first interview.

**No queue worker.** The runbook said to add no background process *"until there is work for them"* — a sentence written before package 5, which has dispatched `ShouldQueue` jobs since. An environment with no worker **completes lessons and silently never writes their homework**: no error, no failed job, no student-visible symptom until the homework screen waits forever.

The lesson is the second one. A "not needed yet" note in a runbook has no expiry date and nobody rereads it; by the time anyone did, the work had existed for two packages. **A runbook step that is conditional on the future is a defect.** `docs/deploy.md` now states the queue as required and names what breaks without it.

## T5. The final exam could not be sat, and never could have

*Found 2026-09-04 on the first real course; repaired 2026-09-06 as spec items 6.3.1–6.3.5 and 8.9.5. Decisions in ledger [D26](02-design.md) and [D34](02-design.md).*

**What happened.** The first real course reached its final exam and could not sit it. Pressing "Open the book" dispatched `WriteFinalExam`, which spent about 90 seconds and $0.16–0.24 per attempt, twice per press, and refused the exam both times. The refusal was in the logs:

> `` `exam.final` did not produce a usable exam in 2 attempts: It asks nothing about [medieval-period-boundaries, early-middle-ages, … 44 slugs] ``

**Why it could never have worked.** The coverage rule was **arithmetically unsatisfiable**. `FinalExam::uncovered()` refused any exam unless every slug in the syllabus's `final_exam_concepts` was some question's `concept_tag`. A question carries **exactly one** tag, so coverage can never exceed the question count — and course 1 declared 44 exam concepts against a 12-question ceiling. No model output could have passed, at any price, for any course whose syllabus declared more exam concepts than the exam had questions.

**It shipped because every test fixture declares three exam concepts.** With three required and twelve allowed, the rule passes easily and looks correct. Spec 8.2.9 had predicted exactly this in writing — *the fixture is a stand-in for a course, and a stand-in that is smaller than the real thing hides the rules that only bind at real size.* The repair's 6.3.5 is a regression guard at course size for that reason: 8 lessons, 40+ declared concepts.

**A second, independent defect hid the first.** `WriteFinalExam` did record a student-facing message on the `GenerationRequest` row, and `FinalExamController` did pass it to the screen as a `failure` prop — but `final-exam.tsx` copied that prop into local state at mount, and the five-second poll leaves the component mounted. So the message never reached the student: the button simply re-enabled, in silence, and the only symptom of a $0.20 failure was nothing happening. That is the first known instance of the mounted-component class, now ledger [D28](02-design.md).

**Three lessons worth carrying.**

1. **A rule whose satisfiability depends on the ratio of two independent numbers needs a test at realistic values of both.** Every fixture in the suite sat on the safe side of the ratio.
2. **A failure surface that is never exercised is not a failure surface.** The message existed, was written, was passed to the screen, and was invisible.
3. **Cost makes a silent failure expensive.** Two `top`-tier attempts per press, and a student presses again because nothing told them not to.
