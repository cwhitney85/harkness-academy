# Spec 3 — Memory Subsystem

*Provides: the path API (3.2) — the only door to memory — consumed by packages 4–6. Consumes: 1.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D3a](../02-design.md) a virtual file tree in Postgres · [D3b](../02-design.md) workflow-initiated writes only · [D7](../02-design.md) the data model · [D7a](../02-design.md) memory is not deleted with the course · [D8](../02-design.md) WORKING_KNOWLEDGE is subtopic-scoped · [D10.1](../02-design.md) `document()` returns content and version together.

Design ground rules (ledger D3a/D3b/D7): virtual file tree in Postgres; workflow-initiated writes only; model-facing documents live in the tree, app-queried state lives in relational rows.

- **3.1 MUST** — `memories` (user, path, content, and its owner — a course, or the subtopic whose working knowledge it is, [16.3.1](16-catalog-removal-ownership.md)), unique (user, path); `memory_versions` on every write, naming the course that wrote it. *Done: write → read-back → version row.*
- **3.2 MUST** — Path API `get(path)` / `put(course, path, content)` / `list(prefix)` — the only door to memory. `put` takes the course that writes and refuses a path outside its own folder and its subtopic's working knowledge ([16.3.3](16-catalog-removal-ownership.md)); a removed document is read by none of them ([16.2.4](16-catalog-removal-ownership.md)). *Done: feature tests; no other class touches the tables.*
- **3.3 MUST** — Derived module rollup: `{module}/WORKING_KNOWLEDGE.md` renders one pointer line per subtopic WK document that exists under the module (`{module}/{subtopic}/WORKING_KNOWLEDGE.md` per ledger D8), each annotated with the skill levels completed there. **Existence comes from the memory tree, levels from the courses table** — a subtopic the student withdrew from still holds real interview knowledge, so keying existence off completed courses would hide it. Never materialized, zero drift. Module quizzes walk the lines and pull pointed-to WKs into context. **Blocked on 4.1** — the courses table and the module/subtopic catalog it reads are package 4's; build this as the first item of the 4.1 session. *Done: completing a course changes the rollup with no write step; a subtopic with a WK document but no completed course appears with no levels named; a module with no WK documents renders an empty rollup.*
- **3.4 SHOULD** — `artisan memory:show {user} {path?}` inspector. *Done: prints tree or file.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

Against a student with no rows in `memories`:

1. Drive a workflow that writes memory — an interview completing is the shortest — so `{module}/{subtopic}/WORKING_KNOWLEDGE.md` exists. `artisan memory:show {user}` prints the tree; naming the path prints the document.
2. Drive a second write to that same path. There is still one `memories` row and now two `memory_versions`, and the earlier content is readable from the older version.
3. Read `{module}/WORKING_KNOWLEDGE.md`. It is not in the `memories` table and it renders anyway: one pointer line for the subtopic, annotated with the levels completed there. Add a second subtopic under the module with a working-knowledge document, and a second line appears with no write step anywhere.
4. Withdraw the course that wrote the first document. The document, its versions and its rollup line all survive — memory outlives the course.
5. `grep` the tree for direct use of the `memories` or `memory_versions` tables outside the path API. There is none; every step above went through the one door.
