# OpenTutor — Build Plan

*The overarching tracker. Each package has a detailed spec in `docs/specs/`; spec item numbers (e.g. 2.4, 5.2.2) are the shared vocabulary across sessions. Update the status table at the end of every session.*

## Global acceptance test (definition of done for v1)

One user completes one honest course cycle **in a browser, and again on a phone**: creates "Language/Conversational Spanish/Intro" → two interviews → approves a syllabus → completes every lesson with checkpoints → passes the final exam → takes a refresher quiz drawing on recorded WORKING_KNOWLEDGE — with total inference cost visible in `usage_events`.
If any step needs a developer touching the DB, v1 is not done.

## Dependency graph

```
1 Foundation ──► 2 AI Layer ──► 3 Memory ──► 4 Course Pipeline ──► 5 Lesson Engine ──► 6 Assessment
                                                                       ▲                    │
                                    6.1 quiz service contract ─────────┘ (6.2 rides 5.4.1)  │
                                                                                            ▼
                                                            8 Interface ──► 7 PWA Shell
```

Package 7 is **no longer decoupled** (spec 8.11.5): package 8 owns responsive behaviour, the palette the manifest's `theme_color` must match, and the crest its icons derive from, so 7 cannot open before 8 lands. It is off the lane-candidate list in ledger W2 for the same reason.

Weeks 1–2 are strictly serial (1→2→3). Parallel worktree lanes open only in weeks 3–4, and **a lane may open only when every interface it consumes has landed on master.** Founder reviews and merges every lane; never auto-merge.

Branching (ledger W4): serial solo work commits directly to `master` at done-condition granularity; branches + founder-reviewed merges only for worktree lanes and risky spikes.

## Status

> **v1 is done (2026-09-07).** Both clauses of the global acceptance test passed — one honest course cycle in a browser and again on a phone, with no developer touching the DB. Every remaining ⚠️ below was confirmed on screen by Colin and is superseded by that result; the marks in the table have not been rewritten because this section is being retired under [spec 9.7](docs/specs/09-documentation.md).
>
> **This file is scheduled for retirement.** [D27](docs/02-design.md) tiers the repo's documents by lifetime and reduces PLAN.md to the dependency graph and the acceptance test; it retires once `docs/plans/` has carried three work units start to finish. The 2026-09-07 dogfood repair is the first.


