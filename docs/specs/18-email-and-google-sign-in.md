# Spec 18 — Email and Google Sign-in

*Provides: password-reset email that arrives, and Google as a second way to sign up, sign in and keep a demo. Consumes: 1 (accounts, the invitation gate), 12 (demo conversion), 13 (view-as). Amends [1.6](01-foundation.md), [12.4.2](12-demo.md), [12.6.4](12-demo.md) and [13.4.8](13-admin-foundation.md). Specced 2026-10-07.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D45](../02-design.md) entry is one shared invitation code · [D68](../02-design.md) nothing is hard-deleted but an unclaimed demo · [D72](../02-design.md) Resend sends the app's email · [D73](../02-design.md) Google sign-up finishes on a page of its own, and joining an existing account takes its password once · [D74](../02-design.md) a student without a password confirms with Google · [D75](../02-design.md) two-factor applies whatever the sign-in · [D76](../02-design.md) email ships first, and a demo can be kept with Google. Traps: [T26](../lessons.md).

## The shape

**Email ships first, on its own** ([D76](../02-design.md)). 18.2–18.5 depend on 18.2.1 and not on 18.1; 18.4's fallback for a student who would rather have a password — forgot-password — needs 18.1.

**What the code had on 2026-10-07**, each confirmed by reading it:

- Password reset is built; `MAIL_MAILER=log` writes the email to the log. `harkness.academy`'s DNS is at Porkbun and carries no SPF or DMARC record.
- Fortify's two-factor challenge is a step of its `POST /login` pipeline (`RedirectIfTwoFactorAuthenticatable`). A sign-in that does not go through that route skips it, and `RequireSecondFactor` checks only that a second factor is enabled, not that this session passed it.
- `users.password` is NOT NULL, and 12.1.2 kept it so; a demo row carries a random password.
- `User` does not implement `MustVerifyEmail`, so no account's email has ever been proven. Joining a Google sign-in to an account by email alone is the pre-registration takeover of CVE-2026-53516 and CVE-2026-35511.
- Four places demand the current password: the security page (`RequirePassword`), two-factor management and passkey management (`confirmPassword: true`), and password change (`current_password`). Fortify's `confirmPasswordsUsing` replaces the check.
- Keeping a demo with an email that is already taken is refused by `ProfileValidationRules`' unique rule.

---

## 18.1 Email that is sent

- **18.1.1 MUST** — Production sends mail through Resend, from `noreply@harkness.academy` as `Harkness Academy` ([D72](../02-design.md)). The key comes from Laravel Cloud's Resend integration if its dashboard offers one, and otherwise from a linked secret, as `ANTHROPIC_API_KEY` does. *Done: a forgot-password request on production arrives in a Gmail inbox, not spam, within a minute.*
- **18.1.2 MUST** — `harkness.academy` carries Resend's domain-verification records. *Done: Resend shows the domain verified, and the 18.1.1 message's headers show `spf=pass` and `dkim=pass`.*
- **18.1.3 SHOULD** — A DMARC record is published at `p=none`. Gmail requires one only of senders of 5,000 or more a day; it is one DNS record. *Done: `dig TXT _dmarc.harkness.academy` returns it.*
- **18.1.4 MUST** — Development and tests never send real mail. *Done: `.env.example` keeps `MAIL_MAILER=log`, and the suite runs on the array mailer.*
- **18.1.5 MUST** — [docs/deploy.md](../deploy.md) carries the Resend and DNS steps. *Done: a cold reader repeats 18.1.1–18.1.3 from the runbook alone.*

## 18.2 The Google connection

