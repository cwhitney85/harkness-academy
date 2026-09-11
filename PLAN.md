# Harkness Academy — Build Plan

*Scheduled for retirement. What survives here is the dependency graph and the retirement clause. **Do not write session status into this file** — package state lives in its spec's done-conditions, and where a fact belongs is [CLAUDE.md](CLAUDE.md)'s tier table.*

*The product was named OpenTutor until 2026-09-11; see [D44](docs/02-design.md). Body prose below is left as it was written.*

## Dependency graph

```
1 Foundation ──► 2 AI Layer ──► 3 Memory ──► 4 Course Pipeline ──► 5 Lesson Engine ──► 6 Assessment
                                                                       ▲                    │
                                    6.1 quiz service contract ─────────┘ (6.2 rides 5.4.1)  │
                                                                                            ▼
                                                            8 Interface ──► 7 PWA Shell
```

Package 7 depends on 8, not on 1 alone (spec 8.11.5): package 8 owns responsive behaviour, the palette the manifest's `theme_color` must match, and the crest its icons derive from. It is off the lane-candidate list in ledger W2 for the same reason. Package 9 sits outside the graph — it consumes nothing.

The v1 acceptance test this graph was drawn for is **[docs/system.md §8](docs/system.md)**, where it is recorded as passed on 2026-09-07.

## Retirement

This file retires once [`docs/plans/`](docs/plans/) has **carried three work units start to finish** (ledger [D27](docs/02-design.md), spec [9.7.1](docs/specs/09-documentation.md)). On retirement the dependency graph above moves to [`docs/system.md`](docs/system.md) and nothing else here survives.

**Tally: 1 of 3.** The documentation migration is the first, landed 2026-09-08.
