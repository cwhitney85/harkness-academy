# The disposable tier

*Ledger [D27](../02-design.md); spec [9.6](../specs/09-documentation.md).*

**One work unit, one file: `docs/plans/<unit>.md`.** It is created from the unit's spec items when the work starts and **deleted in the commit that lands the work**. Nothing else lives here.

This directory exists because session narrative — what was tried, what broke, why an approach was abandoned, what a screen measured — has to go *somewhere* while a unit is in flight, and had nowhere to be retired to. So it accreted into PLAN.md permanently: 58.7% of that file's Status section was a chronological work journal stored in table cells.

## What a plan file holds

- The spec items in scope, copied or linked, with a running disposition for each.
- What has been tried and what it did.
- Open questions for Colin, and the answers when they come.
- Notes from running an acceptance test. **Not the test itself** — a test is durable and lives in its spec ([D29](../02-design.md)); notes on one run are not.

## What happens when the unit lands

Triage the file, in this order, then delete it:

1. **A decision with rationale** → a new entry in [`docs/02-design.md`](../02-design.md), numbered next, naming the package and spec item it arose under. Add its row to the ledger index.
2. **A tooling trap or an incident postmortem** → [`docs/lessons.md`](../lessons.md).
3. **A change to what the app does** → [`docs/system.md`](../system.md).
4. **A change to a rule** → the spec item that sets it.
5. **Everything else** → deleted. Implementation description and verification records are discarded, not relocated (9.3.3). `git log docs/plans/<unit>.md` recovers any of it if it is ever wanted.

A unit that has landed leaves no file here.

## The file

```markdown
# <unit> — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** <spec items, each linked>

## Items

- [ ] **<item>** — <what done looks like, from the spec>

## Log

<what was tried, what it did, what is still open>
```