- **18.2.1 MUST** — Google sign-in runs through `laravel/socialite` and asks only for `openid`, `email` and `profile`. The client ID and secret are read from the environment through `config/services.php`, and the redirect URI is registered for the custom domain. *Done: `git grep` finds no client secret in a committed file; Google's consent screen shows no unverified-app warning.*
- **18.2.2 MUST** — A connection is a row in `google_accounts` holding `user_id` and Google's `sub`, each unique: an account has at most one Google account, and a Google account at most one account. `users` gains no nullable column for it. *Done: the migration adds no nullable column to `users`, and a second connection for either side violates a unique index.*
- **18.2.3 MUST** — A connection is found by `sub`, never by email. Google's email for a person can change; its `sub` cannot. *Falsified if: a student whose Google address changes can no longer sign in with Google.*
- **18.2.4 MUST** — What Google returns is held in the session between the callback and the screens of 18.3 and 18.5, never in form fields. *Done: a crafted POST to the finish or connect screen naming a `sub` the session did not receive from Google creates nothing.*

## 18.3 Signing in and signing up

- **18.3.1 MUST** — Sign in and Register each carry one "Continue with Google" button, and both reach the same route. *Done: feature test.*
- **18.3.2 MUST** — A Google sign-in for a known `sub` signs into that account. *Done: feature test.*
  - **18.3.2.1 MUST** — An account with two-factor on goes to the existing two-factor challenge before it is signed in, whatever the sign-in method, admin included ([D75](../02-design.md)). *Done: a Google sign-in for such an account stops at the challenge, and leaving it signs nobody in.*
  - **18.3.2.2 MUST** — A soft-deleted account is refused exactly as password sign-in refuses it ([16.2.10](16-catalog-removal-ownership.md)). *Done: feature test.*
- **18.3.3 MUST** — A Google sign-in for an unknown `sub` whose email belongs to an account shows **Connect Google**, which asks once for that account's password and offers forgot-password ([D73](../02-design.md)). The right password creates the connection and 18.3.2 applies; a wrong one creates nothing. *Done: feature tests for both; no path joins the two without the account's password.*
  - **18.3.3.1 MUST** — If that account is soft-deleted, the sign-in is refused with 18.3.2.2's message. *Done: feature test.*
  - **18.3.3.2 MUST** — If that account is already connected to a different Google account, the sign-in is refused — "This account signs in with a different Google account." — and nothing is connected. Google's `sub` names a person; two `sub`s are two people, whatever their email (18.2.2). *Added 2026-10-08, Colin. Done: feature test; the account keeps its one connection.*
