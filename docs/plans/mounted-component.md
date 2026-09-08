# mounted-component — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [4.7](../specs/04-course-pipeline.md) · [8.8.10](../specs/08-interface.md) — both instances of the class named in [D28](../02-design.md).

## Items

- [x] **4.7** — The knowledge → goals transition opens the goals stream without a page refresh. *Done: completing the knowledge interview lands on the goals interview with the tutor's first question streaming, no error, no manual reload; a test asserts the goals stream is requested after a client-side visit from the knowledge URL.*
- [ ] **8.8.10** — The explainer closes when the student moves to the next lesson, and stays open across every other interaction in the current lesson. *Done: open the explainer, complete a checkpoint, continue — open; move to the next lesson — closed; close it by hand — stays closed.*
- [x] **D28's rule** — the standing convention lands in `CLAUDE.md`.

## Log

### What Inertia actually does on a visit (read from `node_modules/@inertiajs/*` 3.7.0)

The page component is rendered as `createElement(Component, { key, ...props })`, and the key
is recomputed on every swap:

    key: preserveState ? current.key : Date.now()      // @inertiajs/react App

So Inertia **already remounts the page component on any visit that does not preserve state**,
and the mounted-component defect is not "same component ⇒ stays mounted". It is narrower:
*a state-preserving visit that lands on a different URL.* Where `preserveState` comes from:

| navigation | `preserveState` | remounts? |
|---|---|---|
| `<Link href>` (GET) | `preserveState ?? _method !== 'get'` → **false** | yes |
| `router.visit(url)` GET | default **false** | yes |
| `router.post/put/patch/delete` | **true** by default | no |
| `router.reload()` | forced **true** | no |

### 4.7 — real, and it is `router.reload()`

`settle()` in `courses/interview.tsx` is `router.reload()`, so `preserveState` is forced true.
The knowledge interview's last turn calls `settle()`, the server redirects to the goals URL,
and the component is swapped with the old key — `opened.current` is still `true`, the open
effect does not fire, and the student sits on a goals interview that never opened. Same for
the Done button, `router.post(urls.complete)`, whose `preserveState` also defaults to true.

Fix: key the screen on `kind`, which is what the state inside it describes.

### 8.8.10 — the premise does not hold

Both routes to the next lesson are plain `<Link href>` GETs — the rail in `StudyShell` and
"Next: {title}" on S7 — so `preserveState` is false and the whole screen remounts. `selected`
and `explaining` are gone with it. The three done-conditions hold as written with no change:

- checkpoint answered (`postJson`, no visit) and "continue" (a stream, no visit) — nothing
  navigates, panel stays open;
- next lesson (`<Link>`) — remount, panel closed;
- closed by hand (`setExplaining(null)`) — stays closed.

Free proxy used to confirm the remount in the running app rather than only in the bundle: type
into the ask-a-question textarea (`draft`, `useState` in the same component), click a rail link
to another lesson, see whether the draft survives. Result: <pending>

### Dusk (Colin's choice for 4.7's test) does not install

`composer require --dev laravel/dusk` cannot resolve. Every Dusk release through v8.6.0 requires
`guzzlehttp/guzzle ^7.5`; this project is on guzzle 8.0.2 (`laravel/framework` and `aws/aws-sdk-php`
both accept `^7.8.2 || ^8.0`). With `-W` it resolves only by **downgrading guzzle 8.0.2 → 7.15.5**
and dragging along ~20 other upgrades including `laravel/framework v13.26.1 → v13.30.1`.

Not done. Downgrading the HTTP client the AI gateway streams over, to add a test runner, is a
trade for Colin to make, not one to make silently.

A Dusk test would also have needed the AI faked **inside the served process** — `GatewayAgent::fake()`
swaps a container singleton and Dusk drives a separate `php artisan serve`. The knowledge → goals
reproduction genuinely needs it: the bug only appears when the knowledge stream opened in *this*
mount (`opened.current === true`), so both streams and `wk.create` have to be reachable.

### vitest instead (Colin, 2026-09-08)

`vitest` + `jsdom` + `@testing-library/react`, a `vitest.config.ts` separate from `vite.config.ts`
(the Laravel, Wayfinder and font plugins all reach for a running app), `npm test`, and
`npm run test` added to `composer ci:check`. One test, `interview.test.tsx`: render at
`kind: 'knowledge'`, `rerender` at `kind: 'goals'` — which is what a state-preserving Inertia
visit hands the component — and assert both stream URLs were requested.

Anti-vacuity: with the `key` removed the test fails on its last assertion, with only the
knowledge stream requested. Restored, it passes. Full PHP suite still green (611).

Two jsdom gaps had to be filled, and they must be **hoisted above the imports**: `use-mobile`
calls `matchMedia` at module scope, so a `beforeAll` is already too late.

### Open for Colin

1. 8.8.10's live check needs a logged-in Chrome. Passwords are not mine to type.
2. D28's statement of the class is too broad, and its fourth instance does not hold. Amend both?
