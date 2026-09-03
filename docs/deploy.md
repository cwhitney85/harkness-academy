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
| `ANTHROPIC_API_KEY` | **Always.** Nothing injects it, and without it the app deploys clean and dies at the first interview — a 500 on the interview's stream, surfacing as a 422 on the answer that follows it. Prefer a **linked secret** over a custom variable: Secrets Manager encrypts it in the browser, never shows it again and is KMS-backed. Organization → Settings → Secrets → New secret, then link it to the environment (the **Link secrets** button in General Settings' injected variables section) and deploy from the staged-changes banner. Do not also set a custom variable of the same name — custom variables outrank linked secrets, so a leftover one silently shadows every later rotation. |
| `APP_URL` | After the first deploy, set to the assigned `*.laravel.cloud` domain, then redeploy. |
| `APP_NAME` | Only if the injected value is not `OpenTutor` — it renders user-visibly through Inertia's shared props. |

A variable only reaches the application on a **deploy**, not a restart: the build runs `php artisan optimize`, which caches config and freezes every `env()` value into that cache.

Do **not** paste a locally generated `APP_KEY` over Cloud's. Cloud generates and manages one; replacing it buys nothing and puts a production secret through your terminal history.

`QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` stay on `database`, as in `.env.example`. Cloud filesystems are ephemeral and unshared per replica, so file-backed drivers are not an option; the attached Postgres covers all three without a separate KV store.

**7. Compute.** Click the App compute cluster on the canvas. Flex 512 MiB / 1 vCPU, autoscaling **None**, **Scale to Zero** on with a 5 minute sleep.

Leave **Octane** and **Inertia SSR** off. Octane is not in `composer.json` and enabling it fails the build; SSR would also require `npm run build:ssr`. Neither is needed, and Octane's interaction with the SSE constraint below is unknown — not a variable to introduce before 5.1.

Leave the **scheduler off**. Nothing in the app is scheduled, and a scheduler firing on a cadence holds the environment awake for the whole sleep timeout, defeating Scale to Zero.

**A queue is not optional — see step 8.** This paragraph used to say to add no background process "until there is work for them", and by the time anyone reread it the work had existed for two packages.

**8. Add a managed queue.** Required, not optional (ledger D25). Three things in this app run on the queue, and nothing drains them without this:

- `CompletionPipeline` — the chain that writes homework and the working-knowledge summaries after a lesson (5.4.1). It has dispatched queued jobs since package 5. **An environment without a worker completes lessons and silently never writes their homework.**
- `DrawUpSyllabus` and `WriteFinalExam` — the two `top`-tier generations. They run for minutes, and Cloudflare (Laravel Cloud's networking layer) cuts a request at about 100 seconds, so neither can be done in the request that asks for it.

Infrastructure canvas → **Add compute** → **Managed queue**:

| Field | Value |
|---|---|
| Name | `default` |
| Type | Standard |
| Memory | Flex 256 MiB |
| `--backoff` | 0 |
| **`--timeout`** | **360** |
| `--tries` | 1 |
| `--force` | off |
| Autoscaling | min 0, max 3 workers |

**`--timeout` is the one that matters, and its default of 60 seconds is wrong for this app.** A `top`-tier call is capped at 300 seconds by `config/ai.php`, so at 60 the worker kills every syllabus and exam generation mid-call — the same failure this queue exists to remove, one layer down and harder to see.

360 sits just above the app's own ceiling, so our error handling wins over the worker's. It does not cover the theoretical worst case of both attempts running the full 300 seconds, and deliberately: the retry exists for *unusable output*, not slowness, so a second attempt normally begins within seconds of the first and both fit well inside 360. The case it truncates — a first attempt burning the entire 300s — is the one where retrying is least likely to help.

**Both numbers are ceilings, not measurements.** `usage_events.latency_ms` records every call and the Queues dashboard graphs processing duration; after a handful of real generations, tighten `top` in `config/ai.php` and this timeout with it. That also shrinks the Flex exposure below.

**Cloud will warn that timeouts over 90 seconds want the Pro class, and it is right.** Flex workers run on interruptible capacity with a 90-second shutdown grace, so any job over 90 seconds can be killed mid-run and redelivered — no timeout value changes that. Both generation jobs are idempotent, so a redelivery is safe; it costs one more `top`-tier call. Staying on Flex accepts that trade. Regular interruptions on the Queues dashboard are the signal to move to Pro, not the warning itself.

A killed job still ends the student's wait: both jobs implement `failed()`, which marks the `GenerationRequest` failed. Without it a worker kill would leave the row open and the screen polling forever.

`--tries` and `--backoff` are defaults for jobs that define neither, and every job here defines its own: `PipelineJob` returns 3 and `[10, 60, 300]` from `config/lessons.php` (5.4.5), and both generation jobs set `$tries = 1` because the services already retry internally. A job property beats the flag, so these two fields only guard a job that forgets — 1 and 0 keep that guard tight. `--force` only runs jobs while the app is in maintenance mode; leave it off.

**Standard, not FIFO.** The lesson chain is ordered, which is the trap — but `Bus::chain` enforces that itself by dispatching each job only after the previous one succeeds. FIFO would break it: FIFO supports no per-job delays, and the pipeline's backoff is a delayed retry.

**Named `default`.** No job calls `->onQueue()`, so everything dispatches to the connection's default queue, and this name is the one that cannot be mis-routed. A queue nothing drains fails silently, which is the whole failure mode this step exists to prevent — not the place to spend a naming decision.

Flex is available on every plan, including Starter, and is billed per second only while a job runs — a three-minute generation costs a fraction of a cent, plus roughly $0.13/month of polling. Its 90 seconds is the grace period a worker gets *when it must stop*, not a cap on job length: a long generation completes normally and is only at risk if a worker is interrupted, where the job is redelivered. Both generation jobs are idempotent, so a redelivery costs nothing when the work already landed. Move the queue to **Pro** (Growth plan) if redelivery ever costs real money.

Do **not** run `queue:work` as an app-cluster background process instead. With Scale to Zero the App cluster stops at the sleep timeout *even mid-job*, and this app's queued work is long AI calls.

Deploying a managed queue sets `QUEUE_CONNECTION=cloud` environment-wide, overriding the `database` driver in `.env.example`. `aws/aws-sdk-php` is already installed (`laravel/ai` requires it), but Cloud reads `composer.json` rather than the lock, so it is listed there explicitly.

**9. Deploy.** Click Deploy. Cloud issues the free `*.laravel.cloud` domain with TLS on the first successful deployment.

## After the first deploy

- Set `APP_URL` to the assigned domain and redeploy.
- Confirm the queue is draining. The **Queues** dashboard under Monitoring shows job volume and failures for a managed queue; `php artisan queue:monitor cloud:<queue name>` from the **Commands** tab reports its backlog. Commands run through `bash -c "…"`, so avoid double quotes inside them — an `--execute="…"` dies on its own quoting before it ever reaches PHP.
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
