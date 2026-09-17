<?php

namespace Tests\Feature\Courses;

use App\Courses\DepthParameters;
use App\Courses\Exceptions\UnknownPlan;
use App\Courses\SkillLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.6 -- depth is f(skill_level, plan), and the two never couple.
 */
class DepthParametersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function changing_the_plan_alone_changes_the_depth(): void
    {
        config()->set('courses.plans', ['standard', 'generous']);
        config()->set('courses.depth.101.generous', [
            'lessons' => [14, 16],
            'sections_per_lesson' => [5, 6],
            'minutes_per_lesson' => 30,
        ]);

        $standard = DepthParameters::for(SkillLevel::Introductory, 'standard');
        $generous = DepthParameters::for(SkillLevel::Introductory, 'generous');

        $this->assertNotSame($standard->lessons, $generous->lessons);
        $this->assertSame([14, 16], $generous->lessons);
    }

    #[Test]
    public function skill_level_is_pedagogy_not_price(): void
    {
        $plan = config('courses.default_plan');

        $introductory = DepthParameters::for(SkillLevel::Introductory, $plan);
        $advanced = DepthParameters::for(SkillLevel::Advanced, $plan);

        // The founder correction, pinned: a beginner's course is the *longest*
        // of the three, because scaffolding is not a premium feature.
        $this->assertGreaterThan($advanced->lessons[1], $introductory->lessons[1]);
    }

    #[Test]
    public function an_unknown_plan_has_no_depth(): void
    {
        $this->expectException(UnknownPlan::class);

        DepthParameters::for(SkillLevel::Introductory, 'enterprise');
    }

    #[Test]
    public function every_shipped_plan_resolves_at_every_skill_level(): void
    {
        foreach (config('courses.plans') as $plan) {
            foreach (SkillLevel::cases() as $level) {
                $depth = DepthParameters::for($level, $plan);

                $this->assertGreaterThan(0, $depth->minutesPerLesson, "{$level->value}/{$plan}");
            }
        }
    }

    #[Test]
    public function the_rendered_variable_is_a_markdown_key_list(): void
    {
        $expected = [
            101 => "- Lessons: 8-10\n- Sections per lesson: 3-4\n- Target minutes per lesson: 20",
            201 => "- Lessons: 6-8\n- Sections per lesson: 3-4\n- Target minutes per lesson: 25",
            301 => "- Lessons: 5-7\n- Sections per lesson: 4-5\n- Target minutes per lesson: 30",
        ];

        foreach ($expected as $level => $rendered) {
            $this->assertSame(
                $rendered,
                DepthParameters::for(SkillLevel::from($level), 'standard')->render(),
            );
        }
    }

    #[Test]
    public function a_demo_course_is_one_lesson_of_standard_lessons(): void
    {
        // Spec 12.2.2: only the lesson count shrinks, so the lesson is real.
        foreach (SkillLevel::cases() as $level) {
            $standard = DepthParameters::for($level, 'standard');
            $demo = DepthParameters::for($level, 'demo');

            $this->assertStringStartsWith("- Lessons: 1\n", $demo->render());
            $this->assertSame($standard->sectionsPerLesson, $demo->sectionsPerLesson);
            $this->assertSame($standard->minutesPerLesson, $demo->minutesPerLesson);
        }
    }
}
