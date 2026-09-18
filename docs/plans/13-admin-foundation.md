# 13-admin-foundation — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [spec 13](../specs/13-admin-foundation.md), all items. Branch `pkg/13-admin-foundation` off `unit/admin-and-performance` (W6).

## Items

- [ ] **13.1.1** — `users.role`, default `student`; every dev row reads `student`.
- [ ] **13.1.2** — one `admin` gate on every `/admin*` route; student 403.
- [ ] **13.1.3** — `php artisan user:role {email} {role}`; no web route writes `role`.
- [ ] **13.1.4** — admin on `demo` plan gets demo depth.
- [ ] **13.1.5** — `/admin` needs a passkey or confirmed 2FA; otherwise sent to set one up.
- [ ] **13.2.1** — `admin_actions`, append-only.
- [ ] **13.2.2** — every non-GET `/admin*` route writes exactly one row.
- [ ] **13.2.3** — Admin → Log, filterable by actor, action, subject.
- [ ] **13.3.1** — admin shell artboard + `app.css` in one commit.
- [ ] **13.3.2** — nav shows exactly the landed entries (13: Log).
- [ ] **13.4.1** — preview: GET only, gateway refuses.
- [ ] **13.4.2** — act-as needs a reason.
- [ ] **13.4.3** — acting admin in `Context`, stamped on usage rows, memory versions, admin_actions.
- [ ] **13.4.4** — banner with Exit in each student shell.
- [ ] **13.4.5** — start/step-up/exit logged; 60-minute expiry; ends at logout.
- [ ] **13.4.6** — `/admin*` unreachable while viewing, except Exit.
- [ ] **13.4.7** — WONT: viewing another admin (refused).

## Answers from Colin (2026-09-18)

- **13.4.3 columns:** 13 adds `usage_events.acting_user_id` and `memory_versions.acting_user_id` itself; 14.3.1 and 16.3.1 find them already there.
- **Entry point before 17:** 13 ships the start route plus a bare student picker at `/admin` in the admin shell. **Note for 17.1.4:** replace the picker with the Users screen.
- **Preview GETs that write:** the section stream writes `enterSection` before its AI call. In preview a stream is refused unless it is a replay of an already-taught section; the interview stream (always a new turn) is refused outright. Colin: "I want to be able to preview users but I don't want it to affect the course."

## Log

- `design` skill is not available this session: the artboard source lands in `docs/design/canvas/`, the published canvas still needs re-seeding.
- **Trap (→ lessons):** `migrate:fresh` (and so `RefreshDatabase`) drops tables and views but not Postgres functions. The append-only trigger's `CREATE FUNCTION` failed on every test run after the first; it is `CREATE OR REPLACE` now.
