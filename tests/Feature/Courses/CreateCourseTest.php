<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\Exceptions\SubtopicAlreadyOpen;
use App\Courses\Exceptions\UnusableSubtopicName;
use App\Courses\SkillLevel;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.1 -- the preset catalog, free-text creation, and the exclusivity
 * rule the two of them have to respect (ledger D7a/D7b).
 */
class CreateCourseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_catalog_seeds_every_preset_module(): void
    {
        $this->seed(CourseCatalogSeeder::class);

        $this->assertSame(
            ['math', 'science', 'history', 'language', 'econ', 'philosophy'],
            Module::query()->orderBy('position')->pluck('slug')->all()
        );

        $this->assertGreaterThan(0, Subtopic::query()->count());
    }

    #[Test]
    public function seeding_twice_does_not_duplicate_the_catalog(): void
    {
        $this->seed(CourseCatalogSeeder::class);
        $modules = Module::query()->count();
        $subtopics = Subtopic::query()->count();

        $this->seed(CourseCatalogSeeder::class);

        $this->assertSame($modules, Module::query()->count());
        $this->assertSame($subtopics, Subtopic::query()->count());
    }

    #[Test]
    public function picking_a_preset_creates_a_course_in_created(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $course = $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);

        $this->assertSame(CourseStatus::Created, $course->status);
        $this->assertSame('math', $course->module_slug);
        $this->assertSame('algebra', $course->subtopic_slug);
        $this->assertSame('Algebra', $course->subtopic_title);
        $this->assertSame(SkillLevel::Introductory, $course->skill_level);
        $this->assertSame(1, $course->attempt);
        $this->assertNull($course->withdrawn_at);
        $this->assertTrue($course->isOpen());
    }

    #[Test]
    public function free_text_creates_a_course_in_created(): void
    {
        $student = User::factory()->create();
        $math = $this->module('math');

        $course = $this->action()->fromFreeText($student, $math, 'Knot Theory', SkillLevel::Advanced);

        $this->assertSame(CourseStatus::Created, $course->status);
        $this->assertSame('math', $course->module_slug);
        $this->assertSame('knot-theory', $course->subtopic_slug);
        $this->assertSame('Knot Theory', $course->subtopic_title);
        $this->assertSame(SkillLevel::Advanced, $course->skill_level);
    }

    #[Test]
    public function free_text_keeps_the_students_wording_but_stores_a_path_safe_slug(): void
    {
        $student = User::factory()->create();

        $course = $this->action()->fromFreeText(
            $student,
            $this->module('language'),
            "  Beginner's   Conversational   Spanish!  ",
            SkillLevel::Introductory,
        );

        $this->assertSame("Beginner's Conversational Spanish!", $course->subtopic_title);
        $this->assertSame('beginners-conversational-spanish', $course->subtopic_slug);
    }

    /**
     * The collision the whole slug design exists to prevent: two courses
     * writing one WORKING_KNOWLEDGE.md, the second erasing the first's notes.
     */
    #[Test]
    public function free_text_matching_a_preset_is_the_same_subtopic_not_a_second_one(): void
    {
        $student = User::factory()->create();
        $this->action()->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Introductory);

        $this->expectException(SubtopicAlreadyOpen::class);

        $this->action()->fromFreeText($student, $this->module('math'), 'algebra', SkillLevel::Intermediate);
    }

    #[Test]
    public function a_second_open_course_in_one_subtopic_is_refused(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);

        $this->expectException(SubtopicAlreadyOpen::class);

        $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);
    }

    #[Test]
    public function an_open_course_in_one_subtopic_does_not_block_another_subtopic(): void
    {
        $student = User::factory()->create();

        $this->action()->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Introductory);
        $this->action()->fromCatalog($student, $this->preset('math', 'geometry'), SkillLevel::Introductory);

        $this->assertSame(2, Course::query()->open()->count());
    }

    #[Test]
    public function one_students_open_course_does_not_block_another_student(): void
    {
        $algebra = $this->preset('math', 'algebra');

        $this->action()->fromCatalog(User::factory()->create(), $algebra, SkillLevel::Introductory);
        $this->action()->fromCatalog(User::factory()->create(), $algebra, SkillLevel::Introductory);

        $this->assertSame(2, Course::query()->open()->count());
    }

    #[Test]
    public function a_completed_course_frees_the_subtopic_and_the_next_run_is_attempt_two(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $first = $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->pass($first);

        $second = $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);

        $this->assertSame(2, $second->attempt);
        $this->assertSame(CourseStatus::Created, $second->status);
    }

    #[Test]
    public function attempts_are_counted_per_skill_level(): void
    {
        $student = User::factory()->create();
        $algebra = $this->preset('math', 'algebra');

        $intro = $this->action()->fromCatalog($student, $algebra, SkillLevel::Introductory);
        $this->pass($intro);

        $next = $this->action()->fromCatalog($student, $algebra, SkillLevel::Intermediate);

        $this->assertSame(1, $next->attempt, 'a first run at 201 is attempt 1, not attempt 2');
    }

    #[Test]
    #[DataProvider('unusableNames')]
    public function a_name_that_cannot_become_a_folder_is_refused(string $name): void
    {
        $this->expectException(UnusableSubtopicName::class);

        $this->action()->fromFreeText(
            User::factory()->create(),
            $this->module('math'),
            $name,
            SkillLevel::Introductory,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableNames(): array
    {
        return [
            'blank' => [''],
            'whitespace only' => ["  \t "],
            'punctuation only' => ['!!! ???'],
            'longer than the column' => [str_repeat('algebra ', 20)],
        ];
    }

    #[Test]
    public function a_courses_memory_paths_come_from_its_own_slugs(): void
    {
        $course = $this->action()->fromCatalog(
            User::factory()->create(),
            $this->preset('language', 'spanish'),
            SkillLevel::Introductory,
        );

        $this->assertSame('language/spanish/WORKING_KNOWLEDGE.md', $course->workingKnowledgePath());
        $this->assertSame('language/spanish/101', $course->materialsPrefix());
    }

    /**
     * Why courses copy slugs instead of pointing at the catalog: an admin
     * renaming a preset must not relocate a student's knowledge record.
     */
    #[Test]
    public function renaming_a_catalog_entry_leaves_existing_courses_untouched(): void
    {
        $algebra = $this->preset('math', 'algebra');
        $course = $this->action()->fromCatalog(User::factory()->create(), $algebra, SkillLevel::Introductory);

        $algebra->update(['slug' => 'algebra-i', 'title' => 'Algebra I']);

        $this->assertSame('math/algebra/WORKING_KNOWLEDGE.md', $course->fresh()->workingKnowledgePath());
    }

    /**
     * Walk a course to a passed final exam through the real service.
     */
    private function pass(Course $course): void
    {
        $transitions = app(CourseTransitions::class);

        foreach ([
            CourseStatus::KnowledgeInterview,
            CourseStatus::GoalsInterview,
            CourseStatus::SyllabusDraft,
            CourseStatus::Active,
            CourseStatus::FinalExam,
        ] as $next) {
            $transitions->to($course, $next);
        }

        $transitions->complete($course);
    }

    private function action(): CreateCourse
    {
        return app(CreateCourse::class);
    }

    private function module(string $slug): Module
    {
        $this->seedCatalogOnce();

        return Module::query()->where('slug', $slug)->sole();
    }

    private function preset(string $module, string $subtopic): Subtopic
    {
        return $this->module($module)->subtopics()->where('slug', $subtopic)->sole();
    }

    private function seedCatalogOnce(): void
    {
        if (Module::query()->doesntExist()) {
            $this->seed(CourseCatalogSeeder::class);
        }
    }
}
