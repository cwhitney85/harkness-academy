# OpenTutor — Build Plan

*The overarching tracker. Each package has a detailed spec in `docs/specs/`; spec item numbers (e.g. 2.4, 5.2.2) are the shared vocabulary across sessions. Update the status table at the end of every session.*

## Global acceptance test (definition of done for v1)

One user completes one honest course cycle **on a phone**: creates "Spanish/Conversational Basics/Intro" → two interviews → approves a syllabus → completes every lesson with checkpoints → passes the final exam → takes a refresher quiz drawing on recorded WORKING_KNOWLEDGE — with total inference cost visible in `usage_events`.
If any step needs a developer touching the DB, v1 is not done.

## Dependency graph

```
1 Foundation ──► 2 AI Layer ──► 3 Memory ──► 4 Course Pipeline ──► 5 Lesson Engine ──► 6 Assessment
      │                                                                    ▲
      └──► 7 PWA Shell (decoupled — parallel lane candidate)               │
                                       6.1 quiz service contract ──────────┘ (6.2 rides 5.4.1)
```

Weeks 1–2 are strictly serial (1→2→3). Parallel worktree lanes open only in weeks 3–4, and **a lane may open only when every interface it consumes has landed on master.** Founder reviews and merges every lane; never auto-merge.

Branching (ledger W4): serial solo work commits directly to `master` at done-condition granularity; branches + founder-reviewed merges only for worktree lanes and risky spikes.

## Status

