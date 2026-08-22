# Spec 1 — Foundation

*Provides: the app skeleton every other package builds on. Consumes: nothing. See PLAN.md for status.*

- **1.1 MUST** — Laravel 13, Inertia + React starter, Postgres, queues, scheduler. *Done: boots locally; page renders through Inertia.*
- **1.2 MUST** — Auth + accounts; 18+ attestation in ToS at signup. *Done: register/login/logout pass; under-18 blocks signup.*
- **1.3 MUST** — Laravel Cloud deploy from `master`. *Done: push → live URL over TLS.*
- **1.4 SHOULD** — CI running tests on push. *Done: red build blocks merge.*
