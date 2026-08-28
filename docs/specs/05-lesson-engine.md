# Spec 5 — Lesson Engine (the bet itself)

*Provides: the core product loop; SUMMARY docs + WK updates consumed by 6. Consumes: 2, 3, 4 (GUIDANCE, state machine). See PLAN.md for status.*

Design ground rules (ledger D4/D5): the lesson session is the one real agent; code-paced sections; checkpoint grading is code; context assembly order = cache order.

- **5.1 MUST** — Session bootstrap: context = [persona | CRITERIA | WK | GUIDANCE] cached prefix + transcript. *Done: order test; cache reads observed per 2.4.*
- **5.3 MUST** — Resume: reopening restores transcript + section index. *Done: kill browser mid-section, resume, complete.*
- **5.5 MUST** — Select-mode side-tab: selection → stateless `explainer.select` call → side panel; lesson transcript untouched. *Done: transcript byte-identical before/after use.* Engine half only in package 5 (`POST /lessons/{lesson}/explanations`); the panel is package 7's, as 4.5.4's screen is. The passage is read server-side out of the transcript rather than sent by the client ([D16](../02-design.md)).
- **5.6 SHOULD** — Streaming UX floor: first token <3s perceived; mobile-usable. *Done: manual phone check over LTE.* Splits at the first frame ([D17](../02-design.md)): package 5 owns everything before it — nothing whose latency the server does not control precedes `turn.start`, and no intermediary may buffer — and package 7 owns how the rest feels. The done-condition is package 7's to run and needs a phone; regression tests stand in until then.

## 5.2 Checkpoint contract & SSE vocabulary

- **5.2.1 Generation — MUST.** Input: section transcript + GUIDANCE section. Tier cheap. Strict schema; 1 retry; then fail-**open** (section completes quizless, incident logged). *Done: fault injection shows retry → fail-open → lesson advances.*
- **5.2.2 `json/checkpoint.v1` — MUST.** `{checkpoint: {section_index, questions[1–3]: {id, prompt, options[3–4]{id,text}, correct_option_id, explanation, concept_tag}}}`. `concept_tag` ∈ syllabus slugs; the tag itself **SHOULD** (droppable without breaking the loop). *Done: strict enforcement; lossless round-trip via quizzes row.*
- **5.2.3 Client payload — MUST.** `correct_option_id`/`explanation` stripped server-side until answered. *Done: SSE stream inspection shows no pre-answer key.*
- **5.2.4 Grading — MUST.** `POST /lessons/{lesson}/checkpoints/{checkpoint}/answers` `{question_id, option_id}` → code-graded → `quiz_attempts` → returns `{correct, correct_option_id, explanation}`. Checkpoints inform, never gate Continue. *Done: right/wrong/repeat/out-of-order tests.*
- **5.2.5 SSE vocabulary — MUST.** One vocabulary for all streamed turns: `turn.start {turn_id, kind: section|qa|interview|explainer, section_index?, section_title?, sections_total?}` · `turn.delta {turn_id, text}` · `turn.end {turn_id, message_id}` · `checkpoint.ready {payload per 5.2.3}` · `stream.error {code, retryable}`. Explainer MAY ship non-streamed first. *Done: recorded transcript validates; no undocumented events.*
- **5.2.6 Transport — MUST.** `GET /lessons/{lesson}/sections/{index}/stream` (SSE, session auth). Message row persisted at `turn.end`. Reconnect: replay if turn completed, else regenerate section from transcript (idempotent — sections are code-paced). Heartbeat every 15s. *Done: mid-stream kill → reconnect → complete non-duplicated section, exactly one message row.*

## 5.4 Lesson completion workflow

- **5.4.1 Pipeline — MUST.** Sync: status `completed` + completion screen. Async job chain, ordered, idempotent per (lesson, step): `lesson.summarize` → persist summary → `wk.update` → persist WK (versioned) → `homework.generate` → next lesson `available`. *Done: happy path shows all five effects; re-dispatch = zero duplicates.*
- **5.4.2 `json/lesson-summary.v1` — MUST.** `{summary: {prose, concepts[{slug ∈ lesson's declared concepts, mastery: solid|shaky|missed, evidence}], struggles[], questions_asked[]}}`. Inputs include code-computed checkpoint results — model interprets grades, never recomputes them. *Done: undeclared slug fails validation.*
- **5.4.3 Persistence — MUST.** JSON source on lesson row; `{lesson}/SUMMARY.md` rendered into tree (refreshers read the doc). Safe: completed summaries immutable. *Done: golden render test; refresher fixture reads SUMMARY.md.*
- **5.4.4 `wk.update` — MUST.** Full-document replacement (prior WK + summary JSON → new WK). Erosion guard: regression test asserts every prior concept slug survives (or is explicitly demoted) after an unrelated lesson's update; version history makes erosion diffable. *Done: erosion test across 3-lesson fixture sequence.*
- **5.4.5 Failure handling — MUST.** Progress never blocks on the pipeline: completion is sync and irrevocable; chain retries ×3 with backoff, then parks with alert + `artisan lessons:retry-pipeline {lesson}`. *Done: kill worker mid-chain; student sees completion; artisan finishes chain.*
- **5.4.6 Unlock timing — SHOULD.** Next lesson unlocks at chain end (bootstrap should see updated WK); homework interstitial hides the wait. Escape hatch: chain parked ≥N min → unlock anyway (stale WK beats stuck course). *Done: unlock-after-chain test + clock-mocked timeout test.*
