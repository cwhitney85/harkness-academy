# documentation-migration — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** spec 9 items [9.7](../specs/09-documentation.md), 9.7.1, 9.7.2, [9.8](../specs/09-documentation.md), 9.8.1, [9.9](../specs/09-documentation.md), 9.9.1, 9.9.2. Items 9.1–9.6 landed 2026-09-07/08 in the commits ahead of this one; this session closes the package.

## Items

- [ ] **9.7** — PLAN.md reduced to the dependency graph, the acceptance test and the retirement clause, under 2KB.
- [ ] **9.7.1** — the retirement clause: the count, the tally, where the graph goes.
- [ ] **9.7.2** — no status table returns.
- [ ] **9.8** — README owns the roadmap; three entries carried out of the dogfood list.
- [ ] **9.8.1** — the batch-grading entry names the ledger entry it would amend.
- [ ] **9.9** — every spec carries its own acceptance test.
- [ ] **9.9.1** — the v1 global test moves to `docs/system.md`, marked passed 2026-09-07.
- [ ] **9.9.2** — no plan file holds an acceptance test.

## Log

### Where each PLAN.md fact went (9.7's audit)

| Fact | Disposition |
|---|---|
| Global acceptance test | → `docs/system.md` §8 (9.9.1); PLAN.md keeps a pointer, not a copy — 9.9.1 says system.md holds it *once*, and CLAUDE.md's one-owner rule settles the reading of 9.7's "reduced to … the global acceptance test" |
| Dependency graph + package 7's dependency on 8 | stays in PLAN.md until 9.7.1 retires the file |
| Lane rule, founder-merge rule, branching (W4) | already in CLAUDE.md — discarded here |
| Per-item ✅/⚠️ marks, landing dates, session narrative | discarded (9.3.3); package state is the spec's done-conditions (9.7.2) |
| 1.4 merge-blocking deferred — rulesets need a paid plan | → spec 1.4, as a clarification |
| `favicon.ico` is still the starter kit's mark | → spec 8.3.5, as a clarification |
| 5.6 ownership "stated twice" | discarded — spec 5.6 already assigns the check to package 7 |
| `pass_mark` 0.7 "watch it" | discarded — D20's rider and 6.3.2 both carry the number and what it resolves to |
| Ledger and trap cross-links in the status cells | discarded — 9.4 put them in the spec heads |
| "Order of attack", the ejection order | discarded — v1 landed 2026-09-07, so both are spent |
| Explicit WONTs (v1) | v1.1 entries → README roadmap (9.8); the rest are already spec WONTs (7.2–7.4, 8.2.9, 8.4.5, 8.5.5) |
| "Background docs" list | → README's "Where things are decided", which already held most of it |

### Open

- Nothing yet.
