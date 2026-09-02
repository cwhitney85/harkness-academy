---
description: Start a package-8 interface session scoped to one section (usage: /interface 8.8, optionally /interface 8.8 items 8.8.1-8.8.3)
---

Build section $ARGUMENTS of `docs/specs/08-interface.md`. The design is settled — this session translates it, it does not redesign it.

**Prerequisites: 8.1 and 8.2 must have landed.** 8.1 is the canvas and the ledger; 8.2 is the seeder that puts renderable rows in the database. 8.2 is built with `/package 8 items 8.2`, not with this command — it produces a seeder, not a screen.

## 1. Look before you read

**Open the canvas and actually look at it before writing anything.** Use the Chrome browser tools on `https://claude.ai/code/artifact/5d5d7052-86b8-4230-8c2a-606cba1b6f46`, land on the page named **A · materials**, and view the artboards this section maps to (table below). Reading the `.dc.html` source tells you the values; only looking tells you the proportions, the weight of the colour fields, and how much air the layout carries.

If the browser tools are unavailable, say so and work from `docs/design/canvas/*.dc.html` source — but say it, because it is a weaker basis.

## 2. Then read, in this order

1. `PLAN.md` — package 8's status row.
2. `docs/specs/08-interface.md` — this section, and 8.3 (the language) whichever section you are on.
3. `docs/02-design.md` **D21–D24** — direction, materials, checkpoint, Radix. These are settled; don't re-litigate them.
4. The artboard source for your section, for exact values.

## 3. Which artboards belong to which section

| Section | Artboards on page "A · materials" |
|---|---|
| 8.2 Fixtures | **not an `/interface` session** — it builds a seeder, not a screen. Run `/package 8 items 8.2` first; every session below assumes its rows exist. |
| 8.3 Design language | `ALesson` (the full token set in its `:root`) |
| 8.4 Material system | `QuizPhotocopy`, `ExamClosed`, `ExamBlueBook` |
| 8.5 Application shell | `ALesson`, `LessonPhone` |
| 8.7 Package 4's HTTP layer | **none — no mockup exists.** Derive from 8.3's language. The syllabus screens on page "First directions" are in *rejected* palettes; use them for layout only, never for colour or type. |
| 8.8 Lesson screens | `ALesson`, `LessonPhone`, `CheckpointOptions` (C is chosen), `CheckpointPhone` (P1 is chosen) |
| 8.9 Assessment screens | `QuizPhotocopy`, `ExamClosed`, `ExamBlueBook` |
| 8.10 Course surfaces | **none — no mockup exists.** Derive from 8.3's language. |
| 8.6 Flow map | none — read `routes/`; **run this before 8.7** |

Pages "A · dosage" and "First directions" are **superseded**. They record what was cut. Never copy from them.

## 4. The translation contract

The artboards are fixed-width mockups built to be looked at. They are not a component library.

**Take literally:** every colour, every font family and size, line-height ratios, the 30px blue-book rhythm, the 44px minimum touch target, the order and hierarchy of elements, and which material a surface is made of.

**Take as illustration only:** the 1440px frame width, absolute positioning, hard-coded pixel widths, `transform: rotate()` used to fake a photographed object, and the inline styles themselves. Spec 8.3.3 (measure) and 8.5.2/8.5.3 (breakpoint behaviour) govern how the thing actually resizes; the artboards show one frozen width of it.

**Build into the existing stack, not beside it.** Tokens go in `resources/css/app.css`. Behaviour comes from the Radix primitives already in `resources/js/components/ui/` — ratified in D24, so do not add a competing primitive. App-specific composites (`LessonSection`, `CheckpointQuestion`, `QuizSheet`, `BlueBook`, `Crest`) go in `resources/js/components/`.

**If a screen has no mockup, do not invent a new visual idea for it.** Compose it from the language already established: the same three grounds, the same type ramp, wine for actions, brass and gold for labels and rules.

## 5. Verify against the done-conditions, not against the picture

**Check what is in the database before you assume it needs seeding.** Since 2026-09-02 Colin has been taking a real course on the development database, and `InterfaceFixtureSeeder` deletes every course belonging to `colin@opentutor.test` — his own — before writing its six states. It is out of `DatabaseSeeder` for that reason and never runs as a side effect of `--seed`.

A real course exercises these screens better than the fixture did, so work against it. If a done-condition genuinely needs one of the six states that is not there — a set-aside course, an unsat exam, a completed module — ask Colin before running `php artisan db:seed --class=InterfaceFixtureSeeder`, take a restore point first (`php artisan db:backup`), and never run `migrate:fresh`.

Each spec item states what demonstrates it is done. Those are the acceptance tests — several are mechanically checkable (`no oklch(… 0 0)` remains; the pre-answer DOM contains no `correct_option_id`; no horizontal scroll at 390px). Run them.

Check every screen at 1440px **and** 390px before calling an item done. Browser-primary does not mean phone-last.

## 6. Rules for this session

- Scope is section $ARGUMENTS only. Do not modify other packages.
- If a spec gap appears, or a recorded decision looks wrong in practice, stop and ask — never diverge silently.
- **If the visual language itself changes, `docs/design/canvas/` and `resources/css/app.css` change in the same commit** (8.1.3). Re-seed the canvas from the committed source and republish to the URL above; never extract the published page.
- Commit at done-condition granularity, spec item number in the message (e.g. `8.8.3: the checkpoint asks from the margin`). No signing, co-author trailers, or attribution lines.

End of session: update PLAN.md's package 8 row with which done-conditions now pass.
