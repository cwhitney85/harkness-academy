# Unit `harkness` — the rename, the brand, the landing page

*Started 2026-09-11. Disposable (spec 9.6): decisions go to the ledger, traps to `docs/lessons.md`, behaviour to `docs/system.md`, rules to the spec item — then this file is deleted in the commit that lands the work.*

OpenTutor is now **Harkness Academy**. `harkness.academy` is registered and pointed at the Laravel Cloud application. This unit crosses specs 1, 2, 5, 7, 8 and 9, so no package number names it.

**Hierarchy is dependency.** 3 needs 1 and 2; 4 needs 3; 5 and 6 need nothing.

Ledger entries this unit writes: [D44](../02-design.md) the name, the motto and the monogram · [D45](../02-design.md) the invitation gate · **D46** the landing page's ground, written after node 3 is judged.

Spec items this unit adds: **1.6** (the invitation gate) · **8.13** (the landing page) · an amendment to **8.6.1**.

---

## 1. The rename

*Owner: this file. No spec owns the product's name; after landing, the durable fact is D44.*

Scale, measured 2026-09-11: **78 occurrences across 55 files**; 51 in 32 files outside the canvas and the historical tier. `Open Tutor` with a space appears zero times. The bare token `OT` appears 11 times and every one is the crest monogram — there is no substring hazard to sift.

