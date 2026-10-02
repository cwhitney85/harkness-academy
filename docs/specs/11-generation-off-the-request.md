# Spec 11 — Generation Leaves the Request

*Provides: a rule about what may block inside an HTTP request, and the two moves that make the app obey it. Consumes: 2 (the gateway and `usage_events`), 5 (the lesson engine), 4 (the interviews), 8 (the waiting states). Opened 2026-09-12 from a dogfood incident; independent of every other open package and buildable by a cold session on its own.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D25](../02-design.md) `top`-tier calls run on the queue · [R4](../02-design.md) the host must not buffer SSE · [D29](../02-design.md) an acceptance test lives in a spec · [D48](../02-design.md) a checkpoint's wait runs out · [D49](../02-design.md) Done's write-up is queued too. Traps: [T10](../lessons.md). Runbook: [docs/deploy.md](../deploy.md), the **Streaming** section.

## The rule

**The host cuts a response that says nothing for 60 seconds, and that number is not ours.** Laravel Cloud's nginx sets `fastcgi_read_timeout` from a dashboard field that **caps at 60s** — the default of 20s is what cut a lesson section and an interview summary on the first two real courses (T10). A call the application still believes in past 60 seconds is one the platform has already killed, so the application's own error handling never runs and the student is told nothing useful by the layer that gave up first.

**The tier timeouts cannot restore the ordering.** `mid` allows 120 seconds and `top` 300; both exceed the ceiling, and lowering them would constrain the queue — where those numbers are real and where `homework.generate` has been measured at 44.6s — to fix the request. The ordering the queue gets right, its `--timeout` of 360 sitting above `top`'s 300, cannot be bought here at any price.

**So the rule is about silence, not duration.** A streamed response may take as long as it likes provided it keeps writing. What it may not do is run a blocking, non-streamed generation in the middle and go quiet.

## What violates it today

Two calls do, both the same shape — a `->generate()` inside a request that is streaming SSE, writing nothing while it runs.

| Call | Where | Production max | Local max |
|---|---|---|---|
| `checkpoint.generate` | `LessonSession::offerCheckpoint()`, after `turn.end` | 24.7s | 36.6s |
| `wk.create` / `criteria.create` | `InterviewSession::summarize()`, after the last turn | 28.3s | 19.9s |

**Neither exceeds 60s today, which is why this is a package rather than an incident.** Measured 2026-09-12 over 9 sections and 8 summaries on the deployed environment with `php artisan usage:latency` ([2.2.1](02-ai-layer.md)). The margin is real and the sample is small: one longer section, one cold prompt cache, or one slow hour at the provider closes it, and the failure mode is the silent one the student cannot act on.

**`EventStream::heartbeatIfDue()` cannot cover either window.** It beats at 15 seconds, under the old 20s ceiling and far under 60 — but it is only ever called from inside the delta callback, so it cannot beat until the stream it protects has already started, and it cannot beat at all while PHP is parked in a blocking call. `laravel/ai` parses Anthropic's own `ping` keepalive and matches no branch for it, so it never reaches `each()`. There is no seam. This is recorded in T10 and is the reason the fix is architectural rather than a timer.

## Items

