# Unit `harkness` — the rename, the brand, the landing page

*Started 2026-09-11. Disposable (spec 9.6): decisions go to the ledger, traps to `docs/lessons.md`, behaviour to `docs/system.md`, rules to the spec item — then this file is deleted in the commit that lands the work.*

OpenTutor is now **Harkness Academy**. `harkness.academy` is registered and pointed at the Laravel Cloud application. This unit crosses specs 1, 2, 5, 7, 8 and 9, so no package number names it.

Items here are prefixed `H` so they never collide with the spec item numbers this unit also adds: spec **1.6** is the invitation gate, **H1.6** is this unit's code comments.

**Hierarchy is dependency.** H3 needs H1 and H2; H4 needs H3; H5 and H6 need nothing.

Ledger entries this unit writes: [D44](../02-design.md) the name, the motto and the monogram · [D45](../02-design.md) the invitation gate · [D46](../02-design.md) the wordmark, the motto's placement, the composition and the ground.

Spec items this unit adds: **1.6** (the invitation gate) · **8.13** (the landing page) · an amendment to **8.6.1**, and two to **8.13.4** the day it was written.

## Where it stands (2026-09-11)

**Landed.** `47e365a` D44, D45, spec 1.6 and spec 8.13 written · `057fdd1` H1.1–H1.7, and H2.1–H2.3 with them · `0870b38` H1.8–H1.10 · `81a45de` H1.11–H1.12, plus trap [T9](../lessons.md) and `scripts/icons.sh` · `6b50c3d` H1.13 and H3.1–H3.3 · **H3.4 and H2.4** — the four questions answered, D46 written, `app.css` and the settled artboards moved to W3 · **H4** — the front door is built and matches its artboards at both widths.

Nothing in the running app says OpenTutor, and nothing on canvas page A does either. What still does is deliberate: the `opentutor` and `opentutor_test` databases (H1.5 WONT), nothing — the Cloud application was renamed to `Harkness Academy` (handle `harkness-academy`) on 2026-09-11, and the superseded canvas pages A · dosage and First directions, which are dated records (the H1.10 principle).

`956cd09` **H5** — the gate is in `CreateNewUser` · `140dc94` **H6.1 and H6.3**, and H5's deploy half with them.

**Open.** **H2.5** only. H6.2 landed early inside H1.8.

**To land the unit:** triage this file into the durable tier and delete it in the same commit as the last of the work (spec 9.6).

---

## H1. The rename

*Owner: this file. No spec owns the product's name; after landing, the durable fact is D44.*

Scale, measured 2026-09-11: **78 occurrences across 55 files**; 51 in 32 files outside the canvas and the historical tier. `Open Tutor` with a space appears zero times. The bare token `OT` appears 11 times and every one is the crest monogram — there is no substring hazard to sift.

