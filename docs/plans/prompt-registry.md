# prompt-registry — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** spec 15 items [15.1.1–15.1.7](../specs/15-prompt-registry.md). 15.2 (editor) and 15.3 (experiments) are not in this session.

## Items

- [x] **15.1.1** — `prompts` (key, output, vars from the file) and `prompt_versions` (number, body, tier, source, author, file hash, created_at), never updated. *Done: the model has no update path.*
- [x] **15.1.2** — `prompts:sync` makes one new version per changed file; runs on deploy and at test bootstrap. *Done: two syncs make no second version; one file change makes exactly one.*
- [x] **15.1.3** — A file version activates only over a `file`-sourced active version; otherwise stored and flagged. *Done: one test per case.*
- [x] **15.1.4** — Sync fails when a file drops a var the active admin version uses. *Done: test.*
- [x] **15.1.5** — `PromptRepository` keeps its interface, reads active versions, cached. *Done: missing var throws, unknown key throws, against the database-backed repository.*
- [x] **15.1.6** — Every usage row carries `prompt_version_id`.
- [x] **15.1.7** — `lesson.system` composes from the active version of each part. *Done: a persona edit leaves every other prompt byte-identical.*

## Log

Shape chosen before writing code:

- `PromptDefinition` loses `path` (nothing read it; `prompts:pull` can derive it from the key, which the loader already forces to agree) and stays the contract-plus-body value both halves serve.
- The file-scanning half of today's `PromptRepository` moves out to `PromptFiles`, which `prompts:sync` and the file-auditing tests (2.3.2 inventory, 2.3.5 fixtures — its data provider is static and runs before the app boots) use. `PromptRepository` becomes the database-backed reader.
- The active version is a pointer on `prompts` rather than a flag on `prompt_versions`: one row can then be active, structurally.
- "Newer file version available" is derived (active is `admin` and a `file` version is newer), not stored.

Found while wiring the test bootstrap: `RefreshDatabase::afterRefreshingDatabase()` runs per test and inside the transaction, and `migrateDatabases()` cannot be overridden from `Tests\TestCase` because a trait method in the subclass beats an inherited one. `protected $seeder` is the hook that works. → lessons T18.

The dev database has the registry: `php artisan migrate` then `prompts:sync` on 2026-09-20 made 18 prompts at version 1, and a second run made nothing.
