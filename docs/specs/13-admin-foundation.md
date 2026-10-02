# Spec 13 — Admin Foundation

*Provides: the admin role, the admin shell, the action log, and viewing the app as a student. Consumes: 1 (accounts, Fortify's second factors), 8.1 (the design canvas). First package of the `admin-and-performance` unit (13–17); 14 and 16 open once it lands. Specced 2026-09-18.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D30](../02-design.md) three shells, amended by D61 to four · [D60](../02-design.md) viewing as a student is read-only until you step up with a reason · [D61](../02-design.md) role is a column, apart from plan and skill level.

## The shape

**There is one admin, Colin, and he is also a student.** Role says what a person may administer; plan says how long their courses are; skill level says how they are taught. None of the three reads another.

**Every admin write leaves one row in `admin_actions`, and nothing else in the unit keeps its own audit.** Removals (16.2), prompt edits (15.2), price edits (14.4), plan changes (17.1) and view-as sessions (13.4) all write through the one recorder.

---

## 13.1 Role

- **13.1.1 MUST** — `users.role`: string, default `student`, values `student` | `admin`, added by a migration that only adds. *Done: after migrating the dev database, every existing row reads `student`.*
- **13.1.2 MUST** — One `admin` gate guards every `/admin*` route, and every route an admin controller answers wherever it is mounted — admin controllers alone read removed memory ([16.3.4](16-catalog-removal-ownership.md)). *Done: a route-list test finds the gate on every such route; a student gets 403 on each.*
- **13.1.3 MUST** — A role is granted or revoked only by `php artisan user:role {email} {role}`. *Done: command test; no web route writes `role`.*
- **13.1.4 MUST** — Role is independent of plan and skill level ([4.5.6](04-course-pipeline.md)). *Done: an admin on the `demo` plan gets demo depth.*
- **13.1.5 MUST** — `/admin` requires a confirmed second factor — passkey or two-factor code. An admin without one is sent to set one up. *Done: feature test — an admin with no second factor is redirected to setup; with one, `/admin` renders.*

## 13.2 Action log

- **13.2.1 MUST** — `admin_actions`: actor, action, subject type and id, reason (nullable), payload (json), created_at. No update or delete path exists. *Done: the model exposes neither; a test proves the table only grows.*
- **13.2.2 MUST** — Every admin write records through one recorder, in the same transaction as the write, so a change never stands without its row. *Done: an architecture test finds that each non-GET `/admin*` route writes exactly one row, and that with the row's insert failing, every table is as it was.*
- **13.2.3 SHOULD** — Admin → Log lists actions, filterable by actor, action and subject. A row keeps naming its admin and its subject after either is removed or deleted; a subject the demo sweep deleted outright — the only hard delete (D68) — reads `deleted demo #id`. *Done: renders every action type in a fixture; a removed course, a deleted student and a deleted admin are each named, and a swept demo is labelled.*

## 13.3 Shell

- **13.3.1 MUST** — The admin shell is drawn on the design canvas before it is built, as the fourth shell beside D30's three. Canvas source and `resources/css/app.css` change in the same commit ([8.1.3](08-interface.md)). *Done: the artboard and the CSS land in one commit.*
- **13.3.2 MUST** — Navigation: Users, Courses, Catalog, Prompts, Models, Usage, Log. An entry appears only once its screen has landed. *Done: a test per package asserts exactly the landed entries.*

## 13.4 Viewing as a student

- **13.4.1 MUST** — **Preview** renders every page as the student sees it, refuses every non-GET request, and the gateway refuses to start an AI call. A stream that would teach something new is refused too, because teaching writes before the call starts: a section stream answers only with a section already taught, and an interview stream not at all ([D60](../02-design.md), amended). *Done: in preview, posting a lesson answer returns 403 and writes no `usage_events` row and no `memory_versions` row.*
- **13.4.2 MUST** — **Act-as** is a second step that requires a reason. Writes and AI calls then proceed as the student. *Done: without a reason the step is refused; with one, a lesson answer goes through.*
- **13.4.3 MUST** — The acting admin's id rides in Laravel `Context`, which reaches queued jobs, and is stamped on `usage_events.acting_user_id` ([14.3.1](14-routing-and-usage.md)), `memory_versions.acting_user_id` ([16.3.1](16-catalog-removal-ownership.md)) and `admin_actions` (as its actor). 13 adds both `acting_user_id` columns, since 14 and 16 open only after it lands. *Done: an act-as lesson turn stamps the admin on its usage row, and on the version the queued `UpdateWorkingKnowledge` writes.*
- **13.4.4 MUST** — A banner naming the student and the mode, with Exit, stands on every screen during preview or act-as. *Done: jsdom test renders it in each student shell.*
- **13.4.5 MUST** — Start, step-up and exit are each logged. The session ends at admin logout or after 60 minutes. *Done: three log rows; a clock-advanced test ends the session.*
- **13.4.6 MUST** — While viewing as a student, `/admin*` is unreachable except step-up and Exit; any other `/admin*` URL sends the admin back to the student's dashboard, where the banner's Exit is. *Done: feature test.*
- **13.4.7 WONT** — Viewing another admin.
- **13.4.8 MUST** — In either mode, the student's sign-in is unreachable: the security page, password change, password confirmation, two-factor and passkey routes send the admin back to the student's dashboard, as 13.4.6 does, and a profile save refuses a new email, because password resets go there. The session's password confirmation is the admin's, and would otherwise open every gate on the student's credentials ([T26](../lessons.md)). *Done: with the admin's password confirmed, preview cannot read the student's two-factor secret or recovery codes, and act-as cannot disable their second factor, register a passkey, change their password or change their email.*

## Acceptance test

*The journey this package's items compose into (ledger [D29](../02-design.md)).*

1. `php artisan user:role colin@… admin`.
2. Open `/admin` — sent to set up a second factor. Set one up; `/admin` opens.
3. Preview a student and open their lesson. Answering is refused; the banner is showing.
4. Step up to act-as with a reason; answer a question. It goes through.
5. `select acting_user_id from usage_events order by id desc limit 1` returns your id.
6. Admin → Log lists preview started, stepped up, exited.
