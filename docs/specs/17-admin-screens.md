# Spec 17 — People, Courses and Usage Screens

*Provides: the admin screens used day to day. Consumes: 13 (the shell, view-as, the action log), 14 (the fuller usage row, the catalog), 16 (removal, memory ownership). Last package of the `admin-and-performance` unit. Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D51](../02-design.md) `plan` is a config key · [D56](../02-design.md) the fuller usage row · [D62](../02-design.md) admin changes plans; billing waits for a price.

---

## 17.1 Users

- **17.1.1 MUST** — The list shows name, email, plan, role, joined, course count and cost to date excluding act-as rows, with search and a plan filter. *Done: figures match SQL on a fixture.*
- **17.1.2 MUST** — The detail shows the profile; courses, removed ones marked; cost by feature; and the memory tree read-only, each document's history naming the writing course and any acting admin ([16.3.4](16-catalog-removal-ownership.md)). Admin → Catalog → Refusals' student link ([16.1.6](16-catalog-removal-ownership.md)) points at the student list until this page exists, and is retargeted here. *Done: renders Colin's account from the dev database.*
- **17.1.3 MUST** — Changing a plan offers only the plans in config, and is logged. *Done: feature test; a plan not in config is refused.*
- **17.1.4 MUST** — Preview and act-as start here ([13.4](13-admin-foundation.md)). This replaces the stand-in student list 13 ships at `/admin` and its rail link, "View as a student".
- **17.1.5 WONT** — Editing memories or a student's profile.
- **17.1.6 MUST** — An account its student deleted is restored from its page, with a reason, logged ([D68](../02-design.md)). It signs in again with every course and document as it was, since deleting it touched nothing below the user row. *Done: feature test — refused without a reason; restored, logged and able to sign in with one; a live account is a 409.*

## 17.2 Courses

- **17.2.1 MUST** — The list shows student, module and subtopic, level, attempt, standing (including removed), cost and created, with filters. *Done: fixture.*
- **17.2.2 MUST** — The detail shows criteria and working knowledge; the syllabus and its revisions; lessons with sections and messages; checkpoints and results; homework, quizzes and attempts; the final exam; generation requests and failures; cost by feature and the course's usage rows. *Done: renders Colin's course.*
- **17.2.3 MUST** — Remove and Restore ([16.2](16-catalog-removal-ownership.md)).
- **17.2.4 WONT** — Interview transcripts; they are discarded after summary ([11.4](11-generation-off-the-request.md), confirmed).

## 17.3 Usage

- **17.3.1 MUST** — The overview shows cost by day, by feature and by plan over a date range. *Done: matches SQL.*
- **17.3.2 MUST** — The performance table shows, per task — a prompt, with its tier ([D52](../02-design.md)) — × model: calls, median and p95 latency, mean input and output tokens, cache share of input, cost per call, failure and failover rates. *Done: matches SQL on a fixture.*
- **17.3.3 MUST** — Act-as rows are excluded by default, with a toggle. *Done: test.*
- **17.3.4 SHOULD** — An event browser, filterable, linking each row to its user, course and lesson. *Done: fixture.*
- **17.3.5 SHOULD** — The performance table loads in under a second on a million seeded rows, adding an index if needed. *Falsified by: a benchmark over one second.*
- **17.3.6 WONT** — Quality scores and exports.

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. Open your user: courses, cost, and a memory history naming the course behind each version.
2. Open your course: every part of 17.2.2 renders.
3. Change a demo user's plan to `standard`; Admin → Log shows it.
4. Usage → performance shows `mid` × its model with median, p95 and cost, matching a hand-run SQL query.
