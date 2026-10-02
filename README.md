# Harkness Academy

An LLM personal tutor. Laravel 13 + Inertia/React + Postgres. Every AI call reaches its model
through a database catalog, and one class per provider owns that provider's cache markers, token
counting and pricing (spec [14](docs/specs/14-routing-and-usage.md)). Anthropic is the only provider
built; **multiple providers is the plan**, and always was ([D1](docs/02-design.md)).

## Local setup

Requires PHP 8.3+, Node 22+, and a running Postgres 14+.

```bash
createdb opentutor && createdb opentutor_test
composer install
cp .env.example .env && php artisan key:generate
# set DB_USERNAME / DB_PASSWORD in .env
php artisan migrate && php artisan prompts:sync   # prompts live in the database (spec 15)
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
([docs/system.md §8](docs/system.md)). Since then:

- **A public demo** (spec [12](docs/specs/12-demo.md), 2026-09-17) — a public route mints a `demo`
  account for a one-lesson course, offered a real account at the end.
- **Admin and performance** (specs [13](docs/specs/13-admin-foundation.md)–[17](docs/specs/17-admin-screens.md),
  2026-10-02) — an admin area with view-as, a model catalog with per-model cost behind every AI
  call, prompts edited live, removal and restore of modules, subtopics and courses, and screens for
  users, courses and usage.

What comes next, in the order it is worth doing:

**Evaluating a prompt.** Not specced. Prompts are now edited live in production, and nothing checks
the edited text — the tests hold only the files in `prompts/` ([D65](docs/02-design.md)). A/B
experiments were specced inside 15 and deferred ([D66](docs/02-design.md)): with no quality signal the comparison shows only cost and latency, and with
nine courses there is nothing to split. What returns is its own package — a golden set of captured inputs
run through two prompt versions and read side by side, then a quality signal attached to the usage row,
then the split. The model is its primary axis, not the prompt body.

**Email that is actually sent.** Not specced. Production was never given a mail service, so a
password reset — and anything else the app emails — is written to the log instead of sent, and a student
who forgets their password is locked out. A mail provider configured through environment variables, a
step in [docs/deploy.md](docs/deploy.md), and a reset run end to end in production. Small, and worth doing
before more students are invited.

**Streaming from the queue.** Ledger [D63](docs/02-design.md): every streamed generation runs on the queue
and broadcasts over Reverb, so no model is ever too slow for the host's 60-second ceiling.

**Model switching.** The rest of the move to multiple providers that Anthropic-direct was always the
first step of ([D1](docs/02-design.md)). Spec 14 landed the catalog, the tiers as rows and the provider
class; what is left is [14.6](docs/specs/14-routing-and-usage.md)'s three exclusions — provider classes
beyond Anthropic, changing a tier's model from admin, importing prices for a larger catalog — and
same-provider fallback within a tier, which the SDK's failover cannot do ([D52](docs/02-design.md)).

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
and plan tiers · accounts for under-18s.
