<?php

namespace App\Courses\Interviews;

use App\Ai\Gateway;
use App\Ai\Interviews\CompletionListener;
use App\Ai\Interviews\CompletionSignal;
use App\Ai\Interviews\DoneButton;
use App\Ai\Tools\SignalComplete;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\IllegalTransition;
use App\Courses\Exceptions\InterviewAlreadyUnderway;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Exceptions\InterviewSummaryUnusable;
use App\Memory\MemoryTree;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * One interview being conducted (spec 4.3, 4.4).
 *
 * The machine both interviews share: open the conversation, stream a turn at a
 * time, end it either way spec 2.3.4 allows, summarize the transcript into one
 * document, throw the transcript away, move the course on. What differs --
 * which prompts, which document, which status -- is the Interview it holds.
 *
 * Lifecycle contract:
 *   `open()`   pre: the course is open and standing at this interview's status
 *              or the one before it. post: the course stands at this
 *              interview's status and the tutor's first question is streaming.
 *   `answer()` pre: the interview is underway. post: both turns are in the
 *              transcript.
 *   ends       post: the document exists in the memory tree, no transcript row
 *              survives, and the course stands at `next()`. Either all three
 *              or none: they commit together.
 *
 * The two endings of 2.3.4 are one path here. The model's `signal_complete`
 * arrives *during* a stream, so it is recorded and acted on when the stream
 * finishes -- summarizing inside the tool call would delete the transcript
 * while the model was still writing into it. The Done button arrives with no
 * stream in flight and summarizes immediately. Same method, same order of
 * effects.
 */
final class InterviewSession implements CompletionListener
{
    /**
     * The turn that starts the conversation.
     *
     * The tutor speaks first, but a provider conversation has to open with a
     * user message, so pressing Start is written down as what it is. It is a
     * real turn, not a placeholder: it is in the transcript the summarizer
     * reads, and it is what the model is answering.
     */
    public const OPENING_TURN = 'I am ready to start.';

    /**
     * The longest single answer a student may send (spec 8.7.2).
     *
     * Read by the screen out of the interview payload rather than duplicated
     * there, which is the same rule 8.8.5 states for the lesson composer: one
     * number, owned by the class that enforces it.
     */
    public const MAX_ANSWER = 4000;

    private readonly Transcript $transcript;

    /**
     * Set the moment the interview is declared over, by either door.
     */
    private ?CompletionSignal $signal = null;

    private bool $streaming = false;

    public function __construct(
        private readonly Gateway $gateway,
        private readonly MemoryTree $memory,
        private readonly CourseTransitions $transitions,
        private readonly Course $course,
        private readonly Interview $interview,
    ) {
        $this->transcript = new Transcript($course, $interview->kind());
    }

    /**
     * Begin the interview and stream the tutor's opening question.
     *
     * Moves the course into this interview's status if it is not there yet;
     * the transition service refuses the move from anywhere illegal, so a
     * course cannot be interviewed out of order.
     *
     * Opening discards whatever the transcript holds, which is safe in exactly
     * one case: nothing but the opening turn is in it. That is the case this
     * refuses to leave -- the tutor having spoken once is enough to make a
     * re-open destroy a student's interview, and the flow map's client-side
     * precondition is not somewhere a bug can be caught (spec 8.7.2).
     *
     * Re-opening a transcript that holds only `OPENING_TURN` *is* allowed, and
     * is the recovery for a connection dropped during the tutor's very first
     * question: nothing the student typed is discarded, because they have not
     * typed anything (Colin, 2026-09-01, narrowing 8.6's wording).
     *
     * @throws CourseNotOpen|IllegalTransition|InterviewAlreadyUnderway
     */
    public function open(): StreamableAgentResponse
    {
        if (! $this->course->isOpen()) {
            throw CourseNotOpen::for($this->course);
        }

        if ($this->transcript->hasTutorTurn()) {
            throw InterviewAlreadyUnderway::for($this->course, $this->interview->kind()->value);
        }

        if ($this->course->status !== $this->interview->status()) {
            $this->transitions->to($this->course, $this->interview->status());
        }

        $this->transcript->discard();
        $this->transcript->append(Speaker::Student, self::OPENING_TURN);

        return $this->ask();
    }

