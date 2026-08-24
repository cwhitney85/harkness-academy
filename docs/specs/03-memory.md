# Spec 3 — Memory Subsystem

*Provides: the path API (3.2) — the only door to memory — consumed by packages 4–6. Consumes: 1. See PLAN.md for status.*

Design ground rules (ledger D3a/D3b/D7): virtual file tree in Postgres; workflow-initiated writes only; model-facing documents live in the tree, app-queried state lives in relational rows.

- **3.1 MUST** — `memories` (user, path, content), unique (user, path); `memory_versions` on every write. *Done: write → read-back → version row.*
- **3.2 MUST** — Path API `get(path)` / `put(path, content)` / `list(prefix)` — the only door to memory. *Done: feature tests; no other class touches the tables.*
- **3.3 MUST** — Derived module rollup: `{module}/WORKING_KNOWLEDGE.md` renders pointer lines (one per completed course → subtopic WK path, `{module}/{subtopic}/WORKING_KNOWLEDGE.md` per ledger D8, deduped by path) from the courses table at read time; never materialized, zero drift. Module quizzes walk the lines and pull pointed-to WKs into context. **Blocked on 4.1** — the courses table and the module/subtopic catalog it reads are package 4's; build this as the first item of the 4.1 session. *Done: completing a course changes the rollup with no write step; incomplete courses absent.*
- **3.4 SHOULD** — `artisan memory:show {user} {path?}` inspector. *Done: prints tree or file.*
