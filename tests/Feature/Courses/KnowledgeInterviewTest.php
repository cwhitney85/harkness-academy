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

        $this->assertCount(2, $turns);
        $this->assertSame(Speaker::Student, $turns[0]->speaker);
        $this->assertSame(InterviewKind::Knowledge, $turns[0]->kind);
        $this->assertSame(Speaker::Tutor, $turns[1]->speaker);
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
