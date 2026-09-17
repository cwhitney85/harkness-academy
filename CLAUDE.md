# Harkness Academy

LLM personal-tutor PWA. Laravel 13 + Inertia/React + Postgres. Anthropic-direct via the laravel/ai SDK. Solo founder (Colin) + AI-augmented sessions.

## The three tiers

Every document in this repo is one of three tiers, and **every fact has exactly one owner**. A fact found in two documents is a defect in whichever one is not the owner; the other holds a pointer, or nothing.

| Tier | Loaded | Documents |
|---|---|---|
| **Unconditional** | every session | this file |
| **Durable, addressable** | when targeted | [docs/system.md](docs/system.md) · [docs/02-design.md](docs/02-design.md) · [docs/specs/*.md](docs/specs/) · [docs/lessons.md](docs/lessons.md) · [docs/deploy.md](docs/deploy.md) · [docs/anthropic-specific.md](docs/anthropic-specific.md) |
| **Disposable** | during one work unit | [docs/plans/&lt;unit&gt;.md](docs/plans/) |

**Where a new fact goes.**

- **What the app does** — the lifecycle, the screens, the routes, the records → `docs/system.md`.
- **A decision, with the candidates it beat** → `docs/02-design.md`, numbered next, naming the package and spec item it arose under. Add its row to the index at that file's head.
- **A rule the code must satisfy, and what proves it** → the spec item in `docs/specs/`.
- **A tooling trap, or a postmortem of something that reached a student or a bill** → `docs/lessons.md`.
- **How to deploy** → `docs/deploy.md`. **An Anthropic-ism** → `docs/anthropic-specific.md` (spec 2.5).
- **What this session tried, broke, or abandoned** → the unit's plan file, and nowhere else.
- **The roadmap** → [README.md](README.md), which is the human entry point.

The 2026-08-21 planning documents — [docs/00-frame.md](docs/00-frame.md), [docs/01-research.md](docs/01-research.md), [docs/03-spec.md](docs/03-spec.md) — are durable but **historical**: they record how the shape was arrived at, and nothing new is written into them. [`docs/design/canvas/`](docs/design/canvas/) is durable and is the design source of record (below).

[PLAN.md](PLAN.md) holds the dependency graph and is scheduled for retirement (ledger D27, spec 9.7). Don't read it to find out where the work stands and don't write status into it — package state lives in its spec's done-conditions.

## Before writing any code

1. Read this file.
2. Read the spec for the package you're working on (`docs/specs/`). Spec item numbers (2.4, 5.2.2, …) are the shared vocabulary — use them in commits and discussion.
3. Read the ledger entries that spec's head points at. They are settled, with rejected candidates and rationale — don't re-litigate; if one proves wrong in practice, surface it to Colin instead of silently diverging.

## Standing conventions

- **The development database holds real work.** Colin is taking a real course on it. Never run `migrate:fresh`, `migrate:refresh`, `db:wipe` or `db:seed` against it unless he asks for that specific command in that message — an earlier "yes" does not carry. `InterfaceFixtureSeeder` deletes every course belonging to `colin@harkness.test`, which is now his own, so it is out of `DatabaseSeeder` and runs only when named: `php artisan db:seed --class=InterfaceFixtureSeeder`. Tests are safe — they run against `opentutor_test`. When a destructive command is genuinely wanted, take a restore point first: `php artisan db:backup` (writes `storage/backups/`, prints the `psql` line that restores it).
- **No model IDs outside `config/ai.php`** — call sites name capability tiers (`cheap`/`mid`/`top`) through the AI gateway (spec 2.1).
- **No memory access outside the path API** (spec 3.2). Model-facing docs live in the memory tree; app-queried state lives in relational rows.
- **Prompts are files in `prompts/`** with frontmatter (spec 2.3.1) — never inline strings.
- **Memory writes are workflow-initiated** — the model never decides when to write memory.
- **Fail-open rule**: fail open only when failing closed would destroy student progress (spec 4.5.1).
- **GUIDANCE.md is model-facing only** — no route or component may read it.
- **A state-preserving Inertia visit does not remount the screen** (ledger D28). `router.reload()` always preserves state, and `router.post/put/patch/delete` do unless told otherwise, so a redirect landing on a *different* URL that renders the *same* page component leaves every `useState` and `useRef` holding the previous URL's answer. Key the screen on whatever its state describes. A plain `<Link>` or a GET `router.visit` remounts on its own and needs nothing.
- **Laravel Boost's MCP tools come first** — search its docs before writing Laravel, Inertia, Tailwind or AI SDK code; read errors, logs and schema through its tools rather than by hand. Its database tool is read-only. Don't run its `infer-conventions` skill: project rules are off (`BOOST_RULES_ENABLED=false`) because facts have one owner here.
- **Skill level (101/201/301) is pedagogy; plan is economics** — never couple them (spec 4.5.6).
- Every AI call must log to `usage_events` (spec 2.2) and use a prompt key from the inventory (spec 2.3.2).
- Anthropic-specific feature usage gets an entry in `docs/anthropic-specific.md` (spec 2.5).
- **Session narrative belongs in the unit's plan file and nowhere else.** What was tried, what broke, why an approach was abandoned, what a screen measured — it goes in `docs/plans/<unit>.md`, never in a spec, a status table or a commit body. **On landing, triage it**: decisions to the ledger, traps and postmortems to `docs/lessons.md`, behaviour changes to `docs/system.md`, rule changes to the spec item — then delete the file in the commit that lands the work. `git log` is what recovers the rest.
- **The design canvas is the design source of record** — [`docs/design/canvas/`](docs/design/canvas/) holds the source, the published canvas is where it is read. A change to the visual language changes that directory **and** `resources/css/app.css` in the same commit (spec 8.1.3).
- **Clean up the slop** - before committing, review the diff as if your job is to delete as much of it as possible without violating the requested behavior. Flag:

* abstractions with only one real use
* duplicated existing capability
* speculative extensibility
* unnecessary dependencies
* files changed outside the causal path of the request
* comments/types/wrappers that explain complexity the change itself introduced

For every finding, propose the smaller implementation and prove the same acceptance conditions still pass.

Don’t optimize for fewer lines. Optimize for the least new structure required to make the requested behavior true.

## Git conventions

- Default branch is `master`. Serial solo work commits directly to `master`; branches only for worktree lanes and risky spikes (ledger W4).
- Commit at done-condition granularity: one spec item (or small cluster) per commit, spec item number in the message (e.g. `2.4: caching spike — workaround for laravel/ai#119`).
- No signing, co-author trailers, or attribution lines in commit messages.

## Session ritual

Start a build session with `/package <n>` — or `/unit <name> <items…>` for work that crosses packages and no package number names (see `.claude/commands/`).
Work against the data already in the development database; see the standing convention above before running any seeder or migration.
End of session: report which done-conditions now pass, and triage the plan file as the convention above says.

## Parallel lanes (worktrees)

A lane may open only when every interface it consumes has landed on master. Colin reviews and merges every lane — never auto-merge.
