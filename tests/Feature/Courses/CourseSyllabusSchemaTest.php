<?php

namespace Tests\Feature\Courses;

use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.4.1 -- what the syllabus table refuses to hold (ledger D10.1).
 */
class CourseSyllabusSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_course_cannot_hold_two_rows_at_the_same_generation(): void
    {
        $course = Course::factory()->create();

        CourseSyllabus::factory()->for($course)->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('course_syllabi_one_row_per_generation');

        CourseSyllabus::factory()->for($course)->create();
    }

    #[Test]
    public function a_course_cannot_have_two_approved_syllabi(): void
    {
        $course = Course::factory()->create();

        CourseSyllabus::factory()->for($course)->generation(1)->approved()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('course_syllabi_one_approved_per_course');

        CourseSyllabus::factory()->for($course)->generation(2)->approved()->create();
    }

    #[Test]
    public function unapproved_generations_stack_up_freely(): void
    {
        $course = Course::factory()->create();

        foreach ([1, 2, 3, 4] as $generation) {
            CourseSyllabus::factory()->for($course)->generation($generation)->create();
        }

        // The cap is policy, enforced by the service, not by the schema
        // (ledger D7c: no policy literals in database rules).
        $this->assertSame(4, $course->syllabi()->count());
    }

    #[Test]
    public function a_generation_pins_the_documents_it_was_built_from(): void
    {
        $syllabus = CourseSyllabus::factory()->create();

        $this->assertGreaterThan(0, $syllabus->wk_version_id);
        $this->assertGreaterThan(0, $syllabus->criteria_version_id);
        $this->assertNotSame($syllabus->wk_version_id, $syllabus->criteria_version_id);
    }

    #[Test]
    public function the_first_generation_has_no_feedback_behind_it(): void
    {
        $course = Course::factory()->create();

        $first = CourseSyllabus::factory()->for($course)->generation(1)->create();
        $second = CourseSyllabus::factory()->for($course)->generation(2)->create();

        $this->assertNull($first->feedback);
        $this->assertFalse($first->isRevision());

        $this->assertNotNull($second->feedback);
        $this->assertTrue($second->isRevision());
    }

    #[Test]
    public function a_course_finds_its_latest_and_its_approved_syllabus(): void
    {
        $course = Course::factory()->create();

        CourseSyllabus::factory()->for($course)->generation(1)->approved()->create();
        $latest = CourseSyllabus::factory()->for($course)->generation(2)->create();

        $this->assertSame($latest->id, $course->latestSyllabus()->first()->id);
        $this->assertSame(1, $course->approvedSyllabus()->first()->generation);
    }

    #[Test]
    public function the_syllabus_json_round_trips_as_an_array(): void
    {
        $syllabus = CourseSyllabus::factory()->create()->refresh();

        $this->assertIsArray($syllabus->syllabus_json);
        $this->assertSame('Letters as Numbers', $syllabus->syllabus_json['lessons'][0]['title']);
        $this->assertNull($syllabus->syllabus_json['subtopic_mismatch']);
    }
}
