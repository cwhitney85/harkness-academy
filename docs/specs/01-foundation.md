# Spec 1 — Foundation

*Provides: the app skeleton every other package builds on. Consumes: nothing.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D2](../02-design.md) Inertia + React · [D7](../02-design.md) the data model · [R4](../02-design.md) Laravel Cloud, and the host must not buffer SSE · [W3](../02-design.md) `master` · [W4](../02-design.md) branching · [D41](../02-design.md) an error names every rule the field broke · [D43](../02-design.md) the password rule is eight characters · [D45](../02-design.md) entry is one shared invitation code. Traps found here: [T1, T4](../lessons.md). Deploy runbook: [docs/deploy.md](../deploy.md).

- **1.1 MUST** — Laravel 13, Inertia + React starter, Postgres, queues, scheduler. *Done: boots locally; page renders through Inertia.*
- **1.2 MUST** — Auth + accounts; 18+ attestation in ToS at signup. *Done: register/login/logout pass; under-18 blocks signup.*
- **1.3 MUST** — Laravel Cloud deploy from `master`. *Done: push → live URL over TLS.*
- **1.4 SHOULD** — CI running tests on push. *Done: red build blocks merge.* *Deferred 2026-08-22 (Colin): the tests run on every push, but **merge-blocking is off** — it needs branch rulesets, which need a paid plan. The half that costs nothing is in place; the gate goes on when the repo is paid for.*

## Dogfood repair (2026-09-07)

- **1.5 MUST** — Password requirements are visible before the student types, and every failure restates all of them ([D41](../02-design.md)). *Done: the register, reset and settings-security screens each show the full rule set as readable text, derived from `Password::defaults()` so it cannot drift from what the validator applies; a submission failing on two rules names both.*

## The invitation gate (2026-09-11, unit `harkness`)

*The landing page (8.13) puts a Register button on a public domain, and nothing in the app limits what an account spends. [D45](../02-design.md) settles the shape.*

- **1.6 MUST** — Registration requires an invitation code, validated in `CreateNewUser` beside the existing 18-or-older attestation (1.2) — and, by the same `InvitationValidationRules`, on Google sign-up's Finish signing up page ([18.3.4](18-email-and-google-sign-in.md), [D73](../02-design.md), amended 2026-10-07). *Done: a registration with no code, or with a wrong one, creates no user row; a correct one creates the row exactly as today.*
- **1.6.1 MUST** — The code is a single shared value read from the environment through `config/`, with no table and no per-person codes ([D45](../02-design.md)). *Done: rotating it is an environment change and a deploy, not a migration.*
- **1.6.2 MUST** — An invited account's `plan` is `standard`. Entry is not pedagogy and not economics, and must not become a third axis beside them (spec 4.5.6). *Done: `select distinct plan from users` returns one value after an invited registration.*
- **1.6.3 SHOULD** — A wrong code fails on its own field, naming the rule it broke, as [D41](../02-design.md) requires of every validation error. *Done: the register screen shows the error against the code field, not as a generic banner.*
- **1.6.4 MUST** — The code is never rendered, defaulted, or logged. *Done: `git grep` finds it in no committed file; a failed registration writes no code value to any log.*
- **1.6.5 WONT** — No spend cap, quota or usage ceiling. `usage_events` records what was spent; nothing reads it back as a limit. The gate is who gets in, not what they spend. *Falsified if: a budget check appears anywhere in the registration or generation path.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

Against the deployed URL, over TLS, in a browser that has never seen the app:

1. Register an account. A submission with no invitation code, or a wrong one, creates nothing and says so on the code field. With the right code: before a single character is typed the password rules are on screen as readable text, and a submission that breaks two of them names both. The terms carry the 18+ attestation, and declining it does not create an account.
2. Land on a page that came through Inertia — a client-side link between two screens does not reload the document.
3. Sign out, sign back in with the same credentials, and arrive at the same place.
4. Push a commit to `master`. CI runs the suite on that push, and the deployed URL serves the new commit when it finishes.

A queue worker and the scheduler are running against the same database throughout; nothing in this test needs either, and everything after package 1 does.
