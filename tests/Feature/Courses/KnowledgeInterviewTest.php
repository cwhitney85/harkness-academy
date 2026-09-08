<?php

namespace Tests\Feature\Courses;

use App\Ai\GatewayAgent;
use App\Courses\CourseStatus;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Exceptions\InterviewSummaryUnusable;
use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\KnowledgeInterview;
use App\Courses\Interviews\Speaker;
use App\Courses\SkillLevel;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\InterviewMessage;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StreamableAgentResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.3 -- knowledge interview -> `wk.create` -> WORKING_KNOWLEDGE.md.
 *
 * The done-condition has two halves. The first is that one run produces a
 * document with the three sections. The second is the regression ledger D7a
 * asked 4.3 to carry: a 101 course's claims survive the 201 course's
 * interview, because the accumulated document goes back in as `prior_wk_doc`.
 */
class KnowledgeInterviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The student turn the scripted tutor decides it has heard enough after.
     */
    private const CLOSING_ANSWER = 'You add the same thing to both sides.';

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function opening_the_interview_moves_the_course_and_asks_the_first_question(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->drain($this->interviews()->knowledge($course)->open());

        $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);

        $turns = InterviewMessage::query()->orderBy('position')->get();

        $this->assertCount(1, $turns, 'Spec 4.6: the opener is on the wire, not in the transcript.');
        $this->assertSame(Speaker::Tutor, $turns[0]->speaker);
        $this->assertSame(InterviewKind::Knowledge, $turns[0]->kind);
    }

    /**
     * Spec 4.6. The provider will not take an assistant message first and the
     * gateway will not take an empty user turn, so the opener has to exist --
     * but nobody said it, so nobody is shown or told that they did.
     */
    #[Test]
    public function the_opener_reaches_the_model_and_nothing_else(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->interview($course);

        $this->assertStringNotContainsString(
            'I am ready to start.',
            $this->summaryPrompt(),
            'The summarizer is handed a conversation, not a button press.',
        );

        GatewayAgent::assertPrompted(function ($call): bool {
            $messages = $call->agent->messages();

            return $messages !== []
                && $messages[0] instanceof UserMessage
                && (string) $messages[0]->content === 'I am ready to start.';
        });
    }

    #[Test]
    public function the_interview_writes_the_subtopic_document_and_moves_the_course_on(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->interview($course);

        $document = (new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md');

        $this->assertNotNull($document, 'The interview exists to produce this document.');
        $this->assertStringContainsString('## Solid', $document);
        $this->assertStringContainsString('## Shaky', $document);
        $this->assertStringContainsString('## Gaps', $document);

        $this->assertSame(CourseStatus::GoalsInterview, $course->fresh()?->status);
    }

    #[Test]
    public function the_transcript_does_not_outlive_the_document(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->interview($course);

        $this->assertSame(0, InterviewMessage::query()->count(), 'Spec 4.3: discarded after summarization.');
    }

    #[Test]
    public function every_call_the_interview_makes_is_logged_against_the_feature(): void
    {
        $this->scriptTutor();

        $this->interview($this->course());

        $events = UsageEvent::query()->get();

        $this->assertCount(3, $events, 'Two conversation turns and one summarization.');

        foreach ($events as $event) {
            $this->assertSame('course.knowledge_interview', $event->feature);
        }

        $this->assertSame(
            ['interview.knowledge', 'interview.knowledge', 'wk.create'],
            $events->pluck('prompt_key')->all(),
        );
    }

    #[Test]
    public function the_student_can_end_the_interview_without_the_model_agreeing(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $session = $this->interviews()->knowledge($course);

        $this->drain($session->open());
        $this->drain($session->answer('Not really, no.'));

        $signal = $session->doneButton()->press();

        $this->assertSame('student', $signal->source->value);
        $this->assertNotNull((new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'));
        $this->assertSame(0, InterviewMessage::query()->count());
        $this->assertSame(CourseStatus::GoalsInterview, $course->fresh()?->status);
    }

    #[Test]
    public function an_interview_that_was_never_opened_has_nothing_to_answer(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $this->expectException(InterviewNotUnderway::class);

        $this->interviews()->knowledge($this->course())->answer('Hello?');
    }

    #[Test]
    public function a_summarized_interview_cannot_be_answered_again(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $session = $this->interviews()->knowledge($course);

        $this->drain($session->open());
        $this->drain($session->answer(self::CLOSING_ANSWER));

        $this->expectException(InterviewNotUnderway::class);

        $session->answer('One more thing.');
    }

    #[Test]
    public function the_first_course_in_a_subtopic_says_so_rather_than_sending_a_blank(): void
    {
        $this->scriptTutor();

        $this->interview($this->course());

        $this->assertStringContainsString(
            KnowledgeInterview::NO_PRIOR_KNOWLEDGE,
            $this->summaryPrompt(),
            'A blank in the middle of a prompt reads as a bug (ledger D7a).',
        );
    }

    /**
     * Ledger D7a's regression test: the accumulated document goes in, so a
     * later course revises it instead of overwriting it.
     */
    #[Test]
    public function a_101_to_201_progression_keeps_the_101_documents_claims(): void
    {
        $claim101 = 'Can solve a one-step linear equation unaided.';
        $claim201 = 'Factors a quadratic when the leading coefficient is 1.';

        $student = User::factory()->create();

        $this->scriptTutor($claim101);
        $introductory = $this->course($student);
        $this->interview($introductory);

        // Passing the final exam is 6.3's job; here it only has to free the
        // subtopic's open slot so the next level can start.
        $introductory->forceFill(['status' => CourseStatus::FinalExam, 'completed_at' => now()])->save();

        $this->scriptTutor($claim201);
        $intermediate = $this->course($student, SkillLevel::Intermediate);
        $this->interview($intermediate);

        $this->assertStringContainsString(
            $claim101,
            $this->summaryPrompt(),
            'The accumulated document has to reach the model for it to revise it.',
        );

        $document = (new MemoryTree)->get($student, 'math/algebra/WORKING_KNOWLEDGE.md');

        $this->assertStringContainsString($claim101, (string) $document, 'The 101 course knew this; nothing unlearned it.');
        $this->assertStringContainsString($claim201, (string) $document);
    }

    #[Test]
    public function an_empty_summary_keeps_the_transcript_and_leaves_the_course_where_it_is(): void
    {
        $this->scriptTutor(document: '');
        $course = $this->course();

        $this->expectException(InterviewSummaryUnusable::class);

        try {
            $this->interview($course);
        } finally {
            $this->assertNull(
                (new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'),
                'An empty document would erase what earlier courses recorded.',
            );
            $this->assertGreaterThan(0, InterviewMessage::query()->count(), 'The student can end the interview again.');
            $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);
        }
    }

    /**
     * Spec 4.3.1 -- the fifth question is where the tutor is asked to wrap up.
     *
     * Not told, asked: the budget changes what the brief wants and nothing
     * else, so the assertion is about the instruction rather than about the
     * course having moved.
     */
    #[Test]
    public function spending_the_question_budget_asks_the_tutor_to_wrap_up(): void
    {
        $this->scriptEndlessTutor();

        $session = $this->interviews()->knowledge($this->course());
        $this->drain($session->open());

        foreach (range(1, 4) as $n) {
            $this->drain($session->answer("Answer {$n}."));
        }

        GatewayAgent::assertNotPrompted($this->askedToWrapUp(...));

        $this->drain($session->answer('Answer 5.'));

        GatewayAgent::assertPrompted($this->askedToWrapUp(...));
    }

    /**
     * Spec 4.3.1 -- and a tutor that carries on anyway is not cut off.
     *
     * The first build ended the interview here regardless, which showed the
     * student a question and then a screen they had not asked for. Running
     * long is the cost chosen over that interruption (Colin, 2026-09-08).
     * The wrap-up keeps being asked for, and Done keeps working.
     */
    #[Test]
    public function a_tutor_that_keeps_asking_is_not_cut_off_mid_interview(): void
    {
        $this->scriptEndlessTutor();
        $course = $this->course();

        $session = $this->interviews()->knowledge($course);
        $this->drain($session->open());

        foreach (range(1, 7) as $n) {
            $this->drain($session->answer("Answer {$n}."));
        }

        $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);
        $this->assertSame(8, InterviewMessage::query()->where('speaker', Speaker::Tutor->value)->count());
        $this->assertNull((new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'));

        // Every turn past the budget asks again, rather than asking once and
        // letting the request sink under the transcript.
        GatewayAgent::assertPromptedTimes(8);
        $this->assertAskedToWrapUp(times: 3);

        $session->doneButton()->press();

        $this->assertSame(CourseStatus::GoalsInterview, $course->fresh()?->status);
    }

    /**
     * Whether a call's brief carried the wrap-up instruction.
     */
    private function askedToWrapUp(mixed $call): bool
    {
        return str_contains($call->agent->instructions(), 'You have asked enough questions. Wrap up now');
    }

    /**
     * That at least this many conversation turns ran under it.
     *
     * The fake's `contains` stops at the first closure that answers true, so
     * counting is done by not answering true until the count is reached.
     */
    private function assertAskedToWrapUp(int $times): void
    {
        $asked = 0;

        GatewayAgent::assertPrompted(function (mixed $call) use (&$asked, $times): bool {
            $asked += $this->askedToWrapUp($call) ? 1 : 0;

            return $asked === $times;
        });
    }

    /**
     * Spec 4.3.1 -- saying "let's stop" does not stop it.
     *
     * The Done button is the student's door (R3) and the only one they have.
     * What the tutor says back to those words is the brief's business -- it
     * points at the button -- but no wording of an answer ends an interview,
     * which is why this asserts on the course rather than on the reply.
     */
    #[Test]
    public function a_student_asking_to_stop_does_not_end_the_interview_by_asking(): void
    {
        $this->scriptEndlessTutor();
        $course = $this->course();

        $session = $this->interviews()->knowledge($course);
        $this->drain($session->open());
        $this->drain($session->answer('Answer 1.'));

        $this->drain($session->answer('Honestly, I have had enough of this. Can we stop?'));

        $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);
        $this->assertNull((new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'));
        $this->assertSame(3, InterviewMessage::query()->where('speaker', Speaker::Tutor->value)->count());
    }

    /**
     * Run one full interview: open, one answer, the model ends it.
     */
    private function interview(Course $course): void
    {
        $session = $this->interviews()->knowledge($course);

        $this->drain($session->open());
        $this->drain($session->answer(self::CLOSING_ANSWER));
    }

    /**
     * A tutor that asks one question, ends the interview on the next answer,
     * and writes a document that carries forward whatever it was handed.
     */
    private function scriptTutor(string $claim = 'Reads an equation aloud correctly.', ?string $document = null): void
    {
        $this->sent = [];
        $signalled = false;

        GatewayAgent::fake(function (string $prompt) use (&$signalled, $claim, $document): ToolCall|string {
            $this->sent[] = $prompt;

            if (str_contains($prompt, 'working-knowledge document')) {
                return $document ?? $this->revise($prompt, $claim);
            }

            if (! $signalled && str_contains($prompt, self::CLOSING_ANSWER)) {
                $signalled = true;

                return new ToolCall('call-1', 'signal_complete', ['reason' => 'I can write the three sections.']);
            }

            return $signalled
                ? 'Thanks -- that is everything I needed.'
                : 'How would you undo a plus three?';
        });
    }

    /**
     * A tutor that would happily interview forever: it never calls
     * `signal_complete` and answers "can we stop?" by asking again. What the
     * cap has to stop is exactly this.
     */
    private function scriptEndlessTutor(): void
    {
        $this->sent = [];
        $asked = 0;

        GatewayAgent::fake(function (string $prompt) use (&$asked): string {
            $this->sent[] = $prompt;

            if (str_contains($prompt, 'working-knowledge document')) {
                return "## Solid\n\n- Something.\n\n## Shaky\n\n- Something else.\n\n## Gaps\n\n- The rest.";
            }

            $asked++;

            return str_contains($prompt, 'had enough')
                ? 'There is very little left — question '.$asked.'?'
                : 'Question '.$asked.'?';
        });
    }

    /**
     * Stand in for the model revising the document it was handed: keep every
     * prior claim, add the new one.
     */
    private function revise(string $prompt, string $claim): string
    {
        $carried = [];

        foreach (explode("\n", $prompt) as $line) {
            if (str_starts_with($line, '- ') && ! str_contains($line, KnowledgeInterview::NO_PRIOR_KNOWLEDGE)) {
                $carried[] = $line;
            }
        }

        $carried[] = '- '.$claim;

        return "## Solid\n\n".implode("\n", array_unique($carried))."\n\n## Shaky\n\n- Nothing yet.\n\n## Gaps\n\n- The rest of the subtopic.";
    }

    /**
     * The rendered `wk.create` prompt from the run that just finished.
     */
    private function summaryPrompt(): string
    {
        foreach (array_reverse($this->sent) as $prompt) {
            if (str_contains($prompt, 'working-knowledge document')) {
                return $prompt;
            }
        }

        $this->fail('No summarization call was made.');
    }

    private function course(?User $student = null, ?SkillLevel $level = null): Course
    {
        return Course::factory()
            ->for($student ?? User::factory(), 'student')
            ->state(['skill_level' => $level ?? SkillLevel::Introductory])
            ->create();
    }

    private function interviews(): Interviews
    {
        return app(Interviews::class);
    }

    private function drain(StreamableAgentResponse $stream): void
    {
        foreach ($stream as $event) {
            // The `then` callbacks -- the transcript append, the usage row,
            // the summarization -- only run once the stream is consumed.
        }
    }
}
