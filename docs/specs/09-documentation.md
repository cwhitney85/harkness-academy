# Spec 9 — Documentation System

*Provides: the tier discipline every later session reads under. Consumes: nothing. Settled in the 2026-09-07 planning session (Colin); candidates and rationale in [D27](../02-design.md).*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D27](../02-design.md) documents are tiered by lifetime · [D29](../02-design.md) an acceptance test lives in a spec · [W1](../02-design.md) planning docs are in-repo · [W2](../02-design.md) per-package specs, amended by D27 · [W5](../02-design.md) the `/package` command.

**The three tiers.** Every document in this repo is one of them, and a fact has exactly one owner.

| Tier | Loaded | Documents |
|---|---|---|
| **Unconditional** | every session | `CLAUDE.md` |
| **Durable, addressable** | when targeted | `docs/system.md`, `docs/02-design.md`, `docs/specs/*.md`, `docs/lessons.md` |
| **Disposable** | during one work unit | `docs/plans/<unit>.md` |

`README.md` is the human entry point and owns the roadmap. `PLAN.md` is retained under 9.7.1 and scheduled for retirement.

## 9.1 The tiers are declared

- **9.1 MUST** — `CLAUDE.md` states the three tiers and places every document in one, so a cold session knows what to load and a later session knows where a new fact belongs. *Done: CLAUDE.md names each document in the table above with its tier; a reader can place a new fact in exactly one document without guessing.*
- **9.1.1 MUST** — `CLAUDE.md` carries the standing convention **every fact has exactly one owner** — a fact found in two documents is a defect in whichever is not the owner. *Done: the convention is stated; each of `arithmetically unsatisfiable`, `InterfaceFixtureSeeder`, `8.11.4` and `answer sheet` resolves to one non-code owner, the others holding a pointer or nothing.*
- **9.1.2 MUST** — `CLAUDE.md`'s "Before writing any code" section and `.claude/commands/package.md` name the same read path: CLAUDE.md → the package's spec → the ledger entries that spec's head points at. *Done: neither mentions PLAN.md.*

## 9.2 `docs/system.md` — what the app currently does

No document describes the running system. The specs state what must be true per package; none of them says what happens between creating a course and passing its final exam.

- **9.2 MUST** — A durable description of the running system: the course lifecycle end to end, the routes grouped by subsystem, the screens and which shell frames each, the models, and the queue-backed generations. *Done: a reader who has never seen the repo can name what happens between "create a course" and "pass the final exam" without opening a spec; every route file and every page component is reachable from it.*
- **9.2.1 SHOULD** — It carries the flow map 8.6 defines rather than restating it. *Done: the flow map appears in exactly one of `docs/system.md` and `docs/specs/08-interface.md`; the other points at it.*
- **9.2.2 WONT** — It does not describe implementation. Class names, file paths and signatures stay in the code. *Falsified if: any statement in it would need editing because a class was renamed with no behaviour change.*

## 9.3 The ledger absorbs PLAN.md's decisions

PLAN.md's Status section is the sole non-code home for the architecture's rationale. `AssessmentShell`, `PipelineShell`, `NextAction`, `ResumeRefusal`, `LessonRail`, `Worksheet`, `lastSitting`, `AppLayout`, `rail.tsx` and "the third shell" each appear there once, in the code, and in no spec and no ledger entry.

- **9.3 MUST** — Every decision-with-rationale in the Status section becomes a ledger entry numbered from D27, each naming the package and spec item it arose under. *Done: those ten identifiers each appear in a ledger entry; PLAN.md is no longer the sole non-code home for any of them.*
- **9.3.1 MUST** — The ledger gains a table of contents: entry number, one-line subject, current-or-superseded. *Done: a session can identify which entries it needs from the TOC alone, without reading an entry body.*
- **9.3.2 SHOULD** — Superseded entries stay inline at full length with a first-line status marker naming what superseded them. *Done: D7a, D7b and D7c each open with a marker pointing to D7d; a reader wanting only the current rule reads D7d alone.*
- **9.3.3 MUST** — Implementation description and verification records are discarded, not relocated. *Done: `390px iframe` appears zero times outside the spec that sets the rule; no document contains a sentence whose only content is that a screen was verified at a viewport size.*

## 9.4 The ledger pointers move to the spec heads

PLAN.md holds 28 of the repo's 32 ledger cross-references; the eight spec files hold 4 between them. Retiring PLAN.md without relocating them leaves the ledger unreachable from the specs it governs.

