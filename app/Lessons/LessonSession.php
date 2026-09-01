<?php

namespace App\Lessons;

use App\Ai\Gateway;
use App\Ai\PendingCall;
use App\Lessons\Checkpoints\Checkpoint;
use App\Lessons\Checkpoints\CheckpointGenerator;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Exceptions\NoSuchSection;
use App\Lessons\Streaming\CheckpointReady;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\TurnDelta;
use App\Lessons\Streaming\TurnEnd;
use App\Lessons\Streaming\TurnKind;
use App\Lessons\Streaming\TurnStart;
use App\Models\Lesson;
use App\Models\LessonMessage;
use App\Models\Quiz;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * One lesson being taught (spec 5.1, 5.2, 5.3).
 *
 * **The one real agent** (ledger D4). Everything else in OpenTutor is a
 * scripted call; this is the open-ended conversation, and it is code-paced
 * (D5): the application decides when a section starts and ends, the model
 * teaches the section it is asked for and stops.
 *
 * Context assembly is 5.1's contract and LessonContext holds it: the system
 * prompt is `[persona | CRITERIA | WK | GUIDANCE]`, cached as a prefix, and
 * the transcript follows it uncached. That order is why the fourth lesson of
 * a course costs a fraction of the first.
 *
 * Every turn is idempotent by construction, which is what 5.2.6's reconnect
 * rule rests on. A turn that completed has a row and is replayed byte for
 * byte; a turn that did not has nothing, and re-teaching it is safe because
 * nothing downstream saw the fragment.
 */
final class LessonSession
{
    /**
     * The longest question the session will answer.
     *
     * Generous, because a student explaining what confuses them is exactly
     * when they need room, and every character is billed at the session tier
     * against a growing transcript (ledger D14). Named rather than inlined at
     * the route so `LessonState` can publish it: a limit the screen cannot see
     * is a limit the student meets as a failed request.
     */
    public const MAX_QUESTION = 4000;

    private readonly LessonTranscript $transcript;

    private readonly LessonSections $sections;

    public function __construct(
        private readonly Gateway $gateway,
        private readonly LessonContext $context,
        private readonly CheckpointGenerator $checkpoints,
        private readonly LessonProgress $progress,
        private readonly Lesson $lesson,
    ) {
        $this->transcript = new LessonTranscript($lesson);
        $this->sections = LessonSections::of($lesson);
    }

    public function lesson(): Lesson
    {
        return $this->lesson;
    }

    public function sections(): LessonSections
    {
        return $this->sections;
    }

    public function transcript(): LessonTranscript
    {
        return $this->transcript;
    }

    /**
     * Teach one section, or replay it if it has already been taught.
     *
     * Pre: the lesson is openable, `$index` names one of its sections, and
     *      the student is not skipping ahead of where they have reached.
     * Post: exactly one `section` row exists for `$index`, a `turn.end`
     *       naming it has been written, and -- unless generation failed open
     *       -- a `checkpoint.ready` has followed it.
     *
     * Reconnecting to a section that finished costs no tokens and produces
     * the identical event sequence, which is what makes 5.2.6's "replay if
     * the turn completed" a property rather than an intention.
     *
     * @throws LessonNotOpenable|NoSuchSection
     */
    public function teachSection(int $index, EventStream $out): void
    {
        $this->progress->assertSectionOpenable($this->lesson, $this->sections, $index);

        $taught = $this->transcript->section($index);

        if ($taught instanceof LessonMessage) {
            $this->replay($taught, $out);

            return;
        }

        $this->progress->enterSection($this->lesson, $index);

        $turnId = (string) Str::uuid();

        $out->send(new TurnStart(
            turnId: $turnId,
            kind: TurnKind::Section,
            sectionIndex: $index,
            sectionTitle: $this->sections->title($index),
            sectionsTotal: count($this->sections),
        ));

        $message = $this->streamTurn(
            $out,
            $turnId,
            TurnKind::Section,
            $index,
            $this->gateway
                ->prompt('lesson.section', [
                    'section_index' => (string) $index,
                    'section_title' => $this->sections->title($index),
                    'section_concepts' => $this->sections->conceptList($index),
                ])
                ->feature('lesson.section')
                ->for($this->lesson->course->user_id)
                ->instructions($this->context->systemPromptFor($this->lesson))
                // Everything said in earlier sections, and nothing from this
                // one: a section is regenerated from the transcript that
                // preceded it, so re-teaching it cannot see a half-turn.
                ->messages($this->transcript->messagesBeforeSection($index)),
        );

        if ($message === null) {
            return;
        }

        $this->offerCheckpoint($index, $out);
    }