| Package | Spec | Depends on | Status | Notes |
|---|---|---|---|---|
| 1 Foundation | [docs/specs/01-foundation.md](docs/specs/01-foundation.md) | — | done-conditions passing | 1.1 ✅ 1.2 ✅ 1.3 ✅ ([runbook](docs/deploy.md)) 1.4 ✅ · live at `opentutor-production-ffbp8z.laravel.cloud` · merge-*blocking* for 1.4 deferred to week 3 (2026-08-22, Colin) — rulesets need a paid plan |
| 2 AI Layer | [docs/specs/02-ai-layer.md](docs/specs/02-ai-layer.md) | 1 | done-conditions passing | 2.1 ✅ 2.1.1 ✅ 2.2 ✅ 2.3.1 ✅ 2.3.2 ✅ 2.3.3 ✅ 2.3.4 ✅ 2.3.5 ✅ 2.4 ✅ 2.5 ✅ ([ledger](docs/anthropic-specific.md)) · spike verified live 2026-08-23: 4,742 tokens read from cache on the second call · watch [laravel/ai#869](https://github.com/laravel/ai/pull/869) — it replaces the #119 workaround |
| 3 Memory | [docs/specs/03-memory.md](docs/specs/03-memory.md) | 1 (3.3: **also 4.1**) | done-conditions passing | 3.1 ✅ 3.2 ✅ 3.3 ✅ 3.4 ✅ · path API gained `document()` in the 4.5 session — content + `memory_versions` id from one read, so 4.5.4.1 can pin what a syllabus was built from without reaching past the door ([D10.1](docs/02-design.md)) · 3.3 landed in the 4.1 session as `App\Courses\ModuleRollup` — existence from the tree, levels from `courses`, so a withdrawn subtopic's knowledge stays visible · WK path scope settled as subtopic-level ([D8](docs/02-design.md)) |
| 4 Course Pipeline | [docs/specs/04-course-pipeline.md](docs/specs/04-course-pipeline.md) | 2, 3 | done-conditions passing (engine only) | 4.1 ✅ 4.2 ✅ 4.3 ✅ 4.4 ✅ 4.5.1 ✅ 4.5.2 ✅ 4.5.2.1 ✅ 4.5.2.2 ✅ 4.5.3 ✅ 4.5.4 ⚠️ 4.5.4.1 ✅ 4.5.5 ✅ 4.5.5.1 ✅ 4.5.6 ✅ 4.5.6.1 ✅ 4.5.6.2 ✅ 4.5.7 ⚠️ · ⚠️ = engine half passes, UI half deferred to package 7 (4.5.4's approve/revise screen, 4.5.7's banner) · 4.5 landed 2026-08-25: syllabus drafts are rows pinned to the WK/CRITERIA versions they were built from, `users.plan` ships as a string with the plans table deferred on a named trigger ([D10](docs/02-design.md), [D11](docs/02-design.md)) · interviews landed 2026-08-24: one machine, two ends ([D9](docs/02-design.md)) — `wk.create` gained `prior_wk_doc` and the 2.3.2 inventory row moved with it; `PendingCall::userTurn()` added to package 2 so a brief can be a system prompt (now interview-only — the lesson Q&A path came off it in the 5.4 session, [D14](docs/02-design.md)) · exit rewritten by [D7b](docs/02-design.md) (withdrawal + attempt counter replace delete-on-exit) and [D7c](docs/02-design.md) (both endings are dates; the open-course rule names no statuses, leaving `status` free to become a lookup FK later) and [D7d](docs/02-design.md) (one `CourseStatus` vocabulary; `withdrawn`/`completed` derived by `Course::standing()`, never stored) · engine only — no routes, no UI (Colin, 2026-08-24) · **`TestCase::fakeModel()` added** — laravel/ai keys fakes on the exact agent class, so faking `GatewayAgent` alone let structured calls reach the live API from the test suite; use it, not `GatewayAgent::fake()`, for anything with a json output |
| 5 Lesson Engine | [docs/specs/05-lesson-engine.md](docs/specs/05-lesson-engine.md) | 2, 3, 4 | 5.1–5.4 done-conditions passing | 5.1 ✅ 5.2.1 ✅ 5.2.2 ✅ 5.2.3 ✅ 5.2.4 ✅ 5.2.5 ✅ 5.2.6 ✅ 5.3 ✅ 5.4.1 ✅ 5.4.2 ✅ 5.4.3 ✅ 5.4.4 ✅ 5.4.5 ✅ 5.4.6 ✅ · 5.5 and 5.6 not started (out of this session's scope) · landed 2026-08-25 · routes + controllers, no React — package 7 owns the screens, as it does 4.5.4's · **`quizzes`/`quiz_attempts` landed here, not in 6.1** ([D12](docs/02-design.md)) — 6.1 now owns only the one service and the `final`/`refresher` kinds; the schema, `QuizKind` and `json/quiz.v1` are on master · `lesson_messages` is the table [D9](docs/02-design.md) deferred · erosion guard reports rather than blocks ([D13](docs/02-design.md)) · `LessonStatus` gained `in_progress`/`completed`, the names D10.5 left for this package · **2.3.2 gained a `lesson.qa` row** ([D14](docs/02-design.md), Colin, 2026-08-25) — Q&A is its own `usage_events` bucket because its volume is the least predictable cost in a lesson; the lesson path came off `PendingCall::userTurn()`, which now has interviews as its only caller · 5.6's streaming floor is a manual phone check and 5.3's is a manual browser kill; both have engine-level tests standing in until a device is in hand |
| 6 Assessment | [docs/specs/06-assessment.md](docs/specs/06-assessment.md) | 2, 3, 4, 5 | not started | 6.1's **schema half already landed** with package 5 ([D12](docs/02-design.md)); what is left of 6.1 is the one service and the `final`/`refresher` kinds. 6.2's homework rows are written by 5.4.1's chain — 6.2 owes the serving and the attempt |
| 7 PWA Shell | [docs/specs/07-pwa.md](docs/specs/07-pwa.md) | 1 | not started | parallel lane candidate |

Statuses: not started → in progress (note session/branch) → done-conditions passing → merged.

## Order of attack (estimate; acceptance test is the real deadline)

- Week 1: 1.*, 2.1–2.4 (**2.4 spike first** — outcome can reorder caching design)
- Week 2: 3.*, 4.1–4.3 (3.3 landed with 4.1 as planned; 4.4 landed with 4.3)
- Week 3: ~~4.5~~ (done 2026-08-25), ~~5.1–5.4~~ (done 2026-08-25)
- Week 4: 5.5, 6.*, 7.1, polish, full dogfood cycle

Ejection order if the month runs out: 6.3 final exam → 6.4 lesson-grain refreshers → 5.5 side-tab (differentiators last).

## Explicit WONTs (v1)

Placement quiz & level gating (v1.1) · teaching-style selection (column ships, one style) · student notes (v1.1) · random-quiz-across-everything (v1.1) · essay/project homework · billing & tiers · model selector · email nudges (v1.1) · admin UI beyond `memory:show` · offline · under-18 accounts · BYOK.

## Background docs

- [docs/00-frame.md](docs/00-frame.md) — problem definition
- [docs/01-research.md](docs/01-research.md) — findings + confidence labels + standing unknowns
- [docs/02-design.md](docs/02-design.md) — decision ledger (rejected candidates + rationale; don't re-litigate)
- [docs/03-spec.md](docs/03-spec.md) — spec index
- [docs/anthropic-specific.md](docs/anthropic-specific.md) — portability ledger (spec 2.5)

## Resolved: the `install:features` incident (2026-08-23)

Installing `laravel/ai` fired the starter kit's one-shot `install:features` command through `post-update-cmd`. Running non-interactively it took defaults and stripped package 1's passkey and 2FA scaffolding, then — by design — removed its own hooks from `composer.json` and stopped existing. The scaffolding was restored by hand; the hook removal was kept.

**Not a standing hazard: it cannot fire again.** The command and its `chisel.php` self-cleanup block were deleted 2026-08-23.

`chisel.php`, `chisel-paths.php`, the `laravel/chisel` dependency, and the `/* @chisel-* */` markers in eight package-1 files are now unreachable too — nothing requires them. Left in place; removing them is a package-1 decision.
