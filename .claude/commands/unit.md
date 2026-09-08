---
description: Start a cross-cutting work unit that no package number names (usage: /unit mounted-component 4.7 8.8.10)
---

Start work unit `$ARGUMENTS`. The first argument is the unit's name; the rest are the spec items in its scope.

This is `/package`'s companion for work that crosses packages — a defect class, a migration, a repair that touches four specs. Use `/package <n>` instead whenever one package number names the whole job.

## Read, in this order

1. `CLAUDE.md` — the tiers, the standing conventions, the git conventions.
2. The spec item for each item in scope, in `docs/specs/`, **and its spec's decisions block** — the ledger entries listed at that spec's head bind this work.
3. `docs/system.md` if you need to know what the app currently does.

## Then open the plan file

`docs/plans/<unit>.md`. If it does not exist, create it from the scope — see [docs/plans/README.md](../../docs/plans/README.md) for the shape. It is **disposable**: it is where this session's narrative goes, and it is deleted in the commit that lands the unit.

## Rules for this session

- Scope is the listed items only. Build each to its done-condition, tests passing as you go.
- Session narrative — what was tried, what broke, why an approach was abandoned — goes in the plan file and nowhere else. It never goes in a spec, in a commit body as a story, or in a status table.
- If a spec gap appears, or a recorded decision looks wrong in practice, stop and ask. Decisions live in `docs/02-design.md`; don't diverge silently.
- Commit at done-condition granularity, spec item number in the message. No signing, co-author trailers, or attribution lines.

## Landing it

Triage the plan file into the durable tier — decisions to the ledger with an index row, traps and postmortems to `docs/lessons.md`, behaviour changes to `docs/system.md`, rule changes to the spec item — then **delete the plan file in the same commit as the last of the work**. `git log` is where the rest of it lives.