    /**
     * Answer a question the student asked mid-section (ledger D5).
     *
     * The Q&A turn of 5.2.5's vocabulary. It joins the transcript, so the
     * summarizer sees what was asked and the next section is taught by a model
     * that remembers answering it -- which is the whole reason the lesson is
     * one conversation rather than N independent calls.
     *
     * Ledger D9's rider in substance -- `lesson.system` as the instructions,
     * the student's message as the turn -- but reached by rendering
     * `lesson.qa` rather than by `PendingCall::userTurn()`. Same bytes on the
     * wire; the difference is that the prompt file is really a prompt. A key
     * borrowed from `lesson.section` would have put teaching and questioning
     * in one `usage_events` bucket, and Q&A volume is the least predictable
     * cost in a lesson -- the one number you most want separated before
     * dogfooding (ledger D14).
     *
     * `lesson.qa` runs at `tier: session` for a reason beyond consistency:
     * Anthropic's cache is keyed per model, so a Q&A turn at any other tier
     * would miss the whole `[persona | CRITERIA | WK | GUIDANCE]` prefix and
     * pay to rewrite it in the middle of a lesson.
     *
     * @throws LessonNotOpenable
     */
    public function answerQuestion(string $text, EventStream $out): void
    {
        $index = $this->lesson->section_index ?? 1;

        $this->progress->assertSectionOpenable($this->lesson, $this->sections, $index);

        $turnId = (string) Str::uuid();

        // Written down before it is answered, so the tutor's reply cannot land
        // at a lower position than the question it answers.
        $question = $this->transcript->append(
            TurnKind::Qa,
            Speaker::Student,
            $text,
            $index,
            (string) Str::uuid(),
        );

        $out->send(new TurnStart(turnId: $turnId, kind: TurnKind::Qa));

        $this->streamTurn(
            $out,
            $turnId,
            TurnKind::Qa,
            $index,
            $this->gateway
                ->prompt('lesson.qa', ['question' => $text])
                ->feature('lesson.qa')
                ->for($this->lesson->course->user_id)
                ->instructions($this->context->systemPromptFor($this->lesson))
                // The question is the outgoing message, so the history stops
                // short of it -- sending both would put the same words in
                // front of the model twice.
                ->messages($this->transcript->messagesPreceding($question)),
        );
    }

    /**
     * Stream one model turn and persist it at `turn.end`.
     *
     * The message row is written *before* `turn.end` goes out, never after:
     * `turn.end` is the client's evidence that reconnecting will replay rather
     * than re-teach, and an event that outran its row would be a lie the
     * client acts on.
     *
     * An empty reply writes no row and no `turn.end`. The section is then
     * untaught, and the next request teaches it -- which is the correct
     * outcome and needs no special case anywhere else.
     */
    private function streamTurn(
        EventStream $out,
        string $turnId,
        TurnKind $kind,
        int $index,
        PendingCall $call,
    ): ?LessonMessage {
        $message = null;

        $call->sessionTier($this->context->tier())
            ->stream()
            ->then(function (AgentResponse $response) use (&$message, $kind, $index, $turnId): void {
                if (trim($response->text) === '') {
                    return;
                }

                $message = $this->transcript->append(
                    $kind,
                    Speaker::Tutor,
                    $response->text,
                    $index,
                    $turnId,
                );
            })
            ->each(function (mixed $event) use ($out, $turnId): void {
                if ($event instanceof TextDelta && $event->delta !== '') {
                    $out->send(new TurnDelta($turnId, $event->delta));
                }

                $out->heartbeatIfDue();
            });

        if (! $message instanceof LessonMessage) {
            return null;
        }

        // A racing client may have persisted this section first, in which case
        // `append` handed back the row that won. Report the id the transcript
        // actually holds, so `turn.end` and a later replay agree.
        $out->send(new TurnEnd($turnId, $message->id));

        return $message;
    }

    /**
     * Emit the section's checkpoint, if there is one to emit.
     *
     * Generation fails open (5.2.1): a section with no checkpoint simply has
     * no `checkpoint.ready`, and the client's Continue is unaffected because
     * checkpoints never gated it (5.2.4).
     */
    private function offerCheckpoint(int $index, EventStream $out): void
    {
        $out->heartbeatIfDue();

        $quiz = $this->checkpoints->forSection($this->lesson, $index);

        if ($quiz instanceof Quiz) {
            $out->send(new CheckpointReady($quiz->id, Checkpoint::of($quiz)->clientPayload()));
        }
    }

    /**
     * Send a completed section again, without paying for it again.
     *
     * The whole turn arrives as one delta. Re-chunking it would invent a
     * cadence the model never produced, and a client that renders deltas as
     * they arrive renders this identically either way.
     */
    private function replay(LessonMessage $message, EventStream $out): void
    {
        $index = $message->section_index;

        $out->send(new TurnStart(
            turnId: $message->turn_id,
            kind: TurnKind::Section,
            sectionIndex: $index,
            sectionTitle: $this->sections->title($index),
            sectionsTotal: count($this->sections),
        ));

        $out->send(new TurnDelta($message->turn_id, $message->content));
        $out->send(new TurnEnd($message->turn_id, $message->id));

        $this->offerCheckpoint($index, $out);
    }
}