- **11.1 MUST** — `checkpoint.generate` leaves the section stream. The stream ends when the tutor's turn ends; the checkpoint is generated on the queue and reaches the screen when it is ready. *Done: `SectionStreamController`'s response completes without waiting on a checkpoint; no `usage_events` row for `checkpoint.generate` shares a request with a `lesson.section` row.* *Proved in `QueuedCheckpointTest`.*
- **11.2 MUST** — The section's Continue is available as soon as the prose is. Checkpoints never gated it ([5.2.4](05-lesson-engine.md)) and must not begin to. *Done: the button is pressable while the checkpoint is still generating.* *Proved in `resources/js/pages/lessons/show.test.tsx`.*
- **11.3 MUST** — Checkpoint generation still fails open ([5.2.1](05-lesson-engine.md)). A section whose checkpoint never arrives is a section with no checkpoint, not a section the student is stuck on. The wait runs out after two minutes, and the margin then says so and offers Try again ([D48](../02-design.md)). *Done: with the queue stopped, the lesson is completable end to end and no screen waits forever.* *Proved in `QueuedCheckpointTest` (the lesson, the wait's end, Try again) and `show.test.tsx` (the margin).*
- **11.4 MUST** — `InterviewSession::summarize()` leaves the answer request. The tutor's last turn streams and the request returns; the summary, the memory write, the transcript discard and the course transition happen on the queue, in the transaction they are in today. Done's write-up goes the same way ([D49](../02-design.md)). *Done: the answers request returns within the time its stream takes; the course transition is observable afterwards without a second answer being posted.* *Proved in `QueuedInterviewWriteUpTest`.*
- **11.5 MUST** — Both use the machinery already built for `DrawUpSyllabus` and `WriteFinalExam` — a `generation_requests` row keyed `(course_id, kind)` (one *open* row once [16.2.11](16-catalog-removal-ownership.md) keeps settled ones), a job implementing `failed()`, and the screen's existing waiting state ([8.12.1](08-interface.md)). No second mechanism for the same problem. *Done: no new table, and the two new kinds appear in the same enum the syllabus and exam use.* *`GenerationKind::Checkpoint` and `::InterviewSummary`; `WriteCheckpoint` and `WriteUpInterview`.*
- **11.6 MUST** — Every queued generation the student is waiting on names what is being made and cannot be started twice ([8.12.1](08-interface.md), [8.12.3](08-interface.md)). *Done: the interview's last turn and a section awaiting its checkpoint each show what is being written, with the control that started it disabled.* *Proved in `interview.test.tsx` and `show.test.tsx`. A section's checkpoint is started by the stream, not a control, and Try again appears only once the wait is over.*
- **11.7 SHOULD** — `php artisan usage:latency` ([2.2.1](02-ai-layer.md)) judges the calls that write nothing until they finish, and `--ceiling` is set to what the host actually allows. It measures a call's whole duration, which is the silence only for a call that says nothing until it ends; a streamed prompt's exposure is the wait for its first word, which the report does not measure and [11.8](#items) watches. *Done: every prompt reading `close` or `over` either runs on the queue or streams.*
- **11.8 WONT** — **`lesson.section` does not leave the request, ever.** The student watching the tutor write is the product ([8.8.2](08-interface.md) paints the header before the first token precisely to serve it), and a queued lesson section is a textbook with a spinner. Its exposure is the wait for the first token, which is bounded by the provider rather than by us and measured at 42.7s worst against the 60s ceiling. *Falsified if: the first-token wait is measured past the ceiling on a real course, at which point the question is the prompt's size or the tier's model, not the queue.*
- **11.9 WONT** — No heartbeat thread, signal handler or `pcntl_alarm` to write bytes from inside a blocking call. It means delivering a signal into a vendor's blocking socket read, and it is new structure in the riskiest part of the stack to defend against a number the queue removes. *Falsified if: `laravel/ai` surfaces Anthropic's `ping` to `each()`, which makes the existing heartbeat work with no change here — worth an upstream issue, and the repo has the precedent of laravel/ai#119.*
- **11.10 WONT** — No change to the tiers' timeouts (in `config/ai.php` when this was written, in `ai_tiers` since [14.1.2](14-routing-and-usage.md); the numbers are unchanged). Below a 60s host ceiling the in-request half of `mid`'s 120 is fiction, but the queued half is real and lowering it would truncate `homework.generate` and `wk.update`. *Falsified if: a tier is ever used only inside requests.*

## Acceptance test

*The journey these items compose into (ledger [D29](../02-design.md)). Runnable without opening another spec.*

Against the deployed URL, with a queue worker running:

1. Teach a lesson section. The prose streams, the Continue button appears as soon as the prose ends, and the checkpoint arrives after it without the page being reloaded by hand.
2. Stop the queue worker and teach another section. The prose still streams, Continue still works, and the lesson is completable — the checkpoint simply never arrives, and no screen claims it is coming forever.
3. Finish an interview so the model signals it complete. The last turn streams, the screen says what is being written, and it lands on the next step of the course without a manual reload.
4. Run `php artisan usage:latency` from the Commands tab with the host's real ceiling. Every prompt reading `close` or `over` is either queued or streamed.
5. Across 1–4, the environment's logs contain no `upstream timed out … while reading upstream`.
