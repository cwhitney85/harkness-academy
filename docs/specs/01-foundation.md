# Spec 1 — Foundation

*Provides: the app skeleton every other package builds on. Consumes: nothing. See PLAN.md for status.*

- **1.1 MUST** — Laravel 13, Inertia + React starter, Postgres, queues, scheduler. *Done: boots locally; page renders through Inertia.*
- **1.2 MUST** — Auth + accounts; 18+ attestation in ToS at signup. *Done: register/login/logout pass; under-18 blocks signup.*
- **1.3 MUST** — Laravel Cloud deploy from `master`. *Done: push → live URL over TLS.*
- **1.4 SHOULD** — CI running tests on push. *Done: red build blocks merge.*

## Dogfood repair (2026-09-07)

- **1.5 MUST** — Password requirements are visible before the student types, and every failure restates all of them. The rules reach the client already (`FortifyServiceProvider` passes `passwordRules`) but only as the WebKit `passwordrules` attribute, which renders no visible text, so a student learns them one validation error at a time. *Done: the register, reset and settings-security screens each show the full rule set as readable text; a submission failing on two rules names both.*
