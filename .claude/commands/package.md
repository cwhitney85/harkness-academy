---
description: Start a build session scoped to one spec package (usage: /package 2, optionally /package 2 items 2.1-2.4)
---

## Read, in this order

1. `CLAUDE.md` — the tiers, the standing conventions, the git conventions.
2. `docs/specs/` — the spec for package $ARGUMENTS, **and its decisions block at the head**.
3. The ledger entries that block names, in `docs/02-design.md`. They are settled.
4. `docs/system.md` if you need to know what the app currently does, and `docs/lessons.md` before you fight a tool.

Then open `docs/plans/<unit>.md` for this session's work, creating it from the items in scope if it does not exist — see [docs/plans/README.md](../../docs/plans/README.md).

## Scope

Package $ARGUMENTS only, narrowed further to the listed items if the arguments name any. Build each item to its done-condition, tests passing as you go.

## Rules for this session

- Do not modify other packages. If you need an interface that hasn't landed on master, stop and say so.
- If you hit a spec gap, or a recorded decision seems wrong in practice, stop and ask instead of silently diverging.
- Session narrative goes in the plan file and nowhere else.
- Commit at done-condition granularity: one spec item (or small cluster) per commit, spec item number in the message (e.g. `2.4: caching spike — workaround for laravel/ai#119`). No signing, co-author trailers, or attribution lines.

## Landing it

Triage the plan file into the durable tier and delete it in the commit that lands the last of the work. Report which done-conditions now pass — package state lives in the spec's done-conditions, not in a status table.
