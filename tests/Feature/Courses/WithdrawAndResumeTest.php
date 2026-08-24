<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\Events\CourseResumed;
use App\Courses\Events\CourseWithdrawn;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\CourseNotResumable;
use App\Courses\SkillLevel;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ledger D7b -- withdrawal replaces deletion, attempts replace lockout.
 *
 * The rule these all serve: a student can always get out of a subtopic and
 * back into it. D7a's exclusivity index without an exit is a trap.
 */
class WithdrawAndResumeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function withdrawing_keeps_the_row_and_the_status_it_was_left_on(): void
    {
        Event::fake();
        $course = Course::factory()->status(CourseStatus::SyllabusRevision)->create();

        $this->transitions()->withdraw($course);

        $this->assertNotNull($course->fresh()->withdrawn_at);
        $this->assertSame(
            CourseStatus::SyllabusRevision,
            $course->fresh()->status,
            'status records pipeline position; withdrawal is a separate fact (D7b)',
        );
        $this->assertFalse($course->fresh()->isOpen());

        Event::assertDispatched(
            CourseWithdrawn::class,
            fn (CourseWithdrawn $event): bool => $event->at === CourseStatus::SyllabusRevision,
        );
    }

    #[Test]
    public function resuming_puts_the_student_back_exactly_where_they_left_off(): void
    {
        Event::fake();
        $course = Course::factory()->status(CourseStatus::SyllabusRevision)->withdrawn()->create();

        $this->transitions()->resume($course);

        $this->assertNull($course->fresh()->withdrawn_at);
        $this->assertSame(CourseStatus::SyllabusRevision, $course->fresh()->status);
        $this->assertTrue($course->fresh()->isOpen());

        Event::assertDispatched(CourseResumed::class);
    }

    #[Test]
    public function withdrawing_frees_the_subtopic_for_a_fresh_attempt(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $first = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->transitions()->withdraw($first);

        $second = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);

        $this->assertSame(2, $second->attempt);
        $this->assertSame(CourseStatus::Created, $second->status);
    }

    /**
     * The lockout D7a's deletion existed to avoid, now avoided without
     * destroying anything.
     */
    #[Test]
    public function a_completed_course_can_be_taken_again_as_a_later_attempt(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $first = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->complete($first);

        $refresher = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);

        $this->assertSame(2, $refresher->attempt);
        $this->assertSame(SkillLevel::Introductory, $refresher->skill_level);
    }

    #[Test]
    public function withdrawing_does_not_touch_the_students_knowledge_record(): void
    {
        $student = User::factory()->create();
        $course = app(CreateCourse::class)->fromCatalog(
            $student,
            $this->preset('math', 'algebra'),
            SkillLevel::Introductory,
        );

        $tree = app(MemoryTree::class);
        $tree->put($student, $course->workingKnowledgePath(), '# Solid: fractions');

        $this->transitions()->withdraw($course);

        $this->assertSame(
            '# Solid: fractions',
            $tree->get($student, 'math/algebra/WORKING_KNOWLEDGE.md'),
            'the knowledge is still true whatever happened to the course (D7a rider)',
        );
    }

    #[Test]
    public function an_open_course_cannot_be_resumed(): void
    {
        $this->expectException(CourseNotResumable::class);

        $this->transitions()->resume(Course::factory()->create());
    }

    #[Test]
    public function a_withdrawn_course_cannot_be_withdrawn_again(): void
    {
        $this->expectException(CourseNotOpen::class);

        $this->transitions()->withdraw(Course::factory()->withdrawn()->create());
    }

    /**
     * Lesson guidance paths carry no attempt number (4.5.3), so attempt 2 has
     * already overwritten attempt 1's teaching notes. Resuming attempt 1 would
     * show the student the newer material under the older lessons.
     */
    #[Test]
    public function only_the_highest_attempt_at_a_level_can_be_resumed(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $first = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->transitions()->withdraw($first);

        $second = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->transitions()->withdraw($second);

        $offered = $this->transitions()->resumable($student, 'math', 'algebra', SkillLevel::Introductory);

        $this->assertNotNull($offered);
        $this->assertTrue($offered->is($second), 'the newest attempt is the one offered back');
        $this->assertFalse($offered->is($first));

        $this->expectException(CourseNotResumable::class);
        $this->transitions()->resume($first);
    }

    #[Test]
    public function a_withdrawn_course_cannot_be_resumed_while_another_is_open_in_the_subtopic(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $first = app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->transitions()->withdraw($first);

        // A different level, same subtopic -- so it holds the subtopic's slot
        // and its own attempt numbering, leaving $first the highest at 101.
        app(CreateCourse::class)->fromCatalog($student, $algebra, SkillLevel::Intermediate);

        $this->assertNull($this->transitions()->resumable($student, 'math', 'algebra', SkillLevel::Introductory));

        $this->expectException(CourseNotResumable::class);
        $this->transitions()->resume($first);
    }

    #[Test]
    public function nothing_to_resume_reads_as_null(): void
    {
        $student = User::factory()->create();

        $this->assertNull($this->transitions()->resumable($student, 'math', 'algebra', SkillLevel::Introductory));

        app(CreateCourse::class)->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Introductory);

        $this->assertNull(
            $this->transitions()->resumable($student, 'math', 'algebra', SkillLevel::Introductory),
            'an open course is carried on, not resumed',
        );
    }

    /**
     * Passing the final exam: status records how far the course got, the date
     * records that it was cleared (ledger D7b).
     */
    private function complete(Course $course): void
    {
        $course->forceFill(['status' => CourseStatus::FinalExam])->save();

        $this->transitions()->complete($course);
    }

    private function transitions(): CourseTransitions
    {
        return app(CourseTransitions::class);
    }

    private function preset(string $module, string $subtopic): Subtopic
    {
        if (Module::query()->doesntExist()) {
            $this->seed(CourseCatalogSeeder::class);
        }

        return Module::query()->where('slug', $module)->sole()
            ->subtopics()->where('slug', $subtopic)->sole();
    }
}
