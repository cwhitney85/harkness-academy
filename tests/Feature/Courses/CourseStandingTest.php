<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStanding;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ledger D7d -- the complete course vocabulary, derived rather than stored.
 */
class CourseStandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('pipelineStatuses')]
    public function an_open_course_stands_where_the_pipeline_says(CourseStatus $status): void
    {
        $course = Course::factory()->status($status)->create();

        $this->assertSame($status->value, $course->standing()->value);
        $this->assertFalse($course->standing()->hasEnded());
    }

    /**
     * @return iterable<string, array{CourseStatus}>
     */
    public static function pipelineStatuses(): iterable
    {
        foreach (CourseStatus::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    /**
     * The reason this is derived and not a column: the pipeline position has
     * to survive underneath, or resume has nowhere to put the student back.
     */
    #[Test]
    public function withdrawal_overrides_the_pipeline_position_without_erasing_it(): void
    {
        $course = Course::factory()->status(CourseStatus::SyllabusRevision)->withdrawn()->create();

        $this->assertSame(CourseStanding::Withdrawn, $course->standing());
        $this->assertTrue($course->standing()->hasEnded());
        $this->assertSame(
            CourseStatus::SyllabusRevision,
            $course->status,
            'the position resume returns to is still there underneath',
        );
    }

    #[Test]
    public function a_resumed_course_stands_where_it_left_off(): void
    {
        $course = Course::factory()->status(CourseStatus::SyllabusRevision)->withdrawn()->create();

        app(CourseTransitions::class)->resume($course);

        $this->assertSame(CourseStanding::SyllabusRevision, $course->standing());
    }

    #[Test]
    public function a_passed_course_stands_completed_not_at_the_exam(): void
    {
        $course = Course::factory()->completed()->create();

        $this->assertSame(CourseStanding::Completed, $course->standing());
        $this->assertSame(CourseStatus::FinalExam, $course->status);
    }

    /**
     * `CourseStanding::for()` resolves a pipeline status by value, so a status
     * with no matching standing would throw at runtime rather than here.
     */
    #[Test]
    public function every_pipeline_status_has_a_standing_of_the_same_name(): void
    {
        foreach (CourseStatus::cases() as $status) {
            $this->assertNotNull(
                CourseStanding::tryFrom($status->value),
                "CourseStanding is missing a case for the status [{$status->value}]",
            );
        }
    }

    #[Test]
    public function the_only_standings_beyond_the_pipeline_are_the_two_endings(): void
    {
        $pipeline = array_map(fn (CourseStatus $s): string => $s->value, CourseStatus::cases());
        $extra = array_values(array_diff(
            array_map(fn (CourseStanding $s): string => $s->value, CourseStanding::cases()),
            $pipeline,
        ));

        $this->assertSame(['withdrawn', 'completed'], $extra);
    }
}