- **9.4 MUST** — Each spec file gains a decisions block at its head listing the ledger entries that bind it. *Done: the 28 cross-references resolve from spec heads; deleting PLAN.md leaves no ledger entry unreachable from the spec it governs.*
- **9.4.1 SHOULD** — One block format across all nine specs. *Done: every spec head follows the same shape.*

## 9.5 `docs/lessons.md` — traps and postmortems

- **9.5 MUST** — A durable document for tooling traps and incident postmortems, seeded with the three facts PLAN.md solely owns: the `withoutVite` stale-manifest trap, the `install:features` incident, and the deploy's queue-worker and `ANTHROPIC_API_KEY` requirements. *Done: `withoutVite` and `public/build` appear in `docs/lessons.md`; deleting PLAN.md loses neither.*
- **9.5.1 MUST** — The final-exam repair postmortem moves here, and 6.3.1–6.3.5 keep only their rules and done-conditions. *Done: `arithmetically unsatisfiable` appears in `docs/lessons.md` and the ledger, and no longer in `06-assessment.md` or PLAN.md.*

## 9.6 `docs/plans/` — the disposable tier

The repo has no disposable tier, which is why session narrative accreted into PLAN.md permanently: 58.7% of its Status section is a chronological work journal stored in table cells.

- **9.6 MUST** — A work unit gets one file, `docs/plans/<unit>.md`, created from its spec at the start and deleted when the work lands. History stays in git. *Done: a completed unit leaves no file in `docs/plans/`; `git log` recovers what it said.*
- **9.6.1 MUST** — `CLAUDE.md` states that session narrative — what was tried, what broke, why an approach was abandoned — belongs in the unit's plan file and nowhere else, and that on landing it is triaged: decisions to the ledger, traps to `docs/lessons.md`, the rest deleted. *Done: the convention is stated, and 9.7's reduction is its first worked example.*
- **9.6.2 SHOULD** — A companion to `/package <n> items <x-y>` for cross-cutting units that no package number names. *Done: the mounted-component unit (X.1–X.5) can be started by a single command.*

## 9.7 PLAN.md is reduced, then retired

- **9.7 MUST** — PLAN.md is reduced to the dependency graph, the global acceptance test and the retirement clause. The Status table, both narrative incident sections, "Order of attack" and "Background docs" are gone. *Done: PLAN.md is under 2KB; every fact it held is in `docs/system.md`, `docs/02-design.md`, `docs/lessons.md`, `README.md` or a spec, or was discarded under 9.3.3.*
- **9.7.1 MUST** — PLAN.md carries its retirement clause: **retire once `docs/plans/` has carried three work units start to finish.** The documentation migration is the first of the three. On retirement the dependency graph moves to `docs/system.md`. *Done: the clause states the count, the current tally, and where the graph goes.*
- **9.7.2 WONT** — PLAN.md does not regain a status table. Package state lives in its spec's done-conditions. *Falsified if: any session updates PLAN.md to record what it built.*

## 9.8 README owns the roadmap

- **9.8 MUST** — `README.md` becomes the sole owner of the roadmap, gaining three entries carried out of the dogfood list: explicit teaching style and personas; batch homework grading; and progress reporting for long jobs. *Done: the roadmap appears in exactly one file; `CLAUDE.md` points at it rather than restating it.*
- **9.8.1 SHOULD** — The batch-grading entry records that the per-question path was dogfooded and the opposite was wanted, and names the ledger entry it would amend. *Done: a future session reading the roadmap knows it is a decision change, not an unbuilt feature.*

## 9.9 Acceptance tests

Settled 2026-09-07: an acceptance test is durable, so it lives in a spec, never in a plan file that is deleted on landing.

- **9.9 MUST** — Each spec carries its own acceptance test: the cross-item journey its items compose into, stated as one user-visible cycle. It is distinct from the per-item done-conditions, which prove one item; the acceptance test proves they work together. *Done: every spec file has one, and it can be run against the app without reading any other spec.*
- **9.9.1 MUST** — The v1 global acceptance test moves to `docs/system.md` as the working cycle that document describes, marked passed 2026-09-07. It is not restated in any spec. *Done: `docs/system.md` holds it once; PLAN.md holds it until 9.7.1 retires the file, and no spec repeats it.*
- **9.9.2 WONT** — A plan file does not hold an acceptance test. It may hold the notes from running one. *Falsified if: deleting a landed plan file loses a test anyone would want to re-run.*
