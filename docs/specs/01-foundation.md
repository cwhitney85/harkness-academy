# Spec 1 — Foundation

*Provides: the app skeleton every other package builds on. Consumes: nothing.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D2](../02-design.md) Inertia + React · [D7](../02-design.md) the data model · [R4](../02-design.md) Laravel Cloud, and the host must not buffer SSE · [W3](../02-design.md) `master` · [W4](../02-design.md) branching. Traps found here: [T1, T4](../lessons.md). Deploy runbook: [docs/deploy.md](../deploy.md).

- **1.1 MUST** — Laravel 13, Inertia + React starter, Postgres, queues, scheduler. *Done: boots locally; page renders through Inertia.*
- **1.2 MUST** — Auth + accounts; 18+ attestation in ToS at signup. *Done: register/login/logout pass; under-18 blocks signup.*
- **1.3 MUST** — Laravel Cloud deploy from `master`. *Done: push → live URL over TLS.*
- **1.4 SHOULD** — CI running tests on push. *Done: red build blocks merge.* *Deferred 2026-08-22 (Colin): the tests run on every push, but **merge-blocking is off** — it needs branch rulesets, which need a paid plan. The half that costs nothing is in place; the gate goes on when the repo is paid for.*

## Dogfood repair (2026-09-07)

- **1.5 MUST** — Password requirements are visible before the student types, and every failure restates all of them. The rules reach the client already (`FortifyServiceProvider` passes `passwordRules`) but only as the WebKit `passwordrules` attribute, which renders no visible text, so a student learns them one validation error at a time. *Done: the register, reset and settings-security screens each show the full rule set as readable text; a submission failing on two rules names both.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

Against the deployed URL, over TLS, in a browser that has never seen the app:

1. Register an account. Before a single character is typed the password rules are on screen as readable text; a submission that breaks two of them names both. The terms carry the 18+ attestation, and declining it does not create an account.
2. Land on a page that came through Inertia — a client-side link between two screens does not reload the document.
3. Sign out, sign back in with the same credentials, and arrive at the same place.
4. Push a commit to `master`. CI runs the suite on that push, and the deployed URL serves the new commit when it finishes.

A queue worker and the scheduler are running against the same database throughout; nothing in this test needs either, and everything after package 1 does.