- **H1.1 MUST** — The 15 user-visible strings read `Harkness Academy`: `rail.tsx`, `install.tsx` ×5, `terms.tsx` ×2, `courses/create.tsx`, `crest.tsx`'s `aria-label`, `app.blade.php`'s apple title, `manifest.webmanifest`'s `name` and `short_name`. *Done: `git grep -i opentutor -- resources public` returns nothing.*
- **H1.2 MUST** — `rail.tsx` stops hardcoding the wordmark and reads `name` from Inertia's shared props, as `app-logo.tsx` already does. *Done: changing `APP_NAME` changes both the rail head and the sidebar; the literal appears in neither.* This is a scope fix, not a rename — the string has two owners today, and the rename is the cheapest moment to give it one.
- **H1.3 MUST** — Config and infra: `.env.example`'s `APP_NAME`, `deploy.md`'s `APP_NAME` row, `InterfaceFixtureSeeder::EMAIL` → `colin@harkness.test`. *Done: a fresh `.env` from the example boots with the new name; `DesignFixtureTest` passes.*
- **H1.4 MUST** — The development database's one user row is updated in place to match H1.3, by `UPDATE`, never by reseeding. *Done: `select email from users` returns the new address and `select count(*) from courses` still returns 6.*
- **H1.5 WONT** — The Postgres databases stay `opentutor` and `opentutor_test`; `phpunit.xml`, `.env.example`'s `DB_DATABASE`, `README.md`'s `createdb` line and the CI workflow keep their current values. Renaming a live database holding a real course buys nothing. *Falsified if: any later item needs the database name to match the product name.*
- **H1.6 MUST** — The 12 code comments and docblocks read `Harkness Academy`. *Done: `git grep -i opentutor -- app config resources/js/types` returns nothing.*
- **H1.7 MUST** — `prompts/subtopic/check.md` and `tests/fixtures/prompts/subtopic/check.txt` read the new name. *Done: `PromptLoaderTest` and the subtopic-scope tests pass.* No cached prefix is affected: the cache breakpoints in `PromptCaching.php` sit on lesson system prompts, and neither `prompts/lesson/system.md` nor `prompts/persona/default.md` names the product.
- **H1.8 MUST** — Tier-1 and tier-2 documents are renamed: `CLAUDE.md`, `README.md`, `docs/system.md`, `docs/lessons.md`, `docs/deploy.md`, `docs/anthropic-specific.md`, `docs/specs/08-interface.md`. *Done: `git grep -i opentutor -- CLAUDE.md README.md docs/system.md docs/lessons.md docs/deploy.md docs/anthropic-specific.md docs/specs` returns nothing.*
- **H1.9 MUST** — Every document's **H1 title** is renamed, including the five that keep their bodies: `00-frame.md`, `01-research.md`, `03-spec.md`, `02-design.md`, `PLAN.md`. *Done: no file's first line contains `OpenTutor`.*
- **H1.10 MUST** — **Body prose in the historical tier is not edited.** `01-research.md` line 8 and `02-design.md` line 795 keep the old name: they are dated records of what was decided under it. `00-frame.md`, `01-research.md`, `03-spec.md` and `PLAN.md` each gain one line under their title: *"The product was named OpenTutor until 2026-09-11; see [D44](02-design.md)."* `02-design.md` gets no note, because it holds D44. *Done: those four files carry the note; `docs/01-research.md` holds its one body sentence and `docs/02-design.md` holds both its own — the D7-era sentence at D42, and D44's opening line stating the rename.*
- **H1.11 MUST** — The crest monogram becomes `HA` in all four SVG sources: `resources/js/components/crest.tsx`, `public/favicon.svg`, `resources/icons/icon.svg`, `resources/icons/icon-maskable.svg`. *Done: `git grep -nw OT` returns only canvas artboards.*
- **H1.12 MUST** — The four rasterised PNGs — `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `apple-touch-icon.png` — are regenerated from H1.11's SVGs by `scripts/icons.sh`, which is the recorded command. *Done: deleting the four and running `scripts/icons.sh` reproduces them.* The method was never unknown — it was in `resources/icons/icon.svg`'s own comment, which the research read past. What the script adds is the margin-free wrapper the comment did not mention; the trap is [T9](../lessons.md).
- **H1.13 DONE** — The seven canvas artboards' `OT` glyphs become `HA`, and `docs/design/canvas/README.md` is renamed and re-seeded from source. *Done: the published canvas shows `HA`; a fresh clone re-seeds it.* Eight occurrences in seven files — `ExamClosed` carries two, the rail mark and the one printed on the blue-book cover. The README's seed command now loops over `*.dc.html` rather than naming artboards, so adding one to the directory is the whole change.
- **H1.14 WONT** — `public/favicon.ico` stays the Laravel starter-kit mark, as 8.3.5's 2026-09-07 clarification already decided. *Falsified if: a browser in package 8's acceptance test shows the Laravel mark where the crest is expected.*
- **H1.15 MAY** — The git repository and working directory keep the name `open-tutor`. *Done: n/a — no action.*

## H2. The brand, recorded

- **H2.1 MUST** — Ledger **D44**: the name, the motto, the monogram, and the rejected candidates. *Done: a session reading D44 can say why the motto is not "Age Modo" without asking.*
- **H2.2 MUST** — D44 states that the Harkness pedagogy is **not** claimed. *Done: no string in `resources/` mentions discussion-based or seminar teaching.*
- **H2.3 MUST** — Ledger **D45**: the invitation gate is one shared code in config, and why not a table. *Done: D45 names the revocation cost it accepted.*
- **H2.4 DONE** — Ledger **D46**: the landing page's ground, written **after** node H3 is judged, naming the two rejected grounds and whether `app.css`'s "nothing is ever set on it" comment about `--desk` survives. *Done: `app.css`'s comment and D46 agree.* It grew past its brief because the same sitting settled all four questions, so D46 carries the wordmark, the motto's placement and the composition too. The comment did not survive: the landing page sets type on `--desk`. It is still not a fourth ground in D22's sense, and the rewritten comment says why.
- **H2.5 SHOULD** — `docs/system.md` gains the landing page and the invitation gate. *Done: a reader who has never seen the repo can say what an unauthenticated visitor sees.*

## H3. The artboards

*Depends on H1.11 (the `HA` crest) and H2.1 (the motto). Extends 8.1, which already makes the canvas the source of record.*

Four decisions are deliberately deferred to looking rather than arguing: the wordmark (W1 vs W3), the page (L1 vs L2), the motto's placement (P1 vs P2) and the ground (G1 vs G2 vs G3). Each artboard isolates one variable and holds the rest constant.

- **H3.1 DONE** — A new canvas page **B · brand** carrying eight artboards: `Marks`; `LandingCrest` ×3 (paper, green, desk); `LandingArgument`; `Footer`; `LandingPhone` ×2 (paper, green). *Done: the published canvas shows all eight, and each candidate ground appears at 1440px and at 390px.* Desk at 390px shares `LandingPhonePaper`, which carries two frames side by side: a 2.4% lightness step reads as nothing when the two are not adjacent, so a lone desk phone would have answered no question. `launch` now opens the canvas on page-4.
- **H3.2 DONE** — No placeholder text. `LandingArgument` carries **"Enroll yourself."** over the real lifecycle subhead: *a tutor that interviews you, writes a syllabus you approve, teaches it lesson by lesson, and examines you at the end.* *Done: the artboards contain no lorem ipsum — the rule `docs/design/canvas/README.md` already states.*
- **H3.3 DONE** — `Marks` sets W1 (`Harkness Academy`, one line) and W3 (stacked letterspaced caps) against both crest variants at hero, rail, auth and 16px sizes. *Done: the wordmark decision is answerable from that artboard alone.* `Marks` also carries the motto question, because P1 and P2 are both compositions of the mark and belong beside the wordmark rather than on a landing artboard that is holding everything but its ground constant.
- **H3.4 DONE** — Whatever wins changes `docs/design/canvas/` **and** `resources/css/app.css` in the same commit (8.1.3). *Done: no commit touches one without the other.* Colin's call, 2026-09-11: **W3 · P2 · L2 · G3**, and drop both the bar and the footer. All of it is D46.

  The commit went further than `app.css`, because W3 is a composition rather than a token and leaving the app rendering W1 is the drift 8.1.3 exists to prevent. `<Wordmark/>` splits `APP_NAME` on whitespace and renders a line per word; `rail.tsx` and `app-logo.tsx` are its two call sites, and `auth-simple-layout.tsx` draws the crest with no wordmark, so there is no third. `.wordmark` is the class, beside `.display`/`.label`/`.hand`.

  The rail head changed shape with it: crest and the two 15px lines are both 34px, so they align as one object, and the subtitle drops below them behind a hairline. Colin's diagnosis was that the crest was splitting the name from the course; the hairline is what stops the course from reading as a product tagline once it is on its own line.

  P1 is rejected rather than dead. The band does not read at any size the page uses, because an 11-unit band under a 38-unit shield is a smudge — but that is an argument about the shield's size, not about the idea. D46 names it as the thing to revisit if the crest is punched up.

  **Two corrections from looking at it (Colin, same day).** The motto was drawn under "Enroll yourself." and reads there as a subtitle for the sentence above it; a school motto belongs to the crest, so it hangs off the mark at both widths. And G3 on `--desk` was invisible: 2.4% under `--ground` is plenty where the two touch, and nothing full-bleed. The front door gets **`--door`, `oklch(0.915 0.016 95)`** — its own token, because deepening `--desk` would change the exam screen to fix the landing page.

  That reverses what this file and D46 first said about `app.css`'s `--desk` comment. It *does* survive: with the front door on `--door`, "nothing is ever set on it" is true again. The Terms line went with the same pass — the footer was already cut and one orphan link at the foot of an otherwise linkless page was not worth the row.

  On the canvas: `Landing` and `LandingPhone` are the chosen page. `Marks` and the three `LandingCrest` grounds stay as the record of what they beat, retitled. `LandingArgument`, `Footer` and the two candidate phones are deleted — L1 survives in the three ground artboards, so nothing about the comparison is lost by removing the four.
- **H3.5 WONT** — No artboard for a signed-in navbar. `/` redirects members, so no signed-in user ever sees this page. *Falsified if: the landing route renders for an authenticated request.*

## H4. The landing page

*Landed 2026-09-11 as spec section **8.13**; 8.6's map and table carried S0 from the day the section was written. `Landing.dc.html` and `LandingPhone.dc.html` are what 8.13.6 measured against — both widths shot in a signed-out browser and compared to the artboard.*

`GET /` left the auth group and became one condition in the route: `Auth::check()` redirects to the dashboard, everyone else gets `Inertia::render('landing')`. Leaving it in the group would have sent the visitor to sign in and left no front door to reach. `EntryRedirectTest` holds both arms; it used to assert the guest arm was a redirect to sign-in.

`landing.tsx` is written phone-first and the desktop reverses the row, because the phone's DOM order — mark, then argument — is the one order both widths can share: a column has no right-hand side to put an object on. The one thing rendered twice is the second paragraph's closing clause, which the phone artboard drops; it is a `hidden lg:inline` span rather than two paragraphs.

The quiet button is the app's `variant="outline" size="action"`, which the dashboard's dialog already uses, plus `bg-transparent shadow-none` — the starter kit's outline fills with `--ground`, and on `--door` that read as a panel rather than as the artboard's unfilled second action.

**Two spec amendments, both from the same looking-at-it pass that wrote H3.4's corrections.** 8.13.4 and D46 both still required a single Terms link at the foot; the chosen artboards carry none, and the commit that cut the footer said so in its message and nowhere durable. Both now say the page navigates in two places and both are buttons.

**Open, and Colin's call:** the caption reads *Registration needs an invitation code*, which is on the artboard and is not true until H5 lands — `register` is ungated today. Nothing announces the domain yet, so nothing is being told to a visitor who is not Colin, but H5 should land before that changes.

## H5. The invitation gate

*Landed 2026-09-11 as spec item **1.6**; see [docs/specs/01-foundation.md](../specs/01-foundation.md).*

`config('auth.invitation_code')` reads `INVITATION_CODE`, with **no default** — `Rule::in([null])` matches no string, so an environment missing the variable shuts the door rather than opening it. That is not the fail-open rule biting (4.5.1): nobody's progress is behind registration.

The field is **first on the register screen**, above Name, and takes the autofocus. A visitor without a code learns it before filling in five fields rather than after. Every `tabIndex` on the screen shifted by one.

Two messages, because two rules can break: `required` says *Registration is by invitation. Enter the code you were sent.* and `in` says *That is not the invitation code. Check the one you were sent.* Both land on the `invitation_code` field, which is 1.6.3 and D41.

1.6.2 needed no code at all — `plan` defaults to `standard` in both the migration and `User::$attributes`. The test pins it.

Local `.env` carries `INVITATION_CODE=local-invite` so the dev app can register; the value is Colin's to change.

The field also sets `autoCapitalize="none" autoCorrect="off" spellCheck={false}`. The match is case-sensitive and exact, and an iOS keyboard capitalises the first letter of a text input by default — a correct code typed on a phone would have failed on the one screen with no way to see why.

## H6. Deploy and domain

*Owner: this file. `docs/deploy.md` is a runbook, not a spec. Landed 2026-09-11.*

The runbook gained a **The custom domain** section between the first-deploy checks and cost control, written from Cloud's own domains documentation rather than from memory: the four questions the Add domain dialog asks and the answer for a single apex, what each record is for (`_cf-custom-hostname` ownership, `_acme-challenge` SSL, A vs CNAME origin), what each verification status means, and the `dig` lines that say whether a record is visible. The one fact worth the section on its own: **`*.laravel.cloud` carries `X-Robots-Tag: noindex, nofollow` and a custom domain does not**, so attaching the domain is what makes the app indexable — and the gate H5 just built is what stands behind it.

H5's deploy half landed here too: `INVITATION_CODE` is a row in the environment-variable table, and the after-deploy smoke test now says what a *correct* code failing means (the variable never reached the app — a variable lands on a deploy, not a restart).

- **H6.1 MUST** — A custom-domain section: attaching `harkness.academy` in Laravel Cloud, the DNS records, TLS issuance, and setting `APP_URL` to it. *Done: a reader can attach a second domain without opening the Cloud docs.*
- **H6.2 MUST** — The `APP_NAME` override row reads `Harkness Academy`. *Done: it names no old value.*
- **H6.3 SHOULD** — Step 1's instruction to name the Cloud application `opentutor` is corrected, or marked historical if the application cannot be renamed. *Done: the doc states which, and does not instruct a reader to create an application named after the old product.* **The unknown is closed, and not from the dashboard**: Cloud's public API takes `name` and `slug` on `PATCH /applications/{application}`, and its domains documentation says renaming an application changes the assigned `*.laravel.cloud` domain and needs a redeploy. Step 1 now says to name it `Harkness Academy`, and says how to rename one created under the old name.

---

## Open and unknown

- **Whether renaming the Cloud application changes the injected `APP_NAME`.** Renaming itself is settled (H6.3); what Cloud re-injects afterwards is not. It costs nothing either way — the `APP_NAME` row already says to override only when the injected value is wrong.
- **Whether `harkness.academy` is attached in Cloud with TLS issued**, or only pointed by DNS (H6.1). Dashboard-only.
- **Brass is 2.10:1 on `--door` and 2.42:1 on `--ground`** — below AA either way. Not caused by D46; every brass label in the app has always had it, and the motto line inherits it. Three ways out if it is ever worth fixing: words in `--muted-ink` with the rules left brass, words in wine (8.94:1, but wine is the one action colour, 8.3.4), or accept it as decorative. Palette-wide, so it is not the landing page's decision to make.

- **Whether anything reads `usage_events` back as a per-user total.** Not investigated. Spec item 1.6.5 WONTs a spend cap, so nothing in this unit depends on the answer.
- **`GUIDANCE.md` does not exist.** CLAUDE.md carries a standing convention — "GUIDANCE.md is model-facing only; no route or component may read it" — naming a file `find` locates nowhere in the repo. Out of scope for this unit; surfaced because the rename swept the same ground.
