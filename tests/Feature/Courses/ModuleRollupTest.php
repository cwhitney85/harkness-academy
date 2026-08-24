<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\ModuleRollup;
use App\Courses\SkillLevel;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 3.3 -- the derived module rollup.
 *
 * Never materialised: every test here asserts on a read, and no test writes
 * `{module}/WORKING_KNOWLEDGE.md` anywhere.
 */
class ModuleRollupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_module_with_no_knowledge_records_renders_an_empty_rollup(): void
    {
        $student = User::factory()->create();

        $this->assertSame([], $this->rollup()->pointers($student, $this->module('math')));
        $this->assertStringContainsString(
            'No Math subtopics have a knowledge record yet.',
            $this->rollup()->render($student, $this->module('math')),
        );
    }

    #[Test]
    public function completing_a_course_changes_the_rollup_with_no_write_step(): void
    {
        $student = User::factory()->create();
        $course = $this->courseWithKnowledge($student, 'math', 'algebra', SkillLevel::Introductory);

        $before = $this->rollup()->render($student, $this->module('math'));
        $this->assertStringContainsString('no course completed yet', $before);

        $this->complete($course);

        $after = $this->rollup()->render($student, $this->module('math'));
        $this->assertStringContainsString('completed: 101', $after);

        $this->assertNotContains(
            'math/WORKING_KNOWLEDGE.md',
            app(MemoryTree::class)->list($student),
            'the rollup is derived at read time and must never be written',
        );
    }

    /**
     * The reason existence comes from the tree rather than from completed
     * courses: a student who withdrew still knows what they told the tutor.
     */
    #[Test]
    public function a_subtopic_with_knowledge_but_no_completed_course_still_appears(): void
    {
        $student = User::factory()->create();
        $course = $this->courseWithKnowledge($student, 'math', 'geometry', SkillLevel::Introductory);

        app(CourseTransitions::class)->withdraw($course);

        $pointers = $this->rollup()->pointers($student, $this->module('math'));

        $this->assertCount(1, $pointers);
        $this->assertSame('math/geometry/WORKING_KNOWLEDGE.md', $pointers[0]->path);
        $this->assertSame([], $pointers[0]->levelsCompleted);
    }

    #[Test]
    public function levels_are_deduped_onto_one_line_per_subtopic(): void
    {
        $student = User::factory()->create();

        $first = $this->courseWithKnowledge($student, 'math', 'algebra', SkillLevel::Introductory);
        $this->complete($first);

        $retake = app(CreateCourse::class)->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Introductory);
        $this->complete($retake);

        $next = app(CreateCourse::class)->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Intermediate);
        $this->complete($next);

        $pointers = $this->rollup()->pointers($student, $this->module('math'));

        $this->assertCount(1, $pointers, 'three courses, one subtopic, one line');
        $this->assertSame(
            [SkillLevel::Introductory, SkillLevel::Intermediate],
            $pointers[0]->levelsCompleted,
            '101 completed twice is still said once',
        );
        $this->assertStringContainsString(
            'completed: 101, 201',
            $this->rollup()->render($student, $this->module('math')),
        );
    }

    #[Test]
    public function an_incomplete_course_with_no_knowledge_record_yet_is_absent(): void
    {
        $student = User::factory()->create();

        app(CreateCourse::class)->fromCatalog($student, $this->preset('math', 'algebra'), SkillLevel::Introductory);

        $this->assertSame(
            [],
            $this->rollup()->pointers($student, $this->module('math')),
            'a course created but not yet interviewed has nothing to point at',
        );
    }

    #[Test]
    public function the_rollup_is_scoped_to_one_module_and_one_student(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();

        $this->courseWithKnowledge($student, 'math', 'algebra', SkillLevel::Introductory);
        $this->courseWithKnowledge($student, 'science', 'physics', SkillLevel::Introductory);
        $this->courseWithKnowledge($other, 'math', 'calculus', SkillLevel::Introductory);

        $pointers = $this->rollup()->pointers($student, $this->module('math'));

        $this->assertCount(1, $pointers);
        $this->assertSame('algebra', $pointers[0]->subtopicSlug);
    }

    #[Test]
    public function free_text_subtopics_appear_under_the_students_own_wording(): void
    {
        $student = User::factory()->create();

        $course = app(CreateCourse::class)->fromFreeText(
            $student,
            $this->module('math'),
            'Knot Theory',
            SkillLevel::Advanced,
        );
        app(MemoryTree::class)->put($student, $course->workingKnowledgePath(), '# Solid: trefoils');
        $this->complete($course);

        $this->assertStringContainsString(
            '- `math/knot-theory/WORKING_KNOWLEDGE.md` — Knot Theory (completed: 301)',
            $this->rollup()->render($student, $this->module('math')),
        );
    }

    /**
     * A module quiz (spec 6.5) walks these rather than parsing the prose.
     */
    #[Test]
    public function pointers_resolve_to_documents_the_path_api_can_read(): void
    {
        $student = User::factory()->create();
        $this->courseWithKnowledge($student, 'math', 'algebra', SkillLevel::Introductory);
        $this->courseWithKnowledge($student, 'math', 'geometry', SkillLevel::Introductory);

        $tree = app(MemoryTree::class);

        foreach ($this->rollup()->pointers($student, $this->module('math')) as $pointer) {
            $this->assertNotNull($tree->get($student, $pointer->path));
        }
    }

    private function rollup(): ModuleRollup
    {
        return app(ModuleRollup::class);
    }

    private function courseWithKnowledge(User $student, string $module, string $subtopic, SkillLevel $level): Course
    {
        $course = app(CreateCourse::class)->fromCatalog($student, $this->preset($module, $subtopic), $level);

        app(MemoryTree::class)->put($student, $course->workingKnowledgePath(), "# Solid: something about {$subtopic}");

        return $course;
    }

    private function complete(Course $course): void
    {
        $course->forceFill(['status' => CourseStatus::Completed])->save();
    }

    private function module(string $slug): Module
    {
        if (Module::query()->doesntExist()) {
            $this->seed(CourseCatalogSeeder::class);
        }

        return Module::query()->where('slug', $slug)->sole();
    }

    private function preset(string $module, string $subtopic): Subtopic
    {
        return $this->module($module)->subtopics()->where('slug', $subtopic)->sole();
    }
}
