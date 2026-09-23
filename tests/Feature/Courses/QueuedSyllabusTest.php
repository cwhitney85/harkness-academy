<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\GenerationKind;
use App\Courses\GenerationState;
use App\Courses\Syllabus\DrawUpSyllabus;
use App\Courses\Syllabus\SyllabusGenerator;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\GenerationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ledger D25 -- syllabus drafting moved onto the queue.
 *
 * What is checked here is what the move introduced, not the generation: that a
 * press queues exactly one job, that pressing again queues none, that S5 says
 * it is waiting, and that a failure on the queue still reaches the student
 * even though the request that asked for it ended minutes earlier.
 *
 * `SyllabusGenerator`'s own contracts -- fail closed, one retry, nothing
 * written unless the model produced something usable -- did not change and
 * stay tested in `SyllabusGenerationTest`.
 */
class QueuedSyllabusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function asking_queues_one_job_and_the_screen_says_it_is_being_drawn_up(): void
    {
        Queue::fake();

        $course = $this->course();

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.store', $course))
            ->assertRedirect(route('courses.syllabus.show', $course));

        Queue::assertPushed(DrawUpSyllabus::class, 1);

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.syllabus.show', $course)),
        );

        $this->assertTrue($props['generating']);
        $this->assertNull($props['syllabus']);
        $this->assertNull($props['failure']);
    }

    /**
     * The money test. Before D25 every press was its own `top`-tier
     * generation, and a request cut by the edge is exactly what invites
     * pressing again -- which is how this was found in production.
     */
    #[Test]
    public function pressing_again_while_a_generation_is_out_buys_nothing(): void
    {
        Queue::fake();

        $course = $this->course();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($course->student)
                ->post(route('courses.syllabus.store', $course))
                ->assertRedirect(route('courses.syllabus.show', $course));
        }

        Queue::assertPushed(DrawUpSyllabus::class, 1);
        $this->assertSame(1, GenerationRequest::query()->count());
    }

    #[Test]
    public function a_failure_on_the_queue_reaches_the_screen_it_could_not_answer(): void
    {
        $course = $this->course();

        GenerationRequest::open($course, GenerationKind::Syllabus)
            ?->fail('The syllabus could not be drawn up just now. Nothing has changed — ask again.');

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.syllabus.show', $course)),
        );

        // Not still waiting: a failed request is a request that is over.
        $this->assertFalse($props['generating']);
        $this->assertStringContainsString('could not be drawn up', (string) $props['failure']);
    }

    #[Test]
    public function asking_again_after_a_failure_clears_it_and_queues_a_new_job(): void
    {
        Queue::fake();

        $course = $this->course();

        GenerationRequest::open($course, GenerationKind::Syllabus)?->fail('It broke.');

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.store', $course))
            ->assertRedirect(route('courses.syllabus.show', $course));

        Queue::assertPushed(DrawUpSyllabus::class, 1);

        $request = GenerationRequest::for($course, GenerationKind::Syllabus);

        $this->assertNotNull($request);
        $this->assertFalse($request->hasFailed());
        $this->assertNull($request->failure);
    }

    /**
     * A refusal that needs no model is still answered in the request. Waiting
     * two minutes to be told the course already has a syllabus would be a
     * worse answer to a question that was answerable immediately.
     */
    #[Test]
    public function a_refusal_that_needs_no_model_never_reaches_the_queue(): void
    {
        Queue::fake();

        $course = $this->course();
        CourseSyllabus::factory()->for($course)->generation(1)->create();

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.store', $course))
            ->assertRedirect()
            ->assertSessionHas('failure');

        Queue::assertNothingPushed();
        $this->assertSame(0, GenerationRequest::query()->count());
    }

    #[Test]
    public function an_empty_revision_is_refused_in_the_request_as_it_always_was(): void
    {
        Queue::fake();

        $course = $this->course();
        CourseSyllabus::factory()->for($course)->generation(1)->create();

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.revisions.store', $course), ['feedback' => '   '])
            ->assertSessionHasErrors('feedback');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_revision_queues_the_job_carrying_the_feedback(): void
    {
        Queue::fake();

        $course = $this->course();
        CourseSyllabus::factory()->for($course)->generation(1)->create();

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.revisions.store', $course), [
                'feedback' => 'Fewer lessons, more speaking.',
            ])
            ->assertRedirect(route('courses.syllabus.show', $course));

        Queue::assertPushed(
            DrawUpSyllabus::class,
            fn (DrawUpSyllabus $job): bool => $job->feedback === 'Fewer lessons, more speaking.',
        );
    }

    /**
     * Managed queues deliver at least once, so a job may run twice. The second
     * run must not spend a second `top`-tier call, and must not leave the
     * student watching a wait for work that has already landed.
     */
    #[Test]
    public function a_redelivered_job_settles_instead_of_reporting_a_failure(): void
    {
        $course = $this->course();

        CourseSyllabus::factory()->for($course)->generation(1)->create();
        GenerationRequest::open($course, GenerationKind::Syllabus);

        // No model is scripted: a job that reached the gateway here would fail
        // loudly rather than quietly costing money.
        (new DrawUpSyllabus($course->id))->handle(app(SyllabusGenerator::class));

        // Kept once landed (16.2.11), and not read as a wait.
        $this->assertSame(GenerationState::Landed, GenerationRequest::for($course, GenerationKind::Syllabus)?->state);
        $this->assertFalse($this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.syllabus.show', $course)),
        )['generating']);
    }

    /**
     * A job killed by the worker -- a `--timeout` kill, an out-of-memory
     * restart, a Flex worker reclaimed mid-run -- never unwinds through
     * `handle`, so the wait has to be ended by `failed()` instead. Without it
     * the row stays open and the student polls a screen forever.
     */
    #[Test]
    public function a_job_killed_before_it_could_report_still_ends_the_wait(): void
    {
        $course = $this->course();

        GenerationRequest::open($course, GenerationKind::Syllabus);

        (new DrawUpSyllabus($course->id))->failed(new \RuntimeException('killed'));

        $request = GenerationRequest::for($course, GenerationKind::Syllabus);

        $this->assertNotNull($request);
        $this->assertTrue($request->hasFailed());

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get(route('courses.syllabus.show', $course)),
        );

        $this->assertFalse($props['generating']);
        $this->assertStringContainsString('ask again', (string) $props['failure']);
    }

    private function course(): Course
    {
        return Course::factory()
            ->for(User::factory(), 'student')
            ->status(CourseStatus::SyllabusDraft)
            ->create();
    }
}
