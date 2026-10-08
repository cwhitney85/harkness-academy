# 18-email — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [18.1.1–18.1.5](../specs/18-email-and-google-sign-in.md#181-email-that-is-sent)

## Items

- [ ] **18.1.1** — a forgot-password request on production arrives in Gmail, not spam, within a minute. Code: `resend/resend-php` installed. Rest is Colin's dashboard work.
- [ ] **18.1.2** — Resend shows the domain verified; headers show `spf=pass`, `dkim=pass`. Colin, at Porkbun.
- [ ] **18.1.3** — `dig TXT _dmarc.harkness.academy` returns the record. Colin, at Porkbun.
- [x] **18.1.4** — `.env.example` keeps `MAIL_MAILER=log` (line 50); `phpunit.xml` sets `array` (line 31). Already true; nothing to change.
- [ ] **18.1.5** — deploy.md carries the Resend and DNS steps.

## Log

- Laravel's `resend` transport needs `resend/resend-php`, which was not installed — without it production would fail at the first send. Installed (^1.16); the transport resolves locally with a dummy key. The four `composer audit` advisories (framework, commonmark ×2, flysystem) predate it.
- Cloud's Resend integration exists in its API (`POST /environments/{env}/resend` takes `from_address`, `from_name`; org-level sending domains carry `records` and a verification `status`). No guide page documents it, and nothing says which variables it injects.
- 2026-10-07: `harkness.academy` NS is Porkbun; no TXT, MX or `_dmarc` records exist.
