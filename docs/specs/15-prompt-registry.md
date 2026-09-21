# Spec 15 — Prompt Registry and Experiments

*Provides: prompt versions in the database, file sync, the prompt editor, `prompts:pull`, and A/B experiments. Consumes: 14 (the catalog, the fuller usage row), 13 (the gate, the action log), 2.3 (the inventory and its render rules). Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D54](../02-design.md) files are the reviewed baseline, the database is what runs · [D55](../02-design.md) an experiment sticks to a course · [D52](../02-design.md) the catalog. Amends [2.3.1](02-ai-layer.md): git is the history of file versions; `prompt_versions` is the history of what ran.

## The shape

**A prompt has a contract and a body.** The contract — `output` and `vars` — is what the calling code depends on, so it comes only from the file. The body and the tier are what tuning changes, so they are editable live, and every edit is a new, immutable version.

**A deploy never overrides a live edit.** A new file version activates only when the active version also came from a file; otherwise it waits, flagged, until Colin activates it or pulls the live one back into git.

---

## 15.1 Versions

- **15.1.1 MUST** — `prompts`: key, `output`, `vars` (from the file), and the active version, which is a pointer on the prompt so that two versions of one prompt cannot both be active. `prompt_versions`: number, body, tier, source (`file` | `admin`), author, file hash, created_at. Versions are never updated. *Done: the model has no update path.*
- **15.1.2 MUST** — `php artisan prompts:sync` makes a new version for each file whose content changed — changed against the newest `file` version of that prompt, which is the file's last known state, never against the active version, or a live edit would make the next deploy re-import an unchanged file. It runs on deploy ([docs/deploy.md](../deploy.md)) and at test bootstrap. *Done: two syncs make no second version; one file change makes exactly one.*
- **15.1.3 MUST** — A new file version activates only if the active version's source is `file`; otherwise it is stored and flagged "newer file version available". *Done: one test per case.*
- **15.1.4 MUST** — Sync fails, naming both, when a file drops a var the active admin version uses. *Done: test.*
- **15.1.5 MUST** — `PromptRepository` keeps its interface and reads active versions, cached. [2.3.1](02-ai-layer.md)'s render rules are unchanged. *Done: the loader tests — missing var throws, unknown key throws — pass against the database-backed repository.*
- **15.1.6 MUST** — Every usage row carries `prompt_version_id`. *Done: no null in the acceptance journey.*
- **15.1.7 MUST** — `lesson.system` composes from the active version of each part ([2.3.3](02-ai-layer.md)). *Done: a persona edit leaves every other prompt's rendering byte-identical.*

## 15.2 Editor

- **15.2.1 MUST** — Admin → Prompts lists key, active version, source, and seven-day calls and cost. *Done: renders all 18 prompts.*
- **15.2.2 MUST** — Saving a new body or tier creates a version, activates it, and logs it. A missing or undeclared var is refused at save. *Done: feature tests.*
- **15.2.3 MUST** — A prompt that streams inside the request may move only between `cheap` and `mid` until the queued-streaming unit lands ([D63](../02-design.md)). `tier: session` prompts have no editable tier. The in-request list lives in code. *Done: the editor refuses `top` for `interview.knowledge`.*
- **15.2.4 MUST** — Any two versions can be diffed, and any version re-activated; re-activation is logged. *Done: rolling back to v1 makes the next usage row name v1.*
- **15.2.5 MUST** — `php artisan prompts:pull` writes active versions back to `prompts/` byte-exact. *Done: pull, commit, sync — no new version.*

## 15.3 Experiments

*SHOULD, and built in this package's session so they are not lost.*

- **15.3.1 SHOULD** — `experiments` (prompt, status `draft` | `running` | `stopped`, started_at, stopped_at) and `experiment_variants` (prompt version, optional catalog model, weight). At most one running experiment per prompt. *Done: starting a second is refused.*
- **15.3.2 SHOULD** — The variant is a deterministic hash of experiment and course — or user, when there is no course. *Done: 100 calls from one course share one variant; across 1,000 simulated courses each variant lands within ±3 points of its weight.*
- **15.3.3 SHOULD** — `lesson.section` and `lesson.qa` follow their `lesson.system`'s variant. *Done: test.*
- **15.3.4 SHOULD** — A variant's model must be active and have a provider class ([14.2.2](14-routing-and-usage.md)). *Done: validation.*
- **15.3.5 SHOULD** — A comparison screen shows, per variant: calls, median and p95 latency, cost per call, failure and failover rates. *Done: matches SQL on a fixture.*
- **15.3.6 WONT** — Quality scores, significance testing, automatic stopping.

## 15.4 Out of scope

- **15.4.1 WONT** — Per-module prompts.
- **15.4.2 MAY** — A test proving the version resolver already receives the course, as the seam per-module prompts will use.

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. Edit `explainer.select` in admin; use the explainer. The usage row names the new version.
2. Deploy a change to that file. The editor flags "newer file version available"; the live edit stays active.
3. `prompts:pull`, commit, redeploy. No new version appears.
4. Run a two-variant experiment on `checkpoint.generate` across two courses. Each course sticks to one variant; the comparison screen lists both.