| Package | Spec | Depends on | Status | Notes |
|---|---|---|---|---|
| 1 Foundation | [docs/specs/01-foundation.md](docs/specs/01-foundation.md) | — | done-conditions passing | 1.1 ✅ 1.2 ✅ 1.3 ✅ ([runbook](docs/deploy.md)) 1.4 ✅ · live at `opentutor-production-ffbp8z.laravel.cloud` · merge-*blocking* for 1.4 deferred to week 3 (2026-08-22, Colin) — rulesets need a paid plan · traps found under this package: [T1, T4](docs/lessons.md) |
| 2 AI Layer | [docs/specs/02-ai-layer.md](docs/specs/02-ai-layer.md) | 1 | done-conditions passing | 2.1 ✅ 2.1.1 ✅ 2.2 ✅ 2.3.1 ✅ 2.3.2 ✅ 2.3.3 ✅ 2.3.4 ✅ 2.3.5 ✅ 2.4 ✅ 2.5 ✅ · the 2.4 spike's measured result and the upstream PR that retires its workaround are entry 1 of the [portability ledger](docs/anthropic-specific.md) |
| 3 Memory | [docs/specs/03-memory.md](docs/specs/03-memory.md) | 1 (3.3: **also 4.1**) | done-conditions passing | 3.1 ✅ 3.2 ✅ 3.3 ✅ 3.4 ✅ · 3.3 landed in the 4.1 session, as spec 3.3 said it would · [D8](docs/02-design.md), [D10.1](docs/02-design.md) |
| 4 Course Pipeline | [docs/specs/04-course-pipeline.md](docs/specs/04-course-pipeline.md) | 2, 3 | done-conditions passing | 4.1 ✅ 4.2 ✅ 4.3 ✅ 4.4 ✅ 4.5.1 ✅ 4.5.2 ✅ 4.5.2.1 ✅ 4.5.2.2 ✅ 4.5.3 ✅ 4.5.4 ✅ 4.5.4.1 ✅ 4.5.5 ✅ 4.5.5.1 ✅ 4.5.6 ✅ 4.5.6.1 ✅ 4.5.6.2 ✅ 4.5.7 ✅ · engine landed 2026-08-24/25; its HTTP layer and both deferred screens landed with 8.7 on 2026-09-01, which is what closed 4.5.4 and 4.5.7 · [D7b–D7d](docs/02-design.md), [D9](docs/02-design.md), [D10](docs/02-design.md), [D11](docs/02-design.md), [D14](docs/02-design.md), [D25](docs/02-design.md), [D34](docs/02-design.md) · trap: [T2](docs/lessons.md) |
| 5 Lesson Engine | [docs/specs/05-lesson-engine.md](docs/specs/05-lesson-engine.md) | 2, 3, 4 | done-conditions passing (engine only) | 5.1 ✅ 5.2.1 ✅ 5.2.2 ✅ 5.2.3 ✅ 5.2.4 ✅ 5.2.5 ✅ 5.2.6 ✅ 5.3 ✅ 5.4.1 ✅ 5.4.2 ✅ 5.4.3 ✅ 5.4.4 ✅ 5.4.5 ✅ 5.4.6 ✅ 5.5 ✅ 5.6 ⚠️ · ⚠️ = the server half passes; 5.6's done-condition is a manual check over LTE on a real device, and 5.3's browser-kill check is also still manual · **open question**: ownership of 5.6 is stated twice and not identically — spec 5.6 says the check is package 7's, package 8's row claimed it, and nobody has run it either way. Settle it in the package 7 session, which needs a real device for 7.1 anyway · landed 2026-08-25 and 2026-08-28; the screens over it are package 8's (8.8) · [D9](docs/02-design.md), [D12](docs/02-design.md), [D13](docs/02-design.md), [D14](docs/02-design.md), [D15](docs/02-design.md), [D16](docs/02-design.md), [D17](docs/02-design.md) |
| 6 Assessment | [docs/specs/06-assessment.md](docs/specs/06-assessment.md) | 2, 3, 4, 5 | **done** | 6.1 ✅ 6.2 ✅ 6.3 ✅ 6.3.1 ✅ 6.3.2 ✅ 6.3.3 ✅ 6.3.4 ✅ 6.3.5 ✅ 6.4 ✅ 6.5 ✅ · landed 2026-08-28; 6.3 was reopened 2026-09-04 and the repair landed 2026-09-06 — the postmortem is [T5](docs/lessons.md) · `assessment.final_exam.pass_mark` (0.7) is the one number in this package with nothing behind it but convention; watch it · [D18](docs/02-design.md), [D19](docs/02-design.md), [D20](docs/02-design.md), [D25](docs/02-design.md), [D26](docs/02-design.md), [D34](docs/02-design.md) |
| 7 PWA Shell | [docs/specs/07-pwa.md](docs/specs/07-pwa.md) | 1, **8** | code done; **7.1 awaits its two phones** | 7.1 ⚠️ 7.2 WONT 7.3 WONT 7.4 WONT · landed 2026-09-02 — the manifest, the crest icons, the two apple meta tags and the install page · **what no test can close**: 7.1's done-condition is one real iPhone and one real Android, added to the home screen from each browser's own menu on a deployed build. Until Colin does that, 7.1 is ⚠️ · **open for Colin**: `favicon.ico` is still the starter kit's Laravel mark, deliberately left alone as 8.3.5's business — say the word and it goes with the same artwork · shrunk to its literal text 2026-08-31 (`Lighthouse installable` dropped — the PWA category was removed in Lighthouse 12) · not on the critical path: the acceptance test is satisfied by a phone browser · [R2](docs/02-design.md) |
| 8 Interface | [docs/specs/08-interface.md](docs/specs/08-interface.md) | 1–6 | in progress | 8.1 ✅ 8.2.1 ✅ 8.2.2 ⚠️ 8.2.3 ⚠️ 8.2.4 ✅ 8.2.5 ✅ 8.2.6 ✅ 8.2.7 ⚠️ 8.2.8 ✅ 8.2.9 WONT (2026-08-31) 8.3.1 ✅ 8.3.2 ✅ 8.3.3 ✅ 8.3.4 ✅ 8.3.5 ⚠️ 8.3.6 ✅ 8.3.7 WONT 8.4.1 ✅ 8.4.2 ✅ 8.4.3 ✅ 8.4.4 ✅ 8.4.5 WONT 8.5.1 ✅ 8.5.2 ✅ 8.5.3 ✅ 8.5.4 ✅ 8.5.5 ✅ 8.5.6 ✅ 8.6.1 ✅ 8.7.1 ✅ 8.7.2 ✅ 8.7.3 ✅ 8.7.4 ✅ 8.7.5 ✅ 8.7.6 ✅ 8.7.7 ✅ 8.8.1 ✅ 8.8.2 ✅ 8.8.3 ✅ 8.8.4 ✅ 8.8.5 ✅ 8.8.6 ✅ 8.9.1 ✅ 8.9.2 ✅ 8.9.3 ✅ 8.9.4 ✅ 8.9.5 ✅ 8.10.1 ✅ 8.10.2 ✅ 8.10.3 ✅ 8.11.1 ✅ 8.11.2 ✅ 8.11.3 ✅ 8.11.4 ✅ 8.11.5 ✅ 8.11.6 ✅ · sections landed 2026-08-31 through 2026-09-06 · ⚠️ = a fixture-half-only pass, superseded by the v1 result above · **`InterfaceFixtureSeeder` is not called by `DatabaseSeeder`** (Colin, 2026-09-02) — it clears the known student's courses and that account is taking a real course; the rule is in [CLAUDE.md](CLAUDE.md), the seeder's contents in 8.2 · design settled 2026-08-31, canvas source in [docs/design/canvas/](docs/design/canvas/) · [D21–D24](docs/02-design.md) direction and materials, [D30–D33](docs/02-design.md) the shells and the screens' contracts with the engines |

