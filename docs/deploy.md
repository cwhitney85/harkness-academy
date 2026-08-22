# Deploying to Laravel Cloud (spec 1.3)

*Hosting decision and rationale: [02-design.md](02-design.md) R4. This file is the runbook.*

Done when: a push to `master` lands on a live URL over TLS, with no developer touching the database.

## One-time setup

**1. Create the application.** Laravel Cloud dashboard → **+ New application** → **Connect to GitHub** → grant access to `cwhitney85/open-tutor`. Name it `opentutor`, pick a region, **Create Application**. This creates a default environment; the deployed branch is `master`.

**2. Set the PHP runtime to 8.4.** General Settings → Runtime. Cloud defaults new environments to **8.5** — override it. CI runs 8.4, and an environment that differs from CI defeats the point of having CI.

**3. Build command.**

```sh
composer install --no-dev && npm ci --include=dev && npm run build && php artisan optimize
```

`--include=dev` is not optional here. `vite.config.ts` imports `@laravel/vite-plugin-wayfinder`, which lives in `devDependencies`; if Cloud's build environment sets `NODE_ENV=production`, a bare `npm ci` skips it and the Vite build dies on the import. (Wayfinder's PHP half is in `require`, so `--no-dev` is safe on the Composer side.)

`php artisan optimize` belongs in the build, not the deploy — deploy-command filesystem changes are discarded.

**4. Deploy command.**

```sh
php artisan migrate --force
```

Nothing else. Cloud restarts queue workers itself, and `optimize:clear` / `storage:link` are actively harmful here.

**5. Attach Postgres.** Infrastructure canvas → **Add database** → **Laravel Serverless Postgres**. Region must match the compute cluster. Smallest instance and 5GB storage are ample for dogfooding; enable **scale to zero** so an idle app costs nothing.

Cloud injects `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE` automatically. **Do not set those by hand** — a custom variable overrides the injected one and will silently point production at the wrong database.

**6. Environment variables.** Set these; leave everything else to injection.

| Variable | Value | Why |
|---|---|---|
| `APP_KEY` | output of `php artisan key:generate --show` | Generate a fresh one. Do not reuse your local key. |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_NAME` | `OpenTutor` | |
| `APP_URL` | the assigned `*.laravel.cloud` domain | Fill in after the first deploy. |
| `DB_CONNECTION` | `pgsql` | Cloud injects the credentials but **not** the driver. Without this, Laravel falls back to the `config/database.php` default. |

`QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` stay on `database`, as in `.env.example`. Cloud filesystems are ephemeral and unshared per replica, so file-backed drivers are not an option; the attached Postgres covers all three without a separate KV store.

**7. Compute.** Enable the **scheduler** on the app cluster, and add a **worker** cluster for the queue. Both are required by spec 1.1 and will carry real load from package 4 onward.

**8. Deploy.** Click Deploy. Cloud issues the free `*.laravel.cloud` domain with TLS on the first successful deployment.

## After the first deploy

- Set `APP_URL` to the assigned domain and redeploy.
- Register an account against the live URL. Registration exercises Postgres writes and the 18+ attestation (spec 1.2) in one step.
- Confirm `/up` returns 200.

## Open constraint — SSE buffering

Design R4 records a hard requirement: **the host must not buffer SSE.** The lesson engine (package 5) streams over `text/event-stream`, and a buffering proxy turns streamed tutoring into a page that sits blank and then dumps.

Laravel's `eventStream` sends `X-Accel-Buffering: no` and flushes between generator yields, which is the standard mitigation. **This is unverified on Cloud.** It cannot be tested until 5.1 streams something real. Verify it there before building on it; if Cloud buffers, R4 says revisit the hosting choice rather than work around it.

## Notes for later packages

- Laravel Serverless Postgres supports **pgvector**, which keeps the D3a memory-storage decision's "future pgvector" path open at no cost today.
- Scale-to-zero wakes in a few hundred milliseconds. Acceptable for dogfooding; revisit before real users meet a cold first lesson.
