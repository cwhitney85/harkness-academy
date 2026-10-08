# 18-google-sign-in — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [spec 18](../specs/18-email-and-google-sign-in.md) items 18.2.1–18.5.2 (code). 18.1 landed in 5f5e065.

## Items

- [ ] **18.2.1** — Socialite, `openid email profile`, keys from env via `config/services.php`. *No secret committed; consent screen without unverified-app warning (Colin, Google console).*
- [ ] **18.2.2** — `google_accounts(user_id, sub)`, each unique; no nullable column on `users`.
- [ ] **18.2.3** — found by `sub`, never email.
- [ ] **18.2.4** — Google's answer held in the session, never in form fields.
- [ ] **18.3.1** — one Continue with Google on Sign in and Register, same route.
- [ ] **18.3.2** — known `sub` signs in. **.1** two-factor challenge first. **.2** soft-deleted refused as password sign-in refuses.
- [ ] **18.3.3** — unknown `sub`, known email → Connect Google, password once. **.1** soft-deleted refused.
- [ ] **18.3.4** — unknown both → Finish signing up; `InvitationValidationRules`; random password, `has_chosen_password` false.
- [ ] **18.3.5** — every route an edge in 8.6; `FlowMapTest` passes.
- [ ] **18.3.6** — iPhone home-screen app ends signed in inside the app. *Device test — Colin.*
- [ ] **18.4.1** — `users.has_chosen_password`, NOT NULL, appended; true on existing standard rows.
- [ ] **18.4.2** — Confirm with Google on the confirm screen; matching `sub` only.
- [ ] **18.4.3** — Set a password, no current-password field, behind confirmation.
- [ ] **18.4.4** — view-as refuses 18.4.2's and 18.4.3's routes.
- [ ] **18.5.1** — Keep with Google; finishes on 18.3.4's page; same row.
- [ ] **18.5.2** — taken email or connected `sub` refuses keeping; row unchanged.

## Log

Design, before building:

- One start route (`google.redirect`) and one callback (`google.callback`), so Google's console carries one redirect URI. The callback reads who is signed in: a guest is signing in, a demo is keeping, anyone else is confirming. Socialite's `state` binds the callback to the session that started it.
- Set a password is its own route (`user-password.store`, behind `password.confirm`), refusing an account that has chosen one; Update password is unchanged.
- `google.*` and `user-password.store` join `ViewAsStudent::SIGN_IN_ROUTES`.
- Google sign-in remembers the device (`remember: true`) — there is no checkbox on the Google path. Ask Colin.
- `prompt=select_account` dropped: `Socialite::driver()` returns the `Provider` contract, which has no `with()`/`setScopes()` (PHPStan), and the default scopes are already the spec's.
