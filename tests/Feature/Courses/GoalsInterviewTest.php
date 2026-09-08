<?php

namespace Tests\Feature\Courses;

use App\Ai\GatewayAgent;
use App\Courses\CourseStatus;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\Speaker;
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
 * Spec 4.4 -- goals interview -> `criteria.create` -> COURSE_CRITERIA.md.
 *
 * The same machine as 4.3 with different ends, so what is worth testing
 * separately is what differs: it reads the document 4.3 wrote, it writes a
 * level-scoped one of its own, and it hands the course to 4.5.
 */
class GoalsInterviewTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSING_ANSWER = 'I want to order dinner in Madrid in October without switching to English.';

    private const CRITERIA_PATH = 'math/algebra/101/COURSE_CRITERIA.md';

    private const WK_DOC = "## Solid\n\n- Reads an equation aloud correctly.\n\n## Shaky\n\n- Signs.\n\n## Gaps\n\n- Quadratics.";

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function the_interview_writes_the_criteria_document_and_hands_the_course_to_the_syllabus(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->interview($course);

        $document = (new MemoryTree)->get($course->student, self::CRITERIA_PATH);

        $this->assertNotNull($document, 'The interview exists to produce this document.');

        foreach (['## Goals', '## Interests', '## Success', '## Evaluation style'] as $section) {
            $this->assertStringContainsString($section, $document);
        }

        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()?->status);
        $this->assertSame(0, InterviewMessage::query()->count(), 'Spec 4.3: discarded after summarization.');
    }

    #[Test]
    public function the_criteria_document_is_scoped_to_the_level_not_the_subtopic(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $this->interview($course);

        $this->assertSame(
            [self::CRITERIA_PATH, 'math/algebra/WORKING_KNOWLEDGE.md'],
            (new MemoryTree)->list($course->student),
            'What a student wants from a 201 is not what they wanted from the 101 (ledger D8 draws the same line).',
        );
    }

    #[Test]
    public function the_interview_is_told_what_the_knowledge_interview_found(): void
    {
        $this->scriptTutor();

        $this->interview($this->course());

        $this->assertStringContainsString(
            'Reads an equation aloud correctly.',
            $this->instructions(),
            'The brief carries the WK document so the model does not re-ask what 4.3 established.',
        );
    }

    #[Test]
    public function the_student_can_end_the_interview_without_the_model_agreeing(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        $session = $this->interviews()->goals($course);

        $this->drain($session->open());

        $session->doneButton()->press();

        $this->assertNotNull((new MemoryTree)->get($course->student, self::CRITERIA_PATH));
        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()?->status);
    }

    #[Test]
    public function every_call_the_interview_makes_is_logged_against_the_feature(): void
    {
        $this->scriptTutor();

        $this->interview($this->course());

        $events = UsageEvent::query()->get();

        foreach ($events as $event) {
            $this->assertSame('course.goals_interview', $event->feature);
        }

        $this->assertSame(
            ['interview.goals', 'interview.goals', 'criteria.create'],
            $events->pluck('prompt_key')->all(),
        );
    }

    #[Test]
    public function it_refuses_to_interview_without_the_document_the_first_interview_owes_it(): void
    {
        $this->scriptTutor();

        $course = Course::factory()->status(CourseStatus::GoalsInterview)->create();

        $this->expectException(MissingCourseDocument::class);

        $this->interviews()->goals($course)->open();
    }

    #[Test]
    public function it_neither_reads_nor_deletes_the_other_interview_s_turns(): void
    {
        $this->scriptTutor();
        $course = $this->course();

        // What a failed 4.3 summarization would leave behind.
        InterviewMessage::query()->create([
            'course_id' => $course->id,
            'kind' => InterviewKind::Knowledge,
            'speaker' => Speaker::Student,
            'content' => 'A stray line from the knowledge interview.',
            'position' => 1,
            'created_at' => now(),
        ]);

        $this->interview($course);

        $this->assertStringNotContainsString(
            'A stray line from the knowledge interview.',
            $this->summaryPrompt(),
            'A transcript is scoped to one interview; the other one is not its business.',
        );

        $this->assertSame(
            1,
            InterviewMessage::query()->where('kind', InterviewKind::Knowledge->value)->count(),
            'Discarding one interview must not discard the other.',
        );
    }

    /**
     * Spec 4.4.1 -- four questions, then the tutor is asked to wrap up.
     *
     * A budget, not a cap: what the fourth answer changes is the brief, and
     * the tutor ends the interview itself on the turn after it.
     */
    #[Test]
    public function the_interview_asks_four_questions_and_then_wraps_up(): void
    {
        $this->scriptTutorThatWrapsUpWhenAsked();
        $course = $this->course();

        $session = $this->interviews()->goals($course);
        $this->drain($session->open());

        foreach (range(1, 3) as $n) {
            $this->drain($session->answer("Answer {$n}."));

            $this->assertSame(
                CourseStatus::GoalsInterview,
                $course->fresh()?->status,
                "The budget was spent at question {$n}, and it should not have been.",
            );
        }

        $this->assertSame(4, InterviewMessage::query()->where('speaker', Speaker::Tutor->value)->count());

        $this->drain($session->answer('Answer 4.'));

        $this->assertSame(
            4,
            substr_count($this->summaryPrompt(), 'Question '),
            'Spec 4.4.1: the transcript `criteria.create` reads holds four questions.',
        );

        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()?->status);
    }

    /**
     * The other half of 4.4.1's done-condition: four questions still fill all
     * four sections, because they are the four the criteria document wants.
     */
    #[Test]
    public function four_questions_still_produce_all_four_criteria_sections(): void
    {
        $this->scriptTutorThatWrapsUpWhenAsked();
        $course = $this->course();

        $session = $this->interviews()->goals($course);
        $this->drain($session->open());

        foreach (range(1, 4) as $n) {
            $this->drain($session->answer("Answer {$n}."));
        }

        $document = (string) (new MemoryTree)->get($course->student, self::CRITERIA_PATH);

        foreach (['## Goals', '## Interests', '## Success', '## Evaluation style'] as $section) {
            $this->assertStringContainsString($section, $document);
        }
    }

    /**
     * The first two are fixed by 4.4.1, and the brief is where that is said.
     */
    #[Test]
    public function the_brief_fixes_the_first_two_questions(): void
    {
        $this->scriptTutor();

        $this->interview($this->course());

        $instructions = $this->instructions();

        $this->assertStringContainsString('1. What they hope to gain', $instructions);
        $this->assertStringContainsString('2. What drew them to the subject', $instructions);
    }

    /**
     * A tutor that asks its four and ends the interview when the spent budget
     * asks it to, which is what a real one does. That an interview survives a
     * tutor which ignores the request is KnowledgeInterviewTest's business.
     */
    private function scriptTutorThatWrapsUpWhenAsked(): void
    {
        $this->sent = [];
        $asked = 0;

        GatewayAgent::fake(function (string $prompt) use (&$asked): ToolCall|string {
            $this->sent[] = $prompt;

            if (str_contains($prompt, 'criteria document')) {
                return "## Goals\n\n- Order dinner unaided.\n\n## Interests\n\n- Food.\n\n## Success\n\n- The Madrid trip.\n\n## Evaluation style\n\n- Correct me as I go.";
            }

            $asked++;

            return $asked > 4
                ? new ToolCall('call-1', 'signal_complete', ['reason' => 'I have all four.'])
                : 'Question '.$asked.'?';
        });
    }

    /**
     * The rendered `criteria.create` prompt from the run that just finished.
     */
    private function summaryPrompt(): string
    {
        foreach (array_reverse($this->sent) as $prompt) {
            if (str_contains($prompt, 'criteria document')) {
                return $prompt;
            }
        }

        $this->fail('No summarization call was made.');
    }

    private function interview(Course $course): void
    {
        $session = $this->interviews()->goals($course);

        $this->drain($session->open());
        $this->drain($session->answer(self::CLOSING_ANSWER));
    }

    /**
     * A course standing where 4.3 leaves it: WK written, goals interview next.
     */
    private function course(): Course
    {
        $course = Course::factory()
            ->for(User::factory(), 'student')
            ->status(CourseStatus::KnowledgeInterview)
            ->create();

        (new MemoryTree)->put($course->student, $course->workingKnowledgePath(), self::WK_DOC);

        return $course;
    }

    private function scriptTutor(): void
    {
        $this->sent = [];
        $signalled = false;

        GatewayAgent::fake(function (string $prompt) use (&$signalled): ToolCall|string {
            $this->sent[] = $prompt;

            if (str_contains($prompt, 'criteria document')) {
                return "## Goals\n\n- Order dinner unaided.\n\n## Interests\n\n- Food.\n\n## Success\n\n- The Madrid trip in October.\n\n## Evaluation style\n\n- Correct me as I go.";
            }

            if (! $signalled && str_contains($prompt, self::CLOSING_ANSWER)) {
                $signalled = true;

                return new ToolCall('call-1', 'signal_complete', ['reason' => 'I have their goals and their finish line.']);
            }

            return $signalled ? 'Perfect -- that gives me what I need.' : 'What brought you here?';
        });
    }

    /**
     * The system prompt the conversation ran under.
     */
    private function instructions(): string
    {
        $instructions = '';

        GatewayAgent::assertPrompted(function ($call) use (&$instructions): bool {
            $instructions = $call->agent->instructions();

            return str_contains($instructions, 'You are interviewing a student to find out what they want');
        });

        return $instructions;
    }

    private function interviews(): Interviews
    {
        return app(Interviews::class);
    }

    private function drain(StreamableAgentResponse $stream): void
    {
        foreach ($stream as $event) {
            // The `then` callbacks only run once the stream is consumed.
        }
    }
}
