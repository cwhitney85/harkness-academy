# OpenTutor

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
- [CLAUDE.md](CLAUDE.md) — the document tiers, and the conventions for build sessions.
