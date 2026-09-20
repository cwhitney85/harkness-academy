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
use App\Courses\GenerationKind;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\GenerationRequest;
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
 *              transcript, and the interview is over only if the tutor said so.
 *   ends       post: a write-up is pending on the queue, and the interview
 *              refuses answers until it lands or fails.
 *   `writeUp()` post: the document exists in the memory tree, no transcript
 *              row survives, and the course stands at `next()`. Either all
 *              three or none: they commit together.
 *
 * The two endings of 2.3.4 are one path here. The model's `signal_complete`
 * arrives *during* a stream, so it is recorded and acted on when the stream
 * finishes -- asking inside the tool call would let the write-up delete the
 * transcript while the model was still writing into it. The Done button
 * arrives with no stream in flight and asks immediately. Same method, same
 * order of effects.
 *
 * **The write-up is queued work** (spec 11.4). It is a blocking, non-streamed
 * call, and it used to run at the end of the answer's stream, where the host
 * cuts a response that has said nothing for 60 seconds (T10).
 *
 * **R3's two doors are the only ways out, and the question budget is not a
 * third.** Spending it (4.3.1, 4.4.1) swaps KEEP_GOING for WRAP_UP in the
 * brief and does nothing else: the tutor is asked to end the conversation on
 * every turn from then on, and ends it by calling `signal_complete` like
 * always. An interview whose model ignores that runs long, which is the cost
 * chosen over interrupting a student mid-answer to move them on.
 */
final class InterviewSession implements CompletionListener
{
    /**
     * The message a provider conversation has to open with (spec 4.6).
     *
     * The tutor speaks first, but the Messages API takes no assistant message
     * ahead of a user one, and the gateway refuses a streamed call with an
     * empty user turn. So this exists on the wire and nowhere else: it is
     * never a transcript row, never a line the screen paints, and never a line
     * the summarizer reads. Nobody said it, so nobody is shown saying it.
     */
    private const OPENING_TURN = 'I am ready to start.';

    /**
     * The longest single answer a student may send (spec 8.7.2).
     *
     * Read by the screen out of the interview payload rather than duplicated
     * there, which is the same rule 8.8.5 states for the lesson composer: one
     * number, owned by the class that enforces it.
     */
    public const MAX_ANSWER = 4000;

    /**
     * What the brief's `pacing` variable says on an ordinary turn.
     */
    private const KEEP_GOING = 'Ask your next question.';

    /**
     * What it says once the question budget is spent, and on every turn after.
     *
     * It asks for an ending; it does not impose one. Forcing the ending after
     * this turn is what the first build did, and against a model that asked
     * another question anyway the student got a question and then a screen
     * they had not asked for -- the interruption was worse than the length
     * (Colin, 2026-09-08). Repetition is the pressure: an interview that runs
     * long is told to wrap up on every remaining turn, which is more than a
     * line in the brief read once and buried under a growing transcript.
     */
    private const WRAP_UP = 'You have asked enough questions. Wrap up now: tell the student you have what '
        .'you need, and call `signal_complete`. Do not open a new line of questioning.';

    private readonly Transcript $transcript;

    /**
     * Set the moment the interview is declared over, by either door.
     */
    private ?CompletionSignal $signal = null;

    private bool $streaming = false;

    /**
     * Whether the tutor has spent its question budget (spec 4.3.1, 4.4.1).
     */
    private bool $budgetSpent = false;

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
     * An interview the tutor has already spoken in is refused, since the flow
     * map's client-side precondition is not somewhere a bug can be caught
     * (spec 8.7.2). Short of that there is nothing to lose: a student turn
     * only ever follows a tutor turn, so a transcript with no tutor turn in it
     * is empty, and re-opening is the recovery for a connection dropped during
     * the tutor's very first question (spec 8.6).
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

