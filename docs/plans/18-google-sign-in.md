# 18-google-sign-in — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [spec 18](../specs/18-email-and-google-sign-in.md) items 18.2.1–18.5.2 (code). 18.1 landed in 5f5e065.

## Items

- [ ] **18.2.1** — Socialite, `openid email profile`, keys from env via `config/services.php`. *No secret committed; consent screen without unverified-app warning (Colin, Google console).*
- [x] **18.2.2** — `google_accounts(user_id, sub)`, each unique; no nullable column on `users`.
- [x] **18.2.3** — found by `sub`, never email.
- [x] **18.2.4** — Google's answer held in the session, never in form fields.
- [x] **18.3.1** — one Continue with Google on Sign in and Register, same route.
- [x] **18.3.2** — known `sub` signs in. **.1** two-factor challenge first. **.2** soft-deleted refused as password sign-in refuses.
- [x] **18.3.3** — unknown `sub`, known email → Connect Google, password once. **.1** soft-deleted refused.
- [x] **18.3.4** — unknown both → Finish signing up; `InvitationValidationRules`; random password, `has_chosen_password` false.
- [x] **18.3.5** — every route an edge in 8.6; `FlowMapTest` passes.
- [ ] **18.3.6** — iPhone home-screen app ends signed in inside the app. *Device test — Colin.*
- [x] **18.4.1** — `users.has_chosen_password`, NOT NULL, appended; true on existing standard rows.
- [x] **18.4.2** — Confirm with Google on the confirm screen; matching `sub` only.
- [x] **18.4.3** — Set a password, no current-password field, behind confirmation.
- [x] **18.4.4** — view-as refuses 18.4.2's and 18.4.3's routes.
- [x] **18.5.1** — Keep with Google; finishes on 18.3.4's page; same row.
- [x] **18.5.2** — taken email or connected `sub` refuses keeping; row unchanged.

18.2.1: code done (`git grep` finds no secret; scopes pinned by `GoogleSignInTest`); the consent-screen check waits on Colin creating the client per `docs/deploy.md` → Google sign-in. 18.3.6: blocked, needs production and an iPhone.

## Open questions for Colin

*Answered 2026-10-08:* 1 — yes, stay signed in. 2 — yes; written into the spec as 18.3.3.2. 3 — Colin asked whether it's normal; it isn't (apps confirm deletion with the sign-in method). Offered: deletion behind `password.confirm` for a Google-only student, like 18.4.3. Awaiting his yes.

1. **Remember the device on a Google sign-in?** Built as yes (`GoogleSignIn::complete`, `remember: true`), because there is no checkbox on the Google path and the installed app shouldn't sign students out. Password sign-in leaves Remember me unticked.
2. **An account already connected to a different Google account.** The spec doesn't cover this. If a second Google account with the same email signs in, the build refuses with "This account signs in with a different Google account." Without that check the student would hit a 500 from the unique index. If Colin agrees, it becomes a spec sub-item (18.3.3.2) at landing.
3. **Deleting the account still asks for a password.** `profile.destroy` validates `current_password`, and the spec's list of four password checks leaves it out. A Google-only student has to set a password before they can delete their account. Options: accept that, or put deletion behind `password.confirm` the way 18.4.3 does.

## Log

- 2026-10-08, Colin locally: "Missing required parameter: client_id" from Google. Local `.env` has no `GOOGLE_*` values; the client isn't created yet. Not a code fault.

Design, before building:

- One start route (`google.redirect`) and one callback (`google.callback`), so Google's console carries one redirect URI. The callback reads who is signed in: a guest is signing in, a demo is keeping, anyone else is confirming. Socialite's `state` binds the callback to the session that started it.
- Set a password is its own route (`user-password.store`, behind `password.confirm`), refusing an account that has chosen one; Update password is unchanged.
- `google.*` and `user-password.store` join `ViewAsStudent::SIGN_IN_ROUTES`.
- Google sign-in remembers the device (`remember: true`) — there is no checkbox on the Google path. Ask Colin.
- `prompt=select_account` dropped: `Socialite::driver()` returns the `Provider` contract, which has no `with()`/`setScopes()` (PHPStan), and the default scopes are already the spec's.
