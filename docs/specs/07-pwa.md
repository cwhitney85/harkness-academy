# Spec 7 — PWA Shell

*Provides: an installable home-screen app. Consumes: 1 and **8** — the design language, crest and responsive behaviour are package 8's, so this package cannot land before it. See PLAN.md for status.*

- **7.1 MUST** — Web app manifest carrying `name`, `short_name`, `icons` (192px, 512px, and one maskable, all from 8.3.5's crest), `start_url`, `display: standalone`, and `theme_color` / `background_color` matching 8.3.1's tokens. Plus an iOS install-instructions page, since iOS offers no install prompt. *Done: add-to-home-screen verified on one real iPhone and one real Android, and the launched app shows no browser chrome.*
- **7.2 WONT** — Offline mode (v1.2 candidate).
- **7.3 WONT** — A service worker. Chrome dropped the service-worker requirement for menu-based install in v108 (mobile) and v112 (desktop), so none is needed to satisfy 7.1. Adding one later is cheap — one file plus a registration call — but withdrawing one is not: a registered worker keeps serving its cache until explicitly unregistered, so a bad deploy can pin stale assets on machines you cannot reach, and the fix ships through the thing that is broken. Add one deliberately, with an unregister path, never speculatively.
- **7.4 WONT** — An in-page install button. `beforeinstallprompt` still requires a fetch handler to be present, which would mean a service worker, which 7.3 refuses. Install is discovered through the browser's own menu.

*Note: "Lighthouse installable" was this package's original done-condition. Lighthouse removed its PWA category in v12 (May 2024), so the check no longer exists; 7.1's real-device verification replaces it.*
