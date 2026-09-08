# Spec 7 — PWA Shell

*Provides: an installable home-screen app. Consumes: 1 and **8** — the design language, crest and responsive behaviour are package 8's, so this package cannot land before it.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [R2](../02-design.md) minimal PWA scope, no offline · [D17](../02-design.md) package 7 owns perceived speed and the phone check · [D21](../02-design.md) the palette and the crest the icons derive from.

- **7.1 MUST** — Web app manifest carrying `name`, `short_name`, `icons` (192px, 512px, and one maskable, all from 8.3.5's crest), `start_url`, `display: standalone`, and `theme_color` / `background_color` matching 8.3.1's tokens. Plus an iOS install-instructions page, since iOS offers no install prompt. *Done: add-to-home-screen verified on one real iPhone and one real Android, and the launched app shows no browser chrome.*
- **7.2 WONT** — Offline mode (v1.2 candidate).
- **7.3 WONT** — A service worker. Chrome dropped the service-worker requirement for menu-based install in v108 (mobile) and v112 (desktop), so none is needed to satisfy 7.1. Adding one later is cheap — one file plus a registration call — but withdrawing one is not: a registered worker keeps serving its cache until explicitly unregistered, so a bad deploy can pin stale assets on machines you cannot reach, and the fix ships through the thing that is broken. Add one deliberately, with an unregister path, never speculatively.
- **7.4 WONT** — An in-page install button. `beforeinstallprompt` still requires a fetch handler to be present, which would mean a service worker, which 7.3 refuses. Install is discovered through the browser's own menu.

*Note: "Lighthouse installable" was this package's original done-condition. Lighthouse removed its PWA category in v12 (May 2024), so the check no longer exists; 7.1's real-device verification replaces it.*

## Acceptance test

*The journey this package's items compose into. The per-item done-conditions each prove one item; this proves they work together (ledger [D29](../02-design.md)). It is runnable without opening another spec.*

On a deployed build, with one real iPhone and one real Android:

1. Open the app in Safari on the iPhone and add it to the home screen from Safari's own share menu — no in-page button offers to do it, and none needs to.
2. Open the app in Chrome on the Android and install it from Chrome's own menu.
3. Launch both from the home screen. Each shows the crest as its icon and the app's short name beneath it; each opens at the start URL with no browser chrome, in the app's own colours rather than the browser's default.
4. Reach the install-instructions page in a normal browser tab. It describes the steps that just worked on iOS, which is the only place iOS students can learn them.

Nothing in this test works offline, and nothing is expected to.
