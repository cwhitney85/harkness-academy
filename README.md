# Harkness Academy

An LLM personal tutor. Laravel 13 + Inertia/React + Postgres, Anthropic-direct.

## Local setup

Requires PHP 8.3+, Node 22+, and a running Postgres 14+.

```bash
createdb opentutor && createdb opentutor_test
composer install
cp .env.example .env && php artisan key:generate
# set DB_USERNAME / DB_PASSWORD in .env
php artisan migrate
npm install && npm run build
composer dev   # app, queue worker, logs, and vite
```

Tests run against `opentutor_test` (see `phpunit.xml`), not sqlite — the app
relies on Postgres behaviour.

```bash
composer test      # pint + phpstan + phpunit
composer ci:check  # the above plus eslint, prettier, tsc
```

## Where things are decided

- [docs/system.md](docs/system.md) — what the app does, end to end.
- [docs/specs/](docs/specs/) — per-package specs; item numbers are the shared vocabulary.
- [docs/02-design.md](docs/02-design.md) — decision ledger, indexed at its head.
- [docs/lessons.md](docs/lessons.md) — tooling traps and incident postmortems.
- [docs/deploy.md](docs/deploy.md) — the deploy runbook.
- [CLAUDE.md](CLAUDE.md) — the document tiers, and the conventions for build sessions.

## Roadmap

v1 shipped on 2026-09-07: one student took one honest course cycle end to end, in a
browser and again on a phone, with no developer touching the database
([docs/system.md §8](docs/system.md)). What comes next, in the order it is worth doing:

**Explicit teaching style, and personas the student picks.** A course already carries a
style column and the persona is already a prompt file, but exactly one style ships and
nothing chooses it. What is missing is the choice: a student says how they want to be
taught — brisk, socratic, patient, whatever the set turns out to be — and the course
carries it into every lesson. This is the difference between a tutor and a textbook, and
the plumbing for it is already in.

**Batch homework grading.** *This is a decision change, not an unbuilt feature.* Homework
grades one question at a time and discloses that question's answer in the reply, which is
what ledger [D32](docs/02-design.md) settled and what "answers are kept as you go" on the
sheet promises. Dogfooding it wanted the opposite: doing a whole worksheet and then having
it marked is how homework feels, and per-question reveal turns a worksheet into a
guessing game with feedback. Building this amends D32, which means the endpoint takes a
set of answers, the disclosure moves to the hand-in, and the sheet grows the "Hand it in"
button the artboard drew and 8.9.1 deliberately did not build.

**Progress reporting for long jobs.** A queued syllabus or exam generation now says only
that it is working, and names the artefact ([8.12.2](docs/specs/08-interface.md)). Real
progress means the job emitting the steps it is actually on and the screen reading them,
which is a job-instrumentation change and not a copy change.

**After those, and unordered:** placement quiz and level gating · student notes (the legal
pad in the design canvas is drawn and reserved for it) · a refresher drawn at random across
everything the student knows · questions per lesson as a student input, 1–5 · email nudges
· dark mode · essay and project homework · a model selector · bring-your-own-key · billing
and plan tiers · an admin surface beyond `memory:show` · accounts for under-18s.
