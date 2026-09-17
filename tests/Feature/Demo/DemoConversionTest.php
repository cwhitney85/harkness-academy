<?php

namespace Tests\Feature\Demo;

use App\Courses\CreateCourse;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Module;
use Database\Seeders\CourseCatalogSeeder;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Assessment\AssessmentTestCase;

/**
 * Spec 12.4 -- the demo ends at the offer to keep it, and keeping it is
 * registration onto the row the demo already made.
 */
class DemoConversionTest extends AssessmentTestCase
{
    private const CODE = 'harkness-invite-code';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.invitation_code' => self::CODE]);
    }

    #[Test]
    public function a_finished_demo_course_offers_to_keep_it_where_the_exam_would_be(): void
    {
        $course = $this->demo($this->taughtCourse());
        $last = $course->lessons->last();
        $offer = route('demo.account.create');

        $this->actingAs($course->student);

        $homework = $this->inertiaProps($this->get(route('lessons.homework.show', $last))->assertOk());
        $this->assertTrue($homework['demo']);
        $this->assertSame($offer, $homework['urls']['exam']);

        $lesson = $this->inertiaProps($this->get(route('lessons.show', $last))->assertOk());
        $this->assertSame($offer, $lesson['urls']['exam']);

        $dashboard = $this->inertiaProps($this->get(route('dashboard'))->assertOk());
        $this->assertSame($offer, $dashboard['in_progress'][0]['url']);
        $this->assertSame('Every lesson done — keep your course', $dashboard['in_progress'][0]['detail']);
    }

    #[Test]
    public function a_finished_standard_course_goes_to_its_exam(): void
    {
        $course = $this->taughtCourse();
        $last = $course->lessons->last();

        $homework = $this->inertiaProps($this->actingAs($course->student)->get(route('lessons.homework.show', $last)));

        $this->assertFalse($homework['demo']);
        $this->assertSame(route('courses.final-exam.show', $course), $homework['urls']['exam']);
    }

    #[Test]
    public function keeping_the_demo_makes_it_a_standard_account_with_the_same_working_knowledge(): void
    {
        $course = $this->demo($this->taughtCourse());
        $student = $course->student;
        $tree = app(MemoryTree::class);
        $before = $tree->document($student, $course->workingKnowledgePath())->versionId;

        $this->actingAs($student)
            ->get(route('demo.account.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/keep-course'));

        $this->post(route('demo.account.store'), $this->submission())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('courses.create'));

        $student->refresh();
        $this->assertSame('standard', $student->plan);
        $this->assertSame('kept@example.com', $student->email);
        $this->assertNotNull($student->tos_accepted_at);
        $this->assertAuthenticatedAs($student);

        // 12.4.4: set aside, and the working knowledge untouched by it.
        $this->assertNotNull($course->refresh()->withdrawn_at);
        $this->assertSame($before, $tree->document($student, $course->workingKnowledgePath())->versionId);

        $this->seed(CourseCatalogSeeder::class);
        $fresh = app(CreateCourse::class)->fromFreeText(
            $student,
            Module::query()->where('slug', $course->module_slug)->firstOrFail(),
            $course->subtopic_title,
            $course->skill_level,
        );
        $this->assertSame($course->attempt + 1, $fresh->attempt);
    }

    #[Test]
    public function no_code_or_a_wrong_one_leaves_the_account_a_demo_and_says_so_on_the_code_field(): void
    {
        $course = $this->demo($this->taughtCourse());
        $this->actingAs($course->student);

        foreach (['', 'not-the-code'] as $code) {
            $this->post(route('demo.account.store'), $this->submission(['invitation_code' => $code]))
                ->assertSessionHasErrors('invitation_code');
        }

        $this->post(route('demo.account.store'), $this->submission(['tos_accepted' => false]))
            ->assertSessionHasErrors('tos_accepted');

        $this->assertTrue($course->student->refresh()->isDemo());
        $this->assertNull($course->refresh()->withdrawn_at);
    }

    #[Test]
    public function a_standard_account_has_no_demo_to_keep(): void
    {
        $course = $this->taughtCourse();

        $this->actingAs($course->student)->get(route('demo.account.create'))->assertRedirect(route('dashboard'));
        $this->post(route('demo.account.store'), $this->submission())->assertRedirect(route('dashboard'));

        $this->assertNotSame('kept@example.com', $course->student->refresh()->email);
    }

    private function demo(Course $course): Course
    {
        $course->student->forceFill(['plan' => 'demo'])->save();

        return $course->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return [
            'name' => 'Kept Student',
            'email' => 'kept@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'tos_accepted' => true,
            'invitation_code' => self::CODE,
            ...$overrides,
        ];
    }
}
