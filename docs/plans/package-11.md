# package-11 — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [spec 11](../specs/11-generation-off-the-request.md), items 11.1–11.10.

## Items

- [x] **11.1** — `checkpoint.generate` leaves the section stream; generated on the queue, reaches the screen when ready.
- [x] **11.2** — Continue pressable while the checkpoint is still generating.
- [x] **11.3** — Fails open with the queue stopped; no screen waits forever.
- [ ] **11.4** — `InterviewSession::summarize()` leaves the answer request; summary, memory write, discard, transition on the queue in one transaction.
- [ ] **11.5** — Both on `generation_requests` + a job with `failed()`; two new `GenerationKind` cases, no new table.
- [ ] **11.6** — Interview's last turn and a section awaiting its checkpoint each name what is being written, starting control disabled.
- [ ] **11.7** — `usage:latency --ceiling` is the instrument (already defaults to 60). Done-condition is read on the deployed environment.
- [ ] **11.8–11.10 WONT** — nothing built; confirm nothing in the diff touches them.

## Log

**Gap 1 — the row's key does not name a section.** `generation_requests` is unique on `(course_id, kind)`, so a course holds one checkpoint wait at a time; a checkpoint belongs to a section. And 11.3 needs the screen to stop waiting on a checkpoint whose job never runs, which no existing wait does (syllabus and exam poll forever). Put to Colin.

*Answer (2026-09-13):* keep the key; the row is read as the furthest taught section's. A checkpoint's wait runs out at 2 minutes (the generator's two attempts at `cheap`'s 60s), and then the margin says so — "No question for this section this time" — with **Try again**; Continue beneath it is the move-on. Colin asked for both buttons; Continue already is the second one. A section that finishes while another's request is pending gets none until Try again.

**Gap 2 — Done.** `summarize()` is one method behind both doors (the model's signal and the Done button). Moving it moves Done onto the queue too: the POST returns at once and the screen waits. 11.4 names only the answer request. Put to Colin.

*Answer:* both doors go to the queue, and the answer box and Send are disabled while the write-up runs, not only Done.

**Try again is a route**, `POST /lessons/{lesson}/sections/{index}/checkpoint`, so spec 8.6's flow map gains an edge (FlowMapTest). S3's edges also change with 11.4.

`show.tsx`'s poll interval is `5 * 1000`: `LessonScreenTest` greps the screen for the highlight cap (500), and `5000` contains it.

phpstan: 7 errors, all in `UsageLatencyCommand`, present before this unit.
