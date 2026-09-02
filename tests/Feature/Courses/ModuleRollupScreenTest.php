<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\NextAction;
use App\Courses\SkillLevel;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.10.2 -- screen S12.
 *
 * `ModuleRollupTest` holds the rollup itself (3.3); this holds the screen over
 * it. The done-condition is that a completed course's knowledge appears under
 * its module, and the flow map adds two edges the engine cannot check: the way
 * back to S1's card, and the two refresher doors.
 */
class ModuleRollupScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    #[Test]
    public function a_completed_courses_knowledge_appears_under_its_module(): void
    {
        $student = User::factory()->create();
        $course = $this->courseWithKnowledge($student, 'math', 'algebra');
        $this->complete($course);

        $this->actingAs($student)
            ->get(route('modules.show', $this->module('math')))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('modules/show')
                ->where('module.title', 'Math')
                ->has('subtopics', 1)
                ->where('subtopics.0.title', 'Algebra')
                ->where('subtopics.0.levels', ['Introductory'])
                ->where('subtopics.0.courses.0.id', $course->id)
                ->where('subtopics.0.courses.0.standing', CourseStatus::Completed->title())
                ->where('subtopics.0.refresher_url', route('courses.refreshers.store', $course))
                ->where('urls.refresher', route('modules.refreshers.store', $this->module('math'))),
            );
    }

    /**
     * The rollup's own rule, kept by the screen: which subtopics appear comes
     * from the memory tree, so a student who set a course aside still sees
     * what their interview recorded (D8).
     */
    #[Test]
    public function a_subtopic_with_knowledge_but_no_completed_course_still_appears(): void
    {
        $student = User::factory()->create();
        $course = $this->courseWithKnowledge($student, 'math', 'geometry');
        app(CourseTransitions::class)->withdraw($course);

        $this->actingAs($student)
            ->get(route('modules.show', $this->module('math')))
            ->assertInertia(fn ($page) => $page
                ->has('subtopics', 1)
                ->where('subtopics.0.levels', [])
                ->where('subtopics.0.courses.0.standing', CourseStatus::Withdrawn->title()),
            );
    }

    #[Test]
    public function a_module_the_student_has_no_record_in_says_so_rather_than_404ing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('modules.show', $this->module('math')))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('subtopics', 0));
    }

    #[Test]
    public function one_students_rollup_never_shows_anothers_records(): void
    {
        $other = User::factory()->create();
        $this->complete($this->courseWithKnowledge($other, 'math', 'algebra'));

        $this->actingAs(User::factory()->create())
            ->get(route('modules.show', $this->module('math')))
            ->assertInertia(fn ($page) => $page->has('subtopics', 0));
    }

    /**
     * 8.6's resume table sends a completed course here, and said so before the
     * route existed. This is the row it was waiting for.
     */
    #[Test]
    public function a_completed_course_now_resumes_at_its_module_rollup(): void
    {
        $student = User::factory()->create();
        $course = $this->courseWithKnowledge($student, 'math', 'algebra');
        $this->complete($course);

        $this->assertSame(
            route('modules.show', $this->module('math')),
            NextAction::url($course->refresh()),
        );
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('modules.show', $this->module('math')))
            ->assertRedirect(route('login'));
    }

    private function courseWithKnowledge(User $student, string $module, string $subtopic): Course
    {
        $preset = $this->module($module)->subtopics()->where('slug', $subtopic)->sole();

        $course = app(CreateCourse::class)->fromCatalog($student, $preset, SkillLevel::Introductory);

        app(MemoryTree::class)->put($student, $course->workingKnowledgePath(), "# Solid: something about {$subtopic}");

        return $course;
    }

    private function complete(Course $course): void
    {
        $course->forceFill(['status' => CourseStatus::FinalExam])->save();

        app(CourseTransitions::class)->complete($course);
    }

    private function module(string $slug): Module
    {
        return Module::query()->where('slug', $slug)->sole();
    }
}
