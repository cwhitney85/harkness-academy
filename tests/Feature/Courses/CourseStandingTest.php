<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ledger D7d -- one status vocabulary, two of whose values are derived.
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
            if ($status->isPipelinePosition()) {
                yield $status->value => [$status];
            }
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

        $this->assertSame(CourseStatus::Withdrawn, $course->standing());
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

        $this->assertSame(CourseStatus::SyllabusRevision, $course->standing());
    }

    #[Test]
    public function a_passed_course_stands_completed_not_at_the_exam(): void
    {
        $course = Course::factory()->completed()->create();

        $this->assertSame(CourseStatus::Completed, $course->standing());
        $this->assertSame(CourseStatus::FinalExam, $course->status);
    }

    #[Test]
    public function exactly_two_statuses_are_derived_rather_than_stored(): void
    {
        $derived = array_values(array_map(
            fn (CourseStatus $status): string => $status->value,
            array_filter(CourseStatus::cases(), fn (CourseStatus $status): bool => $status->hasEnded()),
        ));

        $this->assertSame(['withdrawn', 'completed'], $derived);
    }
}
