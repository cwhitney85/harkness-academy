# Spec 10 — Interview Recovery

*Provides: a way out of a failed interview turn. Consumes: 4 (the interview engine), 8 (screen S3). Opened 2026-09-12 from a dogfood incident; independent of every other open package and buildable by a cold session on its own.*

**Decisions that bind this spec** ([ledger](../02-design.md), index at its head): [D9](../02-design.md) one interview machine, two ends · [D41](../02-design.md) an error names every rule the field broke. Traps: [T10](../lessons.md). The lesson screen's equivalent already shipped as [8.8.11](08-interface.md).

## What happened

A knowledge interview reached its last turn. The tutor's reply streamed, the model signalled the interview complete, and the same request then ran `InterviewSession::summarize()` — a blocking, non-streamed `wk.create` call. The host cut the connection during it (T10).

The student saw: the tutor's last message **disappear**, their own answer **reappear in the composer**, and the error *"That did not reach the tutor. Try sending it again."* Sending it again failed **instantly**, and kept failing.

Three separate defects sit behind that one screen.

**The tutor's turn vanished because `turn.end` never arrived.** `interview.tsx` holds the arriving turn in a live buffer and files it only on `turn.end`; the `finally` clears the buffer either way. A cut stream therefore erases text the student had already read.

**The retry was refused, correctly, and the screen could not say so.** PHP does not notice a disconnected client while it is writing nothing, so `summarize()` ran to completion: the working-knowledge document was written, the transcript discarded, and the course transitioned to the goals interview. The knowledge interview was genuinely over, so `assertUnderway()` threw `InterviewNotUnderway` and the controller answered 422 — which the screen rendered with the same copy as a network failure.

**Nothing on the screen led anywhere.** The only affordance was to send again, which could never succeed. A manual page reload was the sole exit, and nothing said so.

## Items

- **10.1 MUST** — A failed answer reloads the interview screen rather than leaving the student on a dead one. The reload is Inertia's, so it lands on whatever the course is actually doing: still interviewing, or moved on. *Done: with the course transitioned server-side behind the failure, the student ends on the goals interview without touching the browser's reload; with the course unchanged, they end on the same interview with their transcript intact.*
- **10.2 MUST** — A refusal is distinguished from a failure. `InterviewNotUnderway` and `CourseNotOpen` mean the course moved, not that the tutor is unreachable, and the student is sent where the course is instead of being shown an error. *Done: a POST to a completed interview's answers URL lands the student on the course's current screen; no error copy mentions reaching the tutor.*
- **10.3 MUST** — The student's text survives every failure path. It is already restored to the composer on failure; a reload must not lose it. *Done: an answer that fails is still in the composer after 10.1's reload, and is not double-posted when sent again.*
- **10.4 SHOULD** — A tutor turn the student has already read is not erased by a failed stream. Either the partial turn stays on screen until the reload replaces it with the server's transcript, or the reload restores it — the student must not watch text they read disappear. *Done: a stream cut after several deltas leaves the student looking at either that text or the server's copy of it, never at an empty conversation.*
- **10.5 SHOULD** — The failure copy names the recovery. [D41](../02-design.md)'s rule applied to a turn rather than a field: the message says what to do next, and the thing it names is on screen. *Done: no failure state on S3 tells the student only to try something that cannot work.*
- **10.6 WONT** — No sixth stream event announcing that the interview is over. [5.2.5](05-lesson-engine.md) fixes the vocabulary at five, and the reload is how the screen learns the course moved — the mechanism this spec is repairing, not replacing. *Falsified if: the reload proves unable to distinguish the two ends of an interview.*
- **10.7 WONT** — No automatic retry. A turn that failed mid-summary may have succeeded server-side, and a client that retries on its own spends a second `wk.create` on a transcript that no longer exists. *Falsified if: 10.1's reload is shown to be enough to make a retry safe.*

## Acceptance test

*The journey these items compose into (ledger [D29](../02-design.md)). Runnable without opening another spec.*

Against the deployed URL, on a course whose knowledge interview is underway:

1. Answer the last question, so the model signals the interview complete. The screen arrives at the goals interview — whether the summary finished inside the request or the request died during it.
2. Simulate a transient failure mid-answer (stop the environment, or cut the network between the browser and the host). The screen recovers to the same interview with the transcript the server holds, and the typed answer is still in the composer.
3. Post an answer to the knowledge interview after it has completed — a stale tab, or the browser's back button. The student lands on the course's current screen, and is told nothing about tutors being unreachable.
4. At no point in 1–3 is a manual browser reload required, and at no point does text the student has already read vanish without something replacing it.