    /**
     * Answer the tutor's last question and stream the next one.
     *
     * @throws InterviewNotUnderway|CourseNotOpen
     */
    public function answer(string $text): StreamableAgentResponse
    {
        $this->assertUnderway();

        $this->transcript->append(Speaker::Student, $text);

        return $this->ask();
    }

    /**
     * The always-visible Done button (spec 2.3.4, decision R3).
     *
     * Handed out rather than wrapped so the student's door into the workflow
     * is the same class package 2 tests, not a lookalike.
     *
     * @throws InterviewNotUnderway|CourseNotOpen
     */
    public function doneButton(): DoneButton
    {
        $this->assertUnderway();

        return new DoneButton($this);
    }

    /**
     * The interview is over -- by the model's signal or the student's button.
     *
     * @throws InterviewSummaryUnusable
     */
    public function completed(CompletionSignal $signal): void
    {
        $this->signal ??= $signal;

        if (! $this->streaming) {
            $this->summarize();
        }
    }

    /**
     * Where the course stands while this interview is running.
     *
     * Handed out so a screen can ask "is this interview still the course's
     * business?" without a second copy of the kind-to-status mapping that
     * KnowledgeInterview and GoalsInterview already own (spec 8.7.2).
     */
    public function status(): CourseStatus
    {
        return $this->interview->status();
    }

    /**
     * Why the interview ended, once it has.
     */
    public function signal(): ?CompletionSignal
    {
        return $this->signal;
    }

    /**
     * Send the transcript's newest student turn and stream the reply.
     *
     * @throws InterviewNotUnderway
     */
    private function ask(): StreamableAgentResponse
    {
        $history = $this->transcript->messages();
        $turn = array_pop($history);

        if ($turn === null) {
            throw InterviewNotUnderway::for($this->course, $this->interview->kind()->value);
        }

        $this->streaming = true;

        return $this->gateway
            ->prompt($this->interview->briefKey(), $this->interview->briefVars($this->course))
            ->feature($this->interview->feature())
            ->for($this->course->user_id)
            ->messages($history)
            ->userTurn((string) $turn->content)
            ->tools([new SignalComplete($this)])
            ->stream()
            ->then(function (AgentResponse $response): void {
                $this->streaming = false;

                if (trim($response->text) !== '') {
                    $this->transcript->append(Speaker::Tutor, $response->text);
                }

                if ($this->signal instanceof CompletionSignal) {
                    $this->summarize();
                }
            });
    }

    /**
     * Turn the conversation into the document it existed to produce.
     *
     * Fails closed. An unusable summary leaves the transcript intact and the
     * course where it was, so the student can end the interview again; writing
     * an empty document instead would erase whatever earlier courses in this
     * subtopic recorded (ledger D7a).
     *
     * @throws InterviewSummaryUnusable
     */
    private function summarize(): void
    {
        // Already done -- a second Done press, or a signal that arrived after
        // the button. The transcript is the record of that: it only goes when
        // the document lands.
        if ($this->transcript->isEmpty()) {
            return;
        }

        $summary = $this->gateway
            ->prompt(
                $this->interview->summaryKey(),
                $this->interview->summaryVars($this->course, $this->transcript->render()),
            )
            ->feature($this->interview->feature())
            ->for($this->course->user_id)
            ->generate();

        $document = trim($summary->text);

        if ($document === '') {
            throw InterviewSummaryUnusable::for($this->course, $this->interview->summaryKey());
        }

        DB::transaction(function () use ($document): void {
            $this->memory->put($this->course->student, $this->interview->documentPath($this->course), $document);

            $this->transcript->discard();

            $this->transitions->to($this->course, $this->interview->next());
        });
    }

    /**
     * @throws InterviewNotUnderway|CourseNotOpen
     */
    private function assertUnderway(): void
    {
        if (! $this->course->isOpen()) {
            throw CourseNotOpen::for($this->course);
        }

        // `hasTutorTurn`, not `isEmpty`: a transcript holding only the
        // opening turn is a conversation nobody has had, and both answering
        // it and summarizing it are meaningless. Done in particular must
        // refuse it -- summarizing it would write a WORKING_KNOWLEDGE
        // document made of one line the student never typed, over whatever
        // earlier courses in the subtopic recorded (ledger D7a).
        if ($this->course->status !== $this->interview->status() || ! $this->transcript->hasTutorTurn()) {
            throw InterviewNotUnderway::for($this->course, $this->interview->kind()->value);
        }
    }
}
