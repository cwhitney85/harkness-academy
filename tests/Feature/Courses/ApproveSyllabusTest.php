<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\LessonStatus;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Lesson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.5 -- approval, in one transaction, and 4.5.7's mismatch banner.
 */
class ApproveSyllabusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approval_creates_the_lessons_materialises_their_guidance_and_starts_the_course(): void
    {
        [$course, $syllabus] = $this->draft();

        $this->approve()($course, $syllabus);

        $lessons = $course->lessons()->get();

        $this->assertCount(2, $lessons);
        $this->assertSame(LessonStatus::Available, $lessons[0]->status);
        $this->assertSame(LessonStatus::Locked, $lessons[1]->status);

        $tree = app(MemoryTree::class);

        foreach ($lessons as $lesson) {
            $guidance = $tree->get($course->student, $lesson->guidance_path);

            $this->assertNotNull($guidance, "No guidance at {$lesson->guidance_path}");
            $this->assertStringContainsString('## Objective', $guidance);
        }

        $this->assertSame(
            $course->materialsPrefix().'/01-letters-as-numbers/GUIDANCE.md',
            $lessons[0]->guidance_path,
        );

        $this->assertNotNull($syllabus->fresh()->approved_at);
        $this->assertSame(CourseStatus::Active, $course->fresh()->status);
    }

    #[Test]
    public function every_materialised_file_is_versioned(): void
    {
        [$course, $syllabus] = $this->draft();

        $this->approve()($course, $syllabus);

        $paths = app(MemoryTree::class)->list($course->student, $course->materialsPrefix());

        $this->assertContains($course->materialsPrefix().'/01-letters-as-numbers/GUIDANCE.md', $paths);
        $this->assertContains($course->materialsPrefix().'/02-keeping-both-sides-equal/GUIDANCE.md', $paths);
    }

    #[Test]
    public function a_kill_part_way_through_leaves_nothing_behind(): void
    {
        [$course, $syllabus] = $this->draft();

        // Something already occupies position 2, so the second iteration of
        // the approval loop violates lessons_one_lesson_per_position after the
        // first lesson and its guidance are already written.
        Lesson::factory()->for($course)->position(2)->create();

        $before = app(MemoryTree::class)->list($course->student);

        try {
            $this->approve()($course, $syllabus);

            $this->fail('Expected the approval to blow up mid-transaction.');
        } catch (QueryException) {
            $course->refresh();

            $this->assertSame(1, $course->lessons()->count(), 'Only the pre-existing row should survive.');
            $this->assertSame($before, app(MemoryTree::class)->list($course->student), 'No guidance should have been left behind.');
            $this->assertNull($syllabus->fresh()->approved_at);
            $this->assertSame(CourseStatus::SyllabusDraft, $course->status);
        }
    }

    #[Test]
    public function a_syllabus_can_only_be_approved_once(): void
    {
        [$course, $syllabus] = $this->draft();

        $this->approve()($course, $syllabus);

        $this->expectException(SyllabusNotDraftable::class);

        $this->approve()($course->fresh(), $syllabus->fresh());
    }

    #[Test]
    public function a_syllabus_belonging_to_another_course_is_refused(): void
    {
        [$course] = $this->draft();
        [, $otherSyllabus] = $this->draft();

        $this->expectException(SyllabusNotDraftable::class);

        $this->approve()($course, $otherSyllabus);
    }

    #[Test]
    public function a_mismatched_syllabus_still_approves(): void
    {
        [$course, $syllabus] = $this->draft(mismatched: true);

        // Spec 4.5.7: a flag on the review screen, never a blocker.
        $this->assertSame(
            'The stated goals are about cooking, not algebra.',
            $syllabus->syllabus_json['subtopic_mismatch'],
        );

        $this->approve()($course, $syllabus);

        $this->assertSame(CourseStatus::Active, $course->fresh()->status);
        $this->assertCount(2, $course->lessons()->get());
    }

    /**
     * @return array{0: Course, 1: CourseSyllabus}
     */
    private function draft(bool $mismatched = false): array
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create();

        $factory = CourseSyllabus::factory()->for($course);

        return [$course, $mismatched ? $factory->mismatched()->create() : $factory->create()];
    }

    private function approve(): ApproveSyllabus
    {
        return app(ApproveSyllabus::class);
    }
}