- **18.3.4 MUST** — A Google sign-in for an unknown `sub` and an unknown email shows **Finish signing up** ([D73](../02-design.md)): the name from Google, editable; the email from Google, not editable; the invitation code; the 18-or-older attestation. They are validated by `InvitationValidationRules`, which registration and demo conversion share — so the code is checked here as well as in `CreateNewUser` ([1.6](01-foundation.md)). Success creates a `standard` account with a random password, `has_chosen_password` false (18.4.1), `tos_accepted_at`, and its connection. *Done: no code, a wrong code or an unticked attestation creates no user row and no connection; a valid submission creates exactly one of each.*
- **18.3.5 MUST** — Every route 18 adds is an edge in [8.6](08-interface.md#86-flow-map)'s flow map, in the commit that adds it. *Done: `FlowMapTest` passes.*
- **18.3.6 MUST** — Google sign-in from the installed iPhone app ends signed in inside the installed app, as it does on the website. Whether it does is unknown: iOS may finish the Google round-trip in Safari, whose session the installed app does not share. If it does not, the build session brings Colin the finding and the fix before anything ships. *Done: from the home-screen app on an iPhone, Google sign-in lands on the dashboard inside the app.*

## 18.4 A student without a password of their own

- **18.4.1 MUST** — `users` gains `has_chosen_password`, a NOT NULL boolean, appended last ([D74](../02-design.md)). It is true on every existing `standard` row and false on a Google sign-up and on a demo row; setting a password in settings (18.4.3), by reset, or by keeping a demo with one makes it true. *Done: after the migration every existing `standard` row is true, and each of the three ways of setting a password turns a false true.*
- **18.4.2 MUST** — The confirm-password screen offers **Confirm with Google** to an account with a connection. A round-trip that returns that account's `sub` confirms the session's password, exactly as typing it would; any other `sub` confirms nothing. *Done: feature tests for the matching and the non-matching `sub`.*
- **18.4.3 MUST** — An account whose `has_chosen_password` is false sees **Set a password** in security settings, with no current-password field; the route is behind password confirmation (18.4.2) instead. *Done: a Google-only student confirms with Google, sets a password, signs out, and signs in with email and that password.*
- **18.4.4 MUST** — Viewing as a student refuses 18.4.2's, 18.4.3's and 18.4.5's routes as it refuses the student's other sign-in routes ([13.4.8](13-admin-foundation.md), [T26](../lessons.md)). *Done: in preview and in act-as, each returns the admin to the student's dashboard.*
- **18.4.5 MUST** — Deleting the account asks an account whose `has_chosen_password` is false for no password; it is behind password confirmation (18.4.2) instead, as 18.4.3 is. An account with a password of its own still types it. *Added 2026-10-08, Colin: apps that sign in with Google confirm a deletion with Google. Done: a Google-only student confirms with Google and deletes the account; unconfirmed, the deletion sends them to confirm and deletes nothing.*

## 18.5 Keeping a demo with Google

- **18.5.1 MUST** — The keep screen offers **Keep with Google** beside the password form ([D76](../02-design.md)). After Google, the visitor finishes on 18.3.4's page; success attaches the connection, the name and Google's email to the demo row and moves it to `standard`. Nothing is copied ([12.4.2](12-demo.md)). *Done: the working-knowledge version before keeping is the version after it, and lesson 2 of the course opens.*
- **18.5.2 MUST** — If Google's email already belongs to an account, or its `sub` is already connected, keeping is refused — "This email already has an account. Sign in instead." — and the demo row is unchanged, as keeping with a password already refuses a taken email. *Done: feature test; the row is still `demo` and no connection exists.*

## 18.6 Out of scope

- **18.6.1 WONT** — A sign-in provider other than Google. *Falsified if: a second Socialite driver is configured.*
- **18.6.2 WONT** — A student removing Google from their own account. One who needs it asks Colin, who does it in the database. *Falsified if: a route or button that removes a connection appears.*
- **18.6.3 WONT** — Email verification across the app. The `verified` middleware stays a no-op (spec 12); 18.3.3 closes the takeover without it. *Falsified if: `User` implements `MustVerifyEmail`.*
- **18.6.4 WONT** — Moving a demo course into an existing account. *Falsified if: keeping a demo writes a course row to a second user.*
- **18.6.5 WONT** — Any other email — nudges, notifications — and any mailbox that receives mail. *Falsified if: a Mailable or Notification other than Fortify's appears.*
- **18.6.6 MAY** — Google brand verification, so the consent screen shows the Harkness name and logo. About three to five days at Google.

## Acceptance test

*The journey this package's items compose into ([D29](../02-design.md)).*

Against production, in a browser that has never seen the app:

1. Ask for a password reset for your own account. The email arrives in Gmail, not spam; reset, and sign in with the new password.
2. Sign out. Continue with Google, using a Google account the app has never seen. Finish signing up refuses a wrong invitation code on the code field; with the right one and the box ticked, you land on the dashboard.
3. Open security settings. The confirm screen offers Confirm with Google; use it. Set a password, sign out, and sign in with email and that password.
4. Sign out. Continue with Google, using the Google account whose email is your password account's. Connect Google asks for that password once; your two-factor challenge follows; you are in the same account, its courses there.
5. Open the demo link, finish lesson 1, and choose Keep with Google with a fresh Google account. Lesson 2 of the same course opens.
6. Repeat step 2 from the home-screen app on an iPhone. It ends on the dashboard inside the app.
