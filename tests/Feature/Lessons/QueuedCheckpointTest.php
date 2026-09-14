<?php

namespace Tests\Feature\Lessons;

use App\Courses\GenerationKind;
use App\Courses\LessonStatus;
use App\Lessons\Checkpoints\WriteCheckpoint;
use App\Lessons\LessonState;
use App\Models\GenerationRequest;
use App\Models\Quiz;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Spec 11.1–11.3 and 11.6 -- the checkpoint leaves the section stream.
 *
 * What moved is where the waiting happens. `CheckpointTest` still owns what a
 * checkpoint is and how it fails open; this owns that the stream no longer
 * waits for one, that the screen is told one is coming for only as long as it
 * could be, and that a stopped queue costs the lesson nothing but its
 * checkpoints.
 */
class QueuedCheckpointTest extends SectionStreamTestCase
{
    /**
     * 11.1's done-condition: no `checkpoint.generate` shares a request with
     * the `lesson.section` that preceded it.
     */
    #[Test]
    public function the_section_stream_asks_the_queue_and_ends(): void
    {
        Queue::fake();

        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame(['turn.start', 'turn.delta', 'turn.end'], $this->eventShapeIn($frames));
        $this->assertSame(['lesson.section'], UsageEvent::query()->pluck('prompt_key')->all());

        Queue::assertPushed(
            WriteCheckpoint::class,
            fn (WriteCheckpoint $job): bool => $job->lessonId === $this->lesson->id && $job->sectionIndex === 1,
        );
        $this->assertNotNull(GenerationRequest::for($this->lesson->course, GenerationKind::Checkpoint));
    }

    #[Test]
    public function a_reconnect_while_the_checkpoint_is_pending_asks_for_nothing_more(): void
    {
        Queue::fake();

        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $first = [];
        $this->teach(1, $first);
        $second = [];
        $this->teach(1, $second);

        Queue::assertPushed(WriteCheckpoint::class, 1);
    }

    /**
     * 11.6 and 11.3 together: the section is marked as being written while
     * the request is pending, and stops being marked once its wait runs out.
     */
    #[Test]
    public function the_screen_is_told_a_checkpoint_is_coming_only_while_it_could_be(): void
    {
        Queue::fake();

        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame([true, false], $this->writing());

        $this->travel(GenerationKind::Checkpoint->patience() + 1)->seconds();

        $this->assertSame([false, false], $this->writing());
    }

    #[Test]
    public function a_written_checkpoint_ends_the_wait(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame(1, Quiz::query()->count());
        $this->assertSame(0, GenerationRequest::query()->count());
        $this->assertSame([false, false], $this->writing());
    }

    #[Test]
    public function a_job_killed_before_it_finished_still_ends_the_wait(): void
    {
        Queue::fake();

        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $frames = [];
        $this->teach(1, $frames);

        (new WriteCheckpoint($this->lesson->id, 1))->failed(new RuntimeException('worker reclaimed'));

        $this->assertSame(0, GenerationRequest::query()->count());
        $this->assertSame([false, false], $this->writing());
    }

    /**
     * Try again (11.3): refused while the wait is still running, asks again
     * once it has run out.
     */
    #[Test]
    public function try_again_asks_once_the_wait_has_run_out(): void
    {
        Queue::fake();

        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $frames = [];
        $this->teach(1, $frames);

        $url = route('lessons.sections.checkpoint.store', [$this->lesson, 1]);
        $student = $this->lesson->course->student;

        $this->actingAs($student)->postJson($url)->assertNoContent();
        Queue::assertPushed(WriteCheckpoint::class, 1);

        $this->travel(GenerationKind::Checkpoint->patience() + 1)->seconds();

        $this->actingAs($student)->postJson($url)->assertNoContent();
        Queue::assertPushed(WriteCheckpoint::class, 2);
        $this->assertSame([true, false], $this->writing());
    }

    #[Test]
    public function try_again_is_refused_for_a_section_not_yet_taught(): void
    {
        Queue::fake();

        $this->actingAs($this->lesson->course->student)
            ->postJson(route('lessons.sections.checkpoint.store', [$this->lesson, 1]))
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    /**
     * 11.3's done-condition: with the queue stopped, the lesson is completable
     * end to end.
     */
    #[Test]
    public function a_stopped_queue_costs_the_lesson_only_its_checkpoints(): void
    {
        Queue::fake();

        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            'An expression puts variables and numbers together.',
        ]);

        $first = [];
        $this->teach(1, $first);
        $second = [];
        $this->teach(2, $second);

        $this->actingAs($this->lesson->course->student)
            ->postJson(route('lessons.complete', $this->lesson))
            ->assertSuccessful();

        $this->assertSame(LessonStatus::Completed, $this->lesson->fresh()->status);
        $this->assertSame(0, Quiz::query()->count());
    }

    /**
     * @return list<bool>
     */
    private function writing(): array
    {
        return array_column(LessonState::of($this->lesson->fresh())->toArray()['sections'], 'checkpoint_writing');
    }
}