Statuses: not started → in progress (note session/branch) → done-conditions passing → merged.

Item markers: ✅ done-condition passing · ⚠️ partly passing, reason in the row · ⬜ **specced, not built** · WONT out of scope for v1.

## Order of attack (estimate; acceptance test is the real deadline)

- Week 1: 1.*, 2.1–2.4 (**2.4 spike first** — outcome can reorder caching design)
- Week 2: 3.*, 4.1–4.3 (3.3 landed with 4.1 as planned; 4.4 landed with 4.3)
- Week 3: ~~4.5~~ (done 2026-08-25), ~~5.1–5.4~~ (done 2026-08-25)
- Week 4: ~~5.5~~ (done 2026-08-28), ~~6.*~~ (done 2026-08-28), 8.* (the whole interface — see the status row), then 7 if time allows, polish, full dogfood cycle

Ejection order if the month runs out: **7 PWA Shell** → 8.7.6 + 8.10.3 withdrawal and resumption (the pair, never one of them) → 8.10.2 module rollup view. Previously listed: ~~8.9.4 refreshers~~ → ~~8.8.6 lesson completion state~~ — both landed 2026-09-01; ~~6.3.4 the exam answer sheet~~ — landed 2026-09-06. Package 7 leads because the amended acceptance test is satisfied by a phone browser and needs no install (8.11.6). Previously listed: ~~6.3 final exam~~ → ~~6.4 lesson-grain refreshers~~ → ~~5.5 side-tab~~ — all three engines landed 2026-08-28; what is left to eject in each case is package 8's screen.

## Explicit WONTs (v1)

Placement quiz & level gating (v1.1) · teaching-style selection (column ships, one style) · student notes (v1.1) · random-quiz-across-everything (v1.1) · essay/project homework · billing & tiers · model selector · email nudges (v1.1) · admin UI beyond `memory:show` · offline · service worker (7.3) · in-page install button (7.4) · dark mode (v1.1, 8.4.5) · **questions per lesson as a student input, 1–5 (v1.1, recorded 2026-09-04)** · under-18 accounts · BYOK.

## Background docs

- [docs/00-frame.md](docs/00-frame.md) — problem definition
- [docs/01-research.md](docs/01-research.md) — findings + confidence labels + standing unknowns
- [docs/02-design.md](docs/02-design.md) — decision ledger (rejected candidates + rationale; don't re-litigate)
- [docs/03-spec.md](docs/03-spec.md) — spec index
- [docs/anthropic-specific.md](docs/anthropic-specific.md) — portability ledger (spec 2.5)

