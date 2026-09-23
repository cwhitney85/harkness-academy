<?php

namespace Tests\Feature\Assessment;

use App\Assessment\WriteFinalExam;
use App\Courses\GenerationKind;
use App\Courses\GenerationState;
use App\Models\GenerationRequest;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

/**
 * Ledger D25 -- writing the final exam moved onto the queue.
 *
 * `QueuedSyllabusTest`'s twin, for the other `top`-tier generation. What is
 * particular here is the fourth screen state: S9 already drew a shut book, an
 * open one and a result, and a book being written is none of those.
 */
class QueuedExamTest extends AssessmentTestCase
{
    #[Test]
    public function opening_queues_the_writing_and_the_book_says_it_is_being_written(): void
    {
        // Faked only after the course is taught: completing a lesson runs
        // package 5's chain, and a faked queue leaves every later lesson
        // locked (5.4.1).
        $course = $this->taughtCourse();

        Queue::fake();

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.store', $course))
            ->assertStatus(202)
            ->assertJson(['state' => 'opening']);

        Queue::assertPushed(WriteFinalExam::class, 1);

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.final-exam.show', $course)),
        );

        $this->assertSame('opening', $props['state']);

        // A book being written ships no questions, for the same reason a shut
        // one does not: there is nothing to hand over yet (D20).
        $this->assertSame([], $props['questions']);
    }

    #[Test]
    public function pressing_again_while_the_exam_is_being_written_buys_nothing(): void
    {
        // Faked only after the course is taught: completing a lesson runs
        // package 5's chain, and a faked queue leaves every later lesson
        // locked (5.4.1).
        $course = $this->taughtCourse();

        Queue::fake();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($course->student)
                ->postJson(route('courses.final-exam.store', $course))
                ->assertStatus(202);
        }

        Queue::assertPushed(WriteFinalExam::class, 1);
        $this->assertSame(1, GenerationRequest::query()->where('kind', GenerationKind::FinalExam)->count());
    }

    #[Test]
    public function a_course_with_lessons_left_is_refused_in_the_request(): void
    {
        Queue::fake();

        $course = $this->activeCourse();

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.store', $course))
            ->assertStatus(422);

        Queue::assertNothingPushed();
        $this->assertSame(0, GenerationRequest::query()->count());
    }

    #[Test]
    public function a_failure_on_the_queue_reaches_the_book_it_could_not_answer(): void
    {
        $course = $this->taughtCourse();

        GenerationRequest::open($course, GenerationKind::FinalExam)
            ?->fail('The exam could not be written. Nothing has changed; try again in a moment.');

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.final-exam.show', $course)),
        );

        // A failed request is over, so the book is shut and askable again.
        $this->assertSame('closed', $props['state']);
        $this->assertStringContainsString('could not be written', (string) $props['failure']);
    }

    /**
     * 16.2.11 -- a request is kept once it is over, so a failure and the
     * retry that landed are both still there, and neither reads as a wait.
     */
    #[Test]
    public function a_failed_then_retried_exam_keeps_every_request_it_made(): void
    {
        $course = $this->taughtCourse();

        Queue::fake();

        $this->actingAs($course->student)->postJson(route('courses.final-exam.store', $course))->assertStatus(202);
        (new WriteFinalExam($course->id))->failed(new \RuntimeException('killed'));

        $this->actingAs($course->student)->postJson(route('courses.final-exam.store', $course))->assertStatus(202);
        $this->scriptModel([$this->examResponse()]);
        app()->call([new WriteFinalExam($course->id), 'handle']);

        $this->assertSame(
            [GenerationState::Failed, GenerationState::Landed],
            GenerationRequest::query()->where('kind', GenerationKind::FinalExam)->orderBy('id')->pluck('state')->all(),
        );

        $props = $this->inertiaProps($this->actingAs($course->student)->get(route('courses.final-exam.show', $course)));

        $this->assertSame('open', $props['state']);
    }

    /** `QueuedSyllabusTest`'s twin: a killed job still ends the wait. */
    #[Test]
    public function a_job_killed_before_it_could_report_still_ends_the_wait(): void
    {
        $course = $this->taughtCourse();

        GenerationRequest::open($course, GenerationKind::FinalExam);

        (new WriteFinalExam($course->id))->failed(new \RuntimeException('killed'));

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.final-exam.show', $course)),
        );

        $this->assertSame('closed', $props['state']);
        $this->assertStringContainsString('could not be written', (string) $props['failure']);
    }
}
