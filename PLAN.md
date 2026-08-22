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
| 2 AI Layer | [docs/specs/02-ai-layer.md](docs/specs/02-ai-layer.md) | 1 | not started | **2.4 caching spike is the first build task** |
| 3 Memory | [docs/specs/03-memory.md](docs/specs/03-memory.md) | 1 | not started | |
| 4 Course Pipeline | [docs/specs/04-course-pipeline.md](docs/specs/04-course-pipeline.md) | 2, 3 | not started | |
| 5 Lesson Engine | [docs/specs/05-lesson-engine.md](docs/specs/05-lesson-engine.md) | 2, 3, 4 | not started | |
| 6 Assessment | [docs/specs/06-assessment.md](docs/specs/06-assessment.md) | 2, 3, 4, 5 | not started | 6.1 contract can land early for parallel lanes |
| 7 PWA Shell | [docs/specs/07-pwa.md](docs/specs/07-pwa.md) | 1 | not started | parallel lane candidate |

Statuses: not started → in progress (note session/branch) → done-conditions passing → merged.

## Order of attack (estimate; acceptance test is the real deadline)

- Week 1: 1.*, 2.1–2.4 (**2.4 spike first** — outcome can reorder caching design)
- Week 2: 3.*, 4.1–4.3
- Week 3: 4.4–4.5, 5.1–5.4
- Week 4: 5.5, 6.*, 7.1, polish, full dogfood cycle

Ejection order if the month runs out: 6.3 final exam → 6.4 lesson-grain refreshers → 5.5 side-tab (differentiators last).

## Explicit WONTs (v1)

Placement quiz & level gating (v1.1) · teaching-style selection (column ships, one style) · student notes (v1.1) · random-quiz-across-everything (v1.1) · essay/project homework · billing & tiers · model selector · email nudges (v1.1) · admin UI beyond `memory:show` · offline · under-18 accounts · BYOK.

## Background docs

- [docs/00-frame.md](docs/00-frame.md) — problem definition
- [docs/01-research.md](docs/01-research.md) — findings + confidence labels + standing unknowns
- [docs/02-design.md](docs/02-design.md) — decision ledger (rejected candidates + rationale; don't re-litigate)
- [docs/03-spec.md](docs/03-spec.md) — spec index
