<?php

namespace Tests\Feature\Courses;

use App\Ai\GatewayAgent;
use App\Courses\CourseStatus;
use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Speaker;
use App\Courses\Interviews\Transcript;
use App\Courses\Interviews\WriteUpInterview;
use App\Models\Course;
use App\Models\GenerationRequest;
use App\Models\InterviewMessage;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec 11.4–11.6 -- the interview write-up leaves the request.
 *
 * `KnowledgeInterviewTest` still owns what the write-up produces and that it
 * fails closed. This owns where it runs: that the answer ending an interview
 * returns having asked the queue, that the screen says what is being written
 * and takes nothing more, and that the course moves afterwards with no second
 * answer posted.
 */
class QueuedInterviewWriteUpTest extends TestCase
{
    use RefreshDatabase;

    private const LAST_ANSWER = 'That is everything I know.';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    /**
     * 11.4's done-condition: the answer request returns within its stream,
     * and the transition is observable afterwards without a second answer.
     */
    #[Test]
    public function the_answer_that_ends_the_interview_asks_the_queue_and_returns(): void
    {
        Queue::fake();
        $course = $this->underway();

        $wire = $this->actingAs($course->student)
            ->post(route('courses.knowledge-interview.answers.store', $course), ['text' => self::LAST_ANSWER])
            ->assertOk()
            ->streamedContent();

        // The tutor's last turn is still a turn: its row outlives the stream,
        // because nothing discards the transcript inside the request now.
        $this->assertStringContainsString('event: turn.end', $wire);
        $this->assertSame(0, UsageEvent::query()->where('prompt_key', 'wk.create')->count());
        $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);

        Queue::assertPushed(WriteUpInterview::class, fn (WriteUpInterview $job): bool => $job->courseId === $course->id
            && $job->kind === InterviewKind::Knowledge);

        $this->assertSame([true, null], $this->writeUp($course));

        app()->call([new WriteUpInterview($course->id, InterviewKind::Knowledge), 'handle']);

        $this->assertSame(CourseStatus::GoalsInterview, $course->fresh()?->status);
        $this->assertSame(0, GenerationRequest::query()->count());
    }

    /**
     * 11.6 and 8.12.3: the control that started it cannot start it twice, and
     * an answer sent meanwhile lands nowhere.
     */
    #[Test]
    public function while_it_is_being_written_up_done_and_answers_ask_for_nothing(): void
    {
        Queue::fake();
        $course = $this->underway();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($course->student)
                ->post(route('courses.knowledge-interview.complete', $course))
                ->assertRedirect(route('courses.knowledge-interview.show', $course));
        }

        Queue::assertPushed(WriteUpInterview::class, 1);

        $this->actingAs($course->student)
            ->post(route('courses.knowledge-interview.answers.store', $course), ['text' => 'One more thing.'])
            ->assertRedirect(route('courses.knowledge-interview.show', $course));

        $this->assertSame(0, InterviewMessage::query()->where('content', 'One more thing.')->count());
    }

    #[Test]
    public function a_failed_write_up_reaches_the_screen_and_done_asks_again(): void
    {
        Queue::fake();
        $course = $this->underway(document: '');

        $this->actingAs($course->student)->post(route('courses.knowledge-interview.complete', $course));
        app()->call([new WriteUpInterview($course->id, InterviewKind::Knowledge), 'handle']);

        [$writing, $failure] = $this->writeUp($course);

        $this->assertFalse($writing);
        $this->assertStringContainsString('press Done', (string) $failure);

        $this->actingAs($course->student)->post(route('courses.knowledge-interview.complete', $course));

        Queue::assertPushed(WriteUpInterview::class, 2);
        $this->assertSame([true, null], $this->writeUp($course));
    }

    #[Test]
    public function a_job_killed_before_it_could_report_still_ends_the_wait(): void
    {
        Queue::fake();
        $course = $this->underway();

        $this->actingAs($course->student)->post(route('courses.knowledge-interview.complete', $course));
        (new WriteUpInterview($course->id, InterviewKind::Knowledge))->failed(new RuntimeException('worker reclaimed'));

        [$writing, $failure] = $this->writeUp($course);

        $this->assertFalse($writing);
        $this->assertStringContainsString('press Done', (string) $failure);
    }

    /**
     * A knowledge interview one question in, with a tutor that ends it on
     * `LAST_ANSWER` and writes `$document` when asked to write it up.
     */
    private function underway(?string $document = null): Course
    {
        $signalled = false;

        GatewayAgent::fake(function (string $prompt) use (&$signalled, $document): ToolCall|string {
            if (str_contains($prompt, 'working-knowledge document')) {
                return $document ?? "## Solid\n\n- Knows it.\n\n## Shaky\n\n- Little.\n\n## Gaps\n\n- The rest.";
            }

            if (! $signalled && str_contains($prompt, self::LAST_ANSWER)) {
                $signalled = true;

                return new ToolCall('call-1', 'signal_complete', ['reason' => 'Enough.']);
            }

            return 'Thanks -- that is everything I needed.';
        });

        $course = Course::factory()->for(User::factory(), 'student')->status(CourseStatus::KnowledgeInterview)->create();

        (new Transcript($course, InterviewKind::Knowledge))->append(Speaker::Tutor, 'What can you do already?');

        return $course;
    }

    /**
     * What S3 is told about the write-up: whether it is pending, and why it failed.
     *
     * @return array{0: bool, 1: string|null}
     */
    private function writeUp(Course $course): array
    {
        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.knowledge-interview.show', $course)),
        );

        return [$props['writing_up'], $props['write_up_failure']];
    }
}
