# Spec 3 — Memory Subsystem

*Provides: the path API (3.2) — the only door to memory — consumed by packages 4–6. Consumes: 1. See PLAN.md for status.*

Design ground rules (ledger D3a/D3b/D7): virtual file tree in Postgres; workflow-initiated writes only; model-facing documents live in the tree, app-queried state lives in relational rows.

- **3.1 MUST** — `memories` (user, path, content), unique (user, path); `memory_versions` on every write. *Done: write → read-back → version row.*
- **3.2 MUST** — Path API `get(path)` / `put(path, content)` / `list(prefix)` — the only door to memory. *Done: feature tests; no other class touches the tables.*
- **3.3 MUST** — Derived module rollup: `{module}/WORKING_KNOWLEDGE.md` renders one pointer line per subtopic WK document that exists under the module (`{module}/{subtopic}/WORKING_KNOWLEDGE.md` per ledger D8), each annotated with the skill levels completed there. **Existence comes from the memory tree, levels from the courses table** — a subtopic the student withdrew from still holds real interview knowledge, so keying existence off completed courses would hide it. Never materialized, zero drift. Module quizzes walk the lines and pull pointed-to WKs into context. **Blocked on 4.1** — the courses table and the module/subtopic catalog it reads are package 4's; build this as the first item of the 4.1 session. *Done: completing a course changes the rollup with no write step; a subtopic with a WK document but no completed course appears with no levels named; a module with no WK documents renders an empty rollup.*
- **3.4 SHOULD** — `artisan memory:show {user} {path?}` inspector. *Done: prints tree or file.*