- **1.1 MUST** — The 15 user-visible strings read `Harkness Academy`: `rail.tsx`, `install.tsx` ×5, `terms.tsx` ×2, `courses/create.tsx`, `crest.tsx`'s `aria-label`, `app.blade.php`'s apple title, `manifest.webmanifest`'s `name` and `short_name`. *Done: `git grep -i opentutor -- resources public` returns nothing.*
- **1.2 MUST** — `rail.tsx` stops hardcoding the wordmark and reads `name` from Inertia's shared props, as `app-logo.tsx` already does. *Done: changing `APP_NAME` changes both the rail head and the sidebar; the literal appears in neither.* This is a scope fix, not a rename — the string has two owners today, and the rename is the cheapest moment to give it one.
- **1.3 MUST** — Config and infra: `.env.example`'s `APP_NAME`, `deploy.md`'s `APP_NAME` row, `InterfaceFixtureSeeder::EMAIL` → `colin@harkness.test`. *Done: a fresh `.env` from the example boots with the new name; `DesignFixtureTest` passes.*
- **1.4 MUST** — The development database's one user row is updated in place to match 1.3, by `UPDATE`, never by reseeding. *Done: `select email from users` returns the new address and `select count(*) from courses` still returns 6.*
- **1.5 WONT** — The Postgres databases stay `opentutor` and `opentutor_test`; `phpunit.xml`, `.env.example`'s `DB_DATABASE`, `README.md`'s `createdb` line and the CI workflow keep their current values. Renaming a live database holding a real course buys nothing. *Falsified if: any later item needs the database name to match the product name.*
- **1.6 MUST** — The 12 code comments and docblocks read `Harkness Academy`. *Done: `git grep -i opentutor -- app config resources/js/types` returns nothing.*
- **1.7 MUST** — `prompts/subtopic/check.md` and `tests/fixtures/prompts/subtopic/check.txt` read the new name. *Done: `PromptLoaderTest` and the subtopic-scope tests pass.* No cached prefix is affected: the cache breakpoints in `PromptCaching.php` sit on lesson system prompts, and neither `prompts/lesson/system.md` nor `prompts/persona/default.md` names the product.
- **1.8 MUST** — Tier-1 and tier-2 documents are renamed: `CLAUDE.md`, `README.md`, `docs/system.md`, `docs/lessons.md`, `docs/deploy.md`, `docs/anthropic-specific.md`, `docs/specs/08-interface.md`. *Done: `git grep -i opentutor -- CLAUDE.md README.md docs/system.md docs/lessons.md docs/deploy.md docs/anthropic-specific.md docs/specs` returns nothing.*
- **1.9 MUST** — Every document's **H1 title** is renamed, including the five that keep their bodies: `00-frame.md`, `01-research.md`, `03-spec.md`, `02-design.md`, `PLAN.md`. *Done: no file's first line contains `OpenTutor`.*
- **1.10 MUST** — **Body prose in the historical tier is not edited.** `01-research.md` line 8 and `02-design.md` line 795 keep the old name: they are dated records of what was decided under it. `00-frame.md`, `01-research.md`, `03-spec.md` and `PLAN.md` each gain one line under their title: *"The product was named OpenTutor until 2026-09-11; see [D44](02-design.md)."* `02-design.md` gets no note, because it holds D44. *Done: those four files carry the note; `git grep -c -i opentutor docs/01-research.md docs/02-design.md` returns 1 for each.*
- **1.11 MUST** — The crest monogram becomes `HA` in all four SVG sources: `resources/js/components/crest.tsx`, `public/favicon.svg`, `resources/icons/icon.svg`, `resources/icons/icon-maskable.svg`. *Done: `git grep -nw OT` returns only canvas artboards.*
- **1.12 MUST** — The four rasterised PNGs — `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `apple-touch-icon.png` — are regenerated from 1.11's SVGs, **and the command that does it is recorded**. *Done: deleting the four and running the recorded command reproduces them.* How they were first made is **unknown**: commit `0fe1894` says "rasterised from two SVG sources" and names no tool. Recording the command is the point of the item — without it, this blocks again at every future mark change. If it turns out to be a trap rather than a one-liner, the finding goes to `docs/lessons.md`.
- **1.13 SHOULD** — The seven canvas artboards' `OT` glyphs become `HA`, and `docs/design/canvas/README.md` is renamed and re-seeded from source. *Done: the published canvas shows `HA`; a fresh clone re-seeds it.*
- **1.14 WONT** — `public/favicon.ico` stays the Laravel starter-kit mark, as 8.3.5's 2026-09-07 clarification already decided. *Falsified if: a browser in package 8's acceptance test shows the Laravel mark where the crest is expected.*
- **1.15 MAY** — The git repository and working directory keep the name `open-tutor`. *Done: n/a — no action.*

## 2. The brand, recorded

- **2.1 MUST** — Ledger **D44**: the name, the motto, the monogram, and the rejected candidates. *Done: a session reading D44 can say why the motto is not "Age Modo" without asking.*
- **2.2 MUST** — D44 states that the Harkness pedagogy is **not** claimed. *Done: no string in `resources/` mentions discussion-based or seminar teaching.*
- **2.3 MUST** — Ledger **D45**: the invitation gate is one shared code in config, and why not a table. *Done: D45 names the revocation cost it accepted.*
- **2.4 MUST** — Ledger **D46**: the landing page's ground, written **after** node 3 is judged, naming the two rejected grounds and whether `app.css`'s "nothing is ever set on it" comment about `--desk` survives. *Done: `app.css`'s comment and D46 agree.*
- **2.5 SHOULD** — `docs/system.md` gains the landing page and the invitation gate. *Done: a reader who has never seen the repo can say what an unauthenticated visitor sees.*

## 3. The artboards

*Depends on 1.11 (the `HA` crest) and 2.1 (the motto). Extends 8.1, which already makes the canvas the source of record.*

Four decisions are deliberately deferred to looking rather than arguing: the wordmark (W1 vs W3), the page (L1 vs L2), the motto's placement (P1 vs P2) and the ground (G1 vs G2 vs G3). Each artboard isolates one variable and holds the rest constant.

- **3.1 MUST** — A new canvas page **B · brand** carrying eight artboards: `Marks`; `LandingCrest` ×3 (paper, green, desk); `LandingArgument`; `Footer`; `LandingPhone` ×2 (paper, green). *Done: the published canvas shows all eight, and each candidate ground appears at 1440px and at 390px.*
- **3.2 MUST** — No placeholder text. `LandingArgument` carries **"Enroll yourself."** over the real lifecycle subhead: *a tutor that interviews you, writes a syllabus you approve, teaches it lesson by lesson, and examines you at the end.* *Done: the artboards contain no lorem ipsum — the rule `docs/design/canvas/README.md` already states.*
- **3.3 MUST** — `Marks` sets W1 (`Harkness Academy`, one line) and W3 (stacked letterspaced caps) against both crest variants at hero, rail, auth and 16px sizes. *Done: the wordmark decision is answerable from that artboard alone.*
- **3.4 MUST** — Whatever wins changes `docs/design/canvas/` **and** `resources/css/app.css` in the same commit (8.1.3). *Done: no commit touches one without the other.*
- **3.5 WONT** — No artboard for a signed-in navbar. `/` redirects members, so no signed-in user ever sees this page. *Falsified if: the landing route renders for an authenticated request.*

## 4. The landing page

*Depends on 3 — the page is not built until the artboards are judged (confirmed 2026-09-11, Colin). Lands as spec section **8.13** plus an amendment to **8.6.1**; see [docs/specs/08-interface.md](../specs/08-interface.md) for the items themselves.*

## 5. The invitation gate

*Lands as spec item **1.6**; see [docs/specs/01-foundation.md](../specs/01-foundation.md). Independent of 3 and 4.*

## 6. Deploy and domain

*Owner: this file. `docs/deploy.md` is a runbook, not a spec.*

- **6.1 MUST** — A custom-domain section: attaching `harkness.academy` in Laravel Cloud, the DNS records, TLS issuance, and setting `APP_URL` to it. *Done: a reader can attach a second domain without opening the Cloud docs.*
- **6.2 MUST** — The `APP_NAME` override row reads `Harkness Academy`. *Done: it names no old value.*
- **6.3 SHOULD** — Step 1's instruction to name the Cloud application `opentutor` is corrected, or marked historical if the application cannot be renamed. *Done: the doc states which, and does not instruct a reader to create an application named after the old product.* Whether Cloud allows renaming an application is **unknown** — dashboard-only, not inspectable from the repo.

---

## Open and unknown

- **How the four PWA PNGs were rasterised** (1.12). Unknown; findability ended in-repo.
- **Whether Laravel Cloud can rename an application**, and whether that changes the injected `APP_NAME` (6.3). Dashboard-only.
- **Whether `harkness.academy` is attached in Cloud with TLS issued**, or only pointed by DNS (6.1). Dashboard-only.
- **Whether anything reads `usage_events` back as a per-user total.** Not investigated. Spec item 1.6.5 WONTs a spend cap, so nothing in this unit depends on the answer.
- **`GUIDANCE.md` does not exist.** CLAUDE.md carries a standing convention — "GUIDANCE.md is model-facing only; no route or component may read it" — naming a file `find` locates nowhere in the repo. Out of scope for this unit; surfaced because the rename swept the same ground.
