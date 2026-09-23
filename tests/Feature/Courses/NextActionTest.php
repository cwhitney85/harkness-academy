<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\LessonStatus;
use App\Courses\NextAction;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.6, "Where a resume lands" -- the table, checked row by row.
 *
 * The flow map says nothing else may decide this, which is only worth saying
 * because three things want to: the dashboard's next action (8.10.1), the
 * landing after a resume (8.7.6), and every pipeline screen's redirect when it
 * finds a course has moved past it. All three read this class, so the way to
 * make them disagree is to change this test.
 */
class NextActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_course_resumes_at_its_knowledge_interview(): void
    {
        $course = Course::factory()->status(CourseStatus::Created)->create();

        $this->assertSame(route('courses.knowledge-interview.show', $course), NextAction::url($course));
    }

    #[Test]
    public function each_pipeline_position_has_its_own_screen(): void
    {
        $cases = [
            [CourseStatus::KnowledgeInterview, 'courses.knowledge-interview.show'],
            [CourseStatus::GoalsInterview, 'courses.goals-interview.show'],
            [CourseStatus::SyllabusDraft, 'courses.syllabus.show'],
            [CourseStatus::SyllabusRevision, 'courses.syllabus.show'],
            [CourseStatus::FinalExam, 'courses.final-exam.show'],
        ];

        foreach ($cases as [$status, $expected]) {
            $course = Course::factory()->status($status)->create();

            $this->assertSame(route($expected, $course), NextAction::url($course), $status->value);
        }
    }

    #[Test]
    public function an_active_course_resumes_at_the_lowest_lesson_still_owed(): void
    {
        $course = Course::factory()->status(CourseStatus::Active)->create();

        $done = Lesson::factory()->for($course)->create(['position' => 1, 'status' => LessonStatus::Completed]);
        $next = Lesson::factory()->for($course)->create(['position' => 2, 'status' => LessonStatus::InProgress]);
        Lesson::factory()->for($course)->create(['position' => 3, 'status' => LessonStatus::Locked]);

        $this->assertSame(route('lessons.show', $next), NextAction::url($course));
        $this->assertNotSame(route('lessons.show', $done), NextAction::url($course));
    }

    #[Test]
    public function an_active_course_with_no_lessons_left_resumes_at_its_exam(): void
    {
        $course = Course::factory()->status(CourseStatus::Active)->create();

        Lesson::factory()->for($course)->create(['position' => 1, 'status' => LessonStatus::Completed]);

        $this->assertSame(route('courses.final-exam.show', $course), NextAction::url($course));
    }

    /**
     * The ending outranks the pipeline position it was left on (ledger D7d):
     * a course withdrawn during syllabus revision is withdrawn, not revising.
     */
    #[Test]
    public function a_withdrawn_course_lands_on_the_dashboard_not_the_screen_it_was_left_on(): void
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->withdrawn()->create();

        $this->assertSame(route('dashboard'), NextAction::url($course));
    }

    /**
     * The rollup row, built by 8.10.2. `ModuleRollupScreenTest` checks it from
     * a real completed course; here it is the table's own row.
     */
    #[Test]
    public function a_completed_course_lands_on_its_module_rollup(): void
    {
        $module = Module::query()->create(['slug' => 'math', 'title' => 'Math', 'position' => 1]);
        $course = Course::factory()->completed()->create(['module_slug' => $module->slug]);

        $this->assertSame(route('modules.show', $module), NextAction::url($course));
    }
}
