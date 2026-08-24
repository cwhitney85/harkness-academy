<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\Events\CourseTransitioned;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\IllegalTransition;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.2 -- the state machine as a single transition service.
 *
 * The pipeline's workflows (4.3, 4.4, 4.5) are not built yet, so the
 * per-state side effect under test is the event they will hang off: it
 * carries the right pair of statuses, fires once, and never fires for a move
 * the machine refused.
 */
class CourseTransitionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('legalTransitions')]
    public function a_legal_transition_moves_the_course_and_announces_it(CourseStatus $from, CourseStatus $to): void
    {
        Event::fake();
        $course = Course::factory()->status($from)->create();

        $this->transitions()->to($course, $to);

        $this->assertSame($to, $course->fresh()->status);

        Event::assertDispatchedTimes(CourseTransitioned::class, 1);
        Event::assertDispatched(
            CourseTransitioned::class,
            fn (CourseTransitioned $event): bool => $event->course->is($course)
                && $event->from === $from
                && $event->to === $to,
        );
    }

    /**
     * @return iterable<string, array{CourseStatus, CourseStatus}>
     */
    public static function legalTransitions(): iterable
    {
        foreach (CourseStatus::cases() as $from) {
            foreach ($from->successors() as $to) {
                yield "{$from->value} -> {$to->value}" => [$from, $to];
            }
        }
    }

    #[Test]
    #[DataProvider('illegalTransitions')]
    public function an_illegal_transition_throws_and_changes_nothing(CourseStatus $from, CourseStatus $to): void
    {
        Event::fake();
        $course = Course::factory()->status($from)->create();

        try {
            $this->transitions()->to($course, $to);
            $this->fail("[{$from->value} -> {$to->value}] should have been refused");
        } catch (IllegalTransition) {
            // expected
        }

        $this->assertSame($from, $course->fresh()->status);
        Event::assertNotDispatched(CourseTransitioned::class);
    }

    /**
     * Every pair the machine does not declare, including the backwards ones.
     *
     * Terminal starting points are excluded because they are refused one step
     * earlier, by the open-course guard rather than the arrow check -- a
     * sharper diagnosis, covered by its own test below.
     *
     * @return iterable<string, array{CourseStatus, CourseStatus}>
     */
    public static function illegalTransitions(): iterable
    {
        foreach (CourseStatus::cases() as $from) {
            if ($from->isTerminal()) {
                continue;
            }

            foreach (CourseStatus::cases() as $to) {
                if (! $from->canPrecede($to)) {
                    yield "{$from->value} -> {$to->value}" => [$from, $to];
                }
            }
        }
    }

    #[Test]
    public function the_pipeline_runs_end_to_end_through_the_service(): void
    {
        $course = Course::factory()->create();

        $walk = [
            CourseStatus::KnowledgeInterview,
            CourseStatus::GoalsInterview,
            CourseStatus::SyllabusDraft,
            CourseStatus::SyllabusRevision,
            CourseStatus::SyllabusDraft,
            CourseStatus::Active,
            CourseStatus::FinalExam,
            CourseStatus::Completed,
        ];

        foreach ($walk as $next) {
            $this->transitions()->to($course, $next);
        }

        $this->assertSame(CourseStatus::Completed, $course->fresh()->status);
        $this->assertFalse($course->fresh()->isOpen());
    }

    /**
     * Spec 6.3: a failed final exam hands the course back rather than ending it.
     */
    #[Test]
    public function a_failed_final_exam_returns_the_course_to_active(): void
    {
        $course = Course::factory()->status(CourseStatus::FinalExam)->create();

        $this->transitions()->to($course, CourseStatus::Active);

        $this->assertSame(CourseStatus::Active, $course->fresh()->status);
        $this->assertTrue($course->fresh()->isOpen());
    }

    #[Test]
    public function a_withdrawn_course_cannot_be_transitioned_until_it_is_resumed(): void
    {
        Event::fake();
        $course = Course::factory()->withdrawn()->create();

        $this->expectException(CourseNotOpen::class);

        $this->transitions()->to($course, CourseStatus::KnowledgeInterview);
    }

    #[Test]
    public function a_completed_course_cannot_be_transitioned(): void
    {
        $course = Course::factory()->status(CourseStatus::Completed)->create();

        $this->expectException(CourseNotOpen::class);

        $this->transitions()->to($course, CourseStatus::Active);
    }

    private function transitions(): CourseTransitions
    {
        return app(CourseTransitions::class);
    }
}
