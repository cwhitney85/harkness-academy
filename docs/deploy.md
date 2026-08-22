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

**6. Environment variables.** Cloud injects nearly everything this app needs, including `APP_KEY`, `APP_ENV`, `APP_DEBUG`, and — once Postgres is attached — `DB_CONNECTION=pgsql` alongside the credentials. Its own docs list only the four DB credential variables, so trust the dashboard over the docs: the injected list in General Settings is authoritative.

**Only override an injected value when it is wrong.** An override is a value you then own, and it silently blocks any later change to Cloud's default. The dashboard warns you ("You are overwriting 1 injected variable") whenever you are about to take that on.

In practice you set almost nothing:

| Variable | When |
|---|---|
| `APP_URL` | After the first deploy, set to the assigned `*.laravel.cloud` domain, then redeploy. |
| `APP_NAME` | Only if the injected value is not `OpenTutor` — it renders user-visibly through Inertia's shared props. |

Do **not** paste a locally generated `APP_KEY` over Cloud's. Cloud generates and manages one; replacing it buys nothing and puts a production secret through your terminal history.

`QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` stay on `database`, as in `.env.example`. Cloud filesystems are ephemeral and unshared per replica, so file-backed drivers are not an option; the attached Postgres covers all three without a separate KV store.

**7. Compute.** Click the App compute cluster on the canvas. Enable the **scheduler** and a **queue worker** there — a separate worker cluster is for splitting load on larger apps and is not needed here. Autoscaling **None**, **Scale to Zero** on.

Leave **Octane** and **Inertia SSR** off. Octane is not in `composer.json` and enabling it fails the build; SSR would also require `npm run build:ssr`. Neither is needed, and Octane's interaction with the SSE constraint below is unknown — not a variable to introduce before 5.1.

**8. Deploy.** Click Deploy. Cloud issues the free `*.laravel.cloud` domain with TLS on the first successful deployment.

## After the first deploy

- Set `APP_URL` to the assigned domain and redeploy.
- Register an account against the live URL. Registration exercises Postgres writes and the 18+ attestation (spec 1.2) in one step.
- Confirm `/up` returns 200.

## Cost control

Set a **spending limit** (Organization → Usage), not just a billing alert. Alerts only email you at 50/80/100% and spending continues past them; a spending limit halts new resource provisioning and pauses compute when hit. v1 prototype settings: limit $30, alert $15.

Hitting the limit takes the environment down until it is raised or the billing cycle rolls over. That is the intended behaviour, but it is not a warning-only state.

**Stopping an environment does not stop all charges.** Compute stops billing; attached resources do not. The database's compute scales to zero on its own, but storage bills continuously. To reach zero, delete the database cluster — the schema lives entirely in migrations, so a redeploy rebuilds it.

## Open constraint — SSE buffering

Design R4 records a hard requirement: **the host must not buffer SSE.** The lesson engine (package 5) streams over `text/event-stream`, and a buffering proxy turns streamed tutoring into a page that sits blank and then dumps.

Laravel's `eventStream` sends `X-Accel-Buffering: no` and flushes between generator yields, which is the standard mitigation. **This is unverified on Cloud.** It cannot be tested until 5.1 streams something real. Verify it there before building on it; if Cloud buffers, R4 says revisit the hosting choice rather than work around it.

## Notes for later packages

- Laravel Serverless Postgres supports **pgvector**, which keeps the D3a memory-storage decision's "future pgvector" path open at no cost today.
- Scale-to-zero wakes in a few hundred milliseconds on current Flex sizes. Acceptable for dogfooding; revisit before real users meet a cold first lesson.
- **Scale-to-zero can interrupt a running queued job** when the sleep timeout elapses. Harmless in package 1 — nothing queues work. It becomes real in package 4, when syllabus generation and summarization go async; the fix is Cloud's Managed Queues, not a longer timeout.
