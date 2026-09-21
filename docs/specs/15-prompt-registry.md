# Spec 15 — Prompt Registry and Experiments

*Provides: prompt versions in the database, file sync, the prompt editor, and `prompts:pull`. A/B experiments were specced here and deferred ([15.3](#153-experiments--deferred-2026-09-21-d66)). Consumes: 14 (the catalog, the fuller usage row), 13 (the gate, the action log), 2.3 (the inventory and its render rules). Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D65](../02-design.md) the database owns a prompt's body — **supersedes D54**, whose shape this spec was first written to · [D55](../02-design.md) an experiment sticks to a course · [D52](../02-design.md) the catalog. Amends [2.3.1](02-ai-layer.md): git is where a prompt starts and where it is archived; `prompt_versions` is the history of what ran.

## The shape

**A prompt has a contract and a body, and they have different owners.** The contract — `output` and `vars` — is what the calling code depends on, so it comes only from the file, on every deploy. The body and the tier are what tuning changes, so the editor owns them: every save is a new, immutable version.

**A file's body is read exactly once**, to seed version 1 of a prompt that has none. After that a deploy cannot change any prompt's text, so nothing compares a file to a version and nothing has to be reconciled. `prompts:pull` is the other direction, and the only one: it writes active versions back into the files so the repo, the test seeder and git history hold what is actually running.

---

## 15.1 Versions

- **15.1.1 MUST** — `prompts`: key, `output`, `vars` (from the file), and the active version, which is a pointer on the prompt so that two versions of one prompt cannot both be active. `prompt_versions`: number, body, tier, source (`file` | `admin`), author, file hash, created_at. Versions are never updated. *Done: the model has no update path.*
- **15.1.2 MUST** — `php artisan prompts:sync` writes each prompt's contract from its file, and creates a version only for a prompt that has none — version 1, from the file's body and tier. It never reads the body of a prompt that already has a version, so no deploy can change what a prompt says. It runs on deploy ([docs/deploy.md](../deploy.md)) and at test bootstrap. *Done: two syncs make no second version; a file body changed after version 1 never reaches the registry; an editor edit is still active after a sync.*
- **15.1.3 WONT** — No reconciliation between a file's body and the active version. There is one writer of a body and it is the editor, so there is no divergence to detect, no waiting version and no flag. *Falsified if: a prompt's body ever needs to be deployable — at which point the question is whether the editor should exist, not how to merge two writers ([D65](../02-design.md)).*
- **15.1.4 MUST** — Sync fails, naming both, when a file drops a var the active version uses — whatever that version's source. A seeded body outlives its own file, and `render()` leaves an undeclared `{{ placeholder }}` in the prompt text rather than throwing, so this refusal is what stands between a file edit and a silently mangled prompt. *Done: one test for an editor's body, one for a seeded body.*
- **15.1.5 MUST** — `PromptRepository` keeps its interface and reads active versions, cached. [2.3.1](02-ai-layer.md)'s render rules are unchanged. *Done: the loader tests — missing var throws, unknown key throws — pass against the database-backed repository.*
- **15.1.6 MUST** — Every usage row carries `prompt_version_id`. *Done: no null in the acceptance journey.*
- **15.1.7 MUST** — `lesson.system` composes from the active version of each part ([2.3.3](02-ai-layer.md)). *Done: a persona edit leaves every other prompt's rendering byte-identical.*

## 15.2 Editor

- **15.2.1 MUST** — Admin → Prompts lists key, active version, source, and seven-day calls and cost. *Done: renders all 18 prompts.*
- **15.2.2 MUST** — Saving a new body or tier creates a version, activates it, and logs it. A missing or undeclared var is refused at save — both directions, because `render()` catches neither ([T20](../lessons.md)). *Done: feature tests.*
- **15.2.3 MUST** — A prompt that streams inside the request may move only between `cheap` and `mid` until the queued-streaming unit lands ([D63](../02-design.md)). `tier: session` prompts have no editable tier. The in-request list lives in code. *Done: the editor refuses `top` for `interview.knowledge`.*
- **15.2.4 MUST** — Any two versions can be diffed, and any version re-activated; re-activation is logged, writes no version, and is held to 15.2.3's tier rule so it cannot be a second door to a tier a save refuses. *Done: rolling back to v1 makes the next usage row name v1.*
- **15.2.5 MUST** — `php artisan prompts:pull` writes active versions back to `prompts/` byte-exact, so the repo, the test seeder and git history hold what is running. *Done: pull leaves a file the loader parses back to the active version's body, tier and contract; a sync after a pull makes no version.*

## 15.3 Experiments — **deferred 2026-09-21** ([D66](../02-design.md))

*Specced 2026-09-18 as SHOULD, to be built in this package's session. Nothing was built. The reasoning is [D66](../02-design.md); [D55](../02-design.md) still binds whatever replaces this.*

What was specced: `experiments` (prompt, status, started/stopped) and `experiment_variants` (prompt version, optional catalog model, weight), one running experiment per prompt, a variant assigned by a deterministic hash of experiment and course, `lesson.section` and `lesson.qa` following their `lesson.system`'s variant, and a comparison screen showing calls, latency, cost and failure rates per variant.

**Why it was deferred.** With quality scores excluded — 15.3.6's WONT, folded into this block — the only numbers a comparison can show are cost, latency and failure rate — which barely move between two wordings of one prompt on one model. And there is nothing to split: the development database holds nine courses across three users, so a 50/50 assignment gives four against five. The machinery would have been correct and measured nothing.

**What changes when it returns**, as its own package rather than an item here:

- Variants author as **non-activating saves**, so a candidate never runs at 100% before the split. This amends [15.2.2](#152-editor), which activates on every save.
- The **model is the primary axis and the body secondary**, inverting how the items above read.
- It needs **at least one quality signal**, which reopens that exclusion. [D66](../02-design.md) surveys the candidates — contract compliance, a golden set, Colin's own rating, student assessment outcomes, confusion proxies, an LLM judge — and the constraint they share: a score must hang off the `usage_events` row that produced the output, or it cannot be attributed to a prompt version.
- The **golden set** is the first thing to build, being the only candidate that pays off before there are students.

## 15.4 Out of scope

- **15.4.1 WONT** — Per-module prompts.
- **15.4.2 MAY** — A test proving the version resolver already receives the course, as the seam per-module prompts will use.

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. Edit `explainer.select` in admin; use the explainer. The usage row names the new version.
2. Deploy a change to that prompt's file. The prompt still runs the edit, and no version is added.
3. `prompts:pull`, commit, redeploy. The file now holds the edit, and still no version is added.
4. Roll `explainer.select` back to version 1 in admin; use the explainer. The usage row names version 1, and the action log holds the re-activation.

*A fifth step ran a two-variant experiment across two courses. It went with [15.3](#153-experiments--deferred-2026-09-21-d66).*