        return $this->ask();
    }

    /**
     * Answer the tutor's last question and stream the tutor's next turn.
     *
     * Pre:  the interview is underway.
     * Post: both turns are in the transcript once the stream is consumed, and
     *       the interview is still underway unless the tutor ended it itself.
     *       Spending the question budget changes what the brief asks for and
     *       nothing else -- this method never ends an interview (4.3.1).
     *
     * @throws InterviewNotUnderway|CourseNotOpen
     */
    public function answer(string $text): StreamableAgentResponse
    {
        $this->assertUnderway();

        $this->transcript->answer($text);

        $this->budgetSpent = $this->transcript->questionsAsked() >= $this->interview->questionBudget();

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
     */
    public function completed(CompletionSignal $signal): void
    {
        $this->signal ??= $signal;

        if (! $this->streaming) {
            $this->askForWriteUp();
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
     * Send the newest student turn and stream the reply.
     *
     * The opener is prepended rather than stored (4.6), which is also what
     * keeps the history the provider sees alternating: `array_pop` takes the
     * turn being answered off the end, and what is left starts with a user
     * message whether the tutor has spoken once or five times.
     */
    private function ask(): StreamableAgentResponse
    {
        $history = [Speaker::Student->message(self::OPENING_TURN), ...$this->transcript->messages()];
        $turn = array_pop($history);

        $this->streaming = true;

        return $this->gateway
            ->prompt($this->interview->briefKey(), [
                ...$this->interview->briefVars($this->course),
                'pacing' => $this->budgetSpent ? self::WRAP_UP : self::KEEP_GOING,
            ])
            ->feature($this->interview->feature())
            ->about($this->course)
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
                    $this->askForWriteUp();
                }
            });
    }

    /**
     * Put the write-up on the queue, unless it is already there (spec 11.4).
     */
    private function askForWriteUp(): void
    {
        if (GenerationRequest::open($this->course, GenerationKind::InterviewSummary) !== null) {
            WriteUpInterview::dispatch($this->course->id, $this->interview->kind());
        }
    }

    /**
     * Turn the conversation into the document it existed to produce.
     *
     * Run by `WriteUpInterview`, never inside a request (11.4).
     *
     * Fails closed. An unusable summary leaves the course where it was, so the
     * student can end the interview again; writing an empty document instead
     * would erase whatever earlier courses in this subtopic recorded (ledger
     * D7a).
     *
     * The transcript is kept either way (ledger D9, amended 2026-09-19).
     *
     * @throws InterviewSummaryUnusable
     */
    public function writeUp(): void
    {
        // Already done -- a second Done press, or a signal that arrived after
        // the button. The course's status is the record of that: it moves in
        // the same transaction as the document, and no status leads back into
        // an interview (CourseStatus::successors).
        if ($this->course->fresh()?->status !== $this->interview->status()) {
            return;
        }

        $summary = $this->gateway
            ->prompt(
                $this->interview->summaryKey(),
                $this->interview->summaryVars($this->course, $this->transcript->render()),
            )
            ->feature($this->interview->feature())
            ->about($this->course)
            ->generate();

        $document = trim($summary->text);

        if ($document === '') {
            throw InterviewSummaryUnusable::for($this->course, $this->interview->summaryKey());
        }

        DB::transaction(function () use ($document): void {
            $this->memory->put($this->course->student, $this->interview->documentPath($this->course), $document);

            $this->transitions->to($this->course, $this->interview->next());
        });
    }

    /**
     * @throws InterviewNotUnderway|CourseNotOpen
     */
    private function assertUnderway(): void
    {
        // Read the row, not the copy this session was built with: the write-up
        // runs in its own session on the queue, so by the time a late answer
        // arrives the status has moved underneath this instance. Until the
        // turns were kept, the emptied transcript hid that (ledger D9, amended
        // 2026-09-19).
        $course = $this->course->fresh();

        if ($course === null || ! $course->isOpen()) {
            throw CourseNotOpen::for($this->course);
        }

        // `hasTutorTurn`, not merely "has turns": a transcript holding only
        // the opening turn is a conversation nobody has had, and both answering
        // it and summarizing it are meaningless. Done in particular must
        // refuse it -- summarizing it would write a WORKING_KNOWLEDGE
        // document made of one line the student never typed, over whatever
        // earlier courses in the subtopic recorded (ledger D7a).
        if ($course->status !== $this->interview->status() || ! $this->transcript->hasTutorTurn()) {
            throw InterviewNotUnderway::for($this->course, $this->interview->kind()->value);
        }

        // Being written up is over as far as the student is concerned: an
        // answer sent now would land in a transcript the write-up has already
        // read, and a second Done would ask for what is already asked for. A
        // write-up that failed reopens the interview (11.4).
        if (GenerationRequest::for($this->course, GenerationKind::InterviewSummary)?->isPending()) {
            throw InterviewNotUnderway::for($this->course, $this->interview->kind()->value);
        }
    }
}
