<?php

namespace Tests\Feature\Courses;

use App\Courses\Syllabus\GuidanceDocument;
use App\Courses\Syllabus\LessonGuidance;
use App\Models\Course;
use Database\Factories\CourseSyllabusFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.3 -- GUIDANCE.md renders from the approved syllabus (ledger D10.3).
 */
class GuidanceDocumentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_golden_file(): void
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        $this->assertSame(
            file_get_contents(base_path('tests/fixtures/guidance/letters-as-numbers.md')),
            GuidanceDocument::render($syllabus, $syllabus['lessons'][0]),
        );
    }

    #[Test]
    public function it_carries_every_concept_slug_of_the_lesson(): void
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        $rendered = GuidanceDocument::render($syllabus, $syllabus['lessons'][1]);

        foreach (['equation', 'variable', 'inverse-operation'] as $slug) {
            $this->assertStringContainsString($slug, $rendered);
        }
    }

    #[Test]
    public function a_lesson_path_sits_under_the_courses_materials_prefix(): void
    {
        $course = Course::factory()->create();
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        $this->assertSame(
            $course->materialsPrefix().'/01-letters-as-numbers/GUIDANCE.md',
            LessonGuidance::pathFor($course, $syllabus['lessons'][0]),
        );
    }

    #[Test]
    public function two_lessons_sharing_a_title_do_not_share_a_path(): void
    {
        $first = LessonGuidance::slugFor(['position' => 3, 'title' => 'Practice']);
        $second = LessonGuidance::slugFor(['position' => 7, 'title' => 'Practice']);

        $this->assertNotSame($first, $second);
        $this->assertSame('03-practice', $first);
        $this->assertSame('07-practice', $second);
    }

    #[Test]
    public function a_title_that_slugs_to_nothing_still_names_its_lesson(): void
    {
        $this->assertSame('04', LessonGuidance::slugFor(['position' => 4, 'title' => '???']));
    }
}
