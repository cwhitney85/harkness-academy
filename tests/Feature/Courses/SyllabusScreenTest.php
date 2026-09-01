<?php

namespace Tests\Feature\Courses;

use App\Ai\StructuredGatewayAgent;
use App\Courses\CourseStatus;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Lesson;
use App\Models\User;
use Database\Factories\CourseSyllabusFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.7.4 and 8.7.5 -- screen S5.
 *
 * 8.7.4's done-condition is two verbs: **approve advances the course**, and
 * **revise regenerates against the typed request**. 8.7.5's is one absence:
 * a mismatched course still approves. That absence is the whole of 4.5.7 --
 * the banner is a paragraph, and a paragraph that could block anything would
 * be a gate.
 */
class SyllabusScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function a_course_with_no_draft_is_offered_the_button_that_makes_one(): void
    {
        $course = $this->course();

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/syllabus')
                ->where('syllabus', null)
                ->where('revisions.remaining', 3)
                ->where('urls.generate', route('courses.syllabus.store', $course)),
            );
    }

    #[Test]
    public function the_screen_carries_the_draft_its_lessons_and_the_exam_it_names(): void
    {
        $course = $this->course();
        $this->draft($course);

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertInertia(fn ($page) => $page
                ->has('syllabus.lessons', 2)
                ->has('syllabus.final_exam_concepts', 3)
                ->where('syllabus.generation', 1)
                ->where('exam.questions.min', 8)
                ->where('exam.pass_mark', 0.7),
            );
    }

    #[Test]
    public function approve_advances_the_course_and_lands_on_lesson_one(): void
    {
        $course = $this->course();
        $this->draft($course);

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.approval.store', $course));

        $course->refresh();
        $lesson = $course->lessons()->first();

        $this->assertSame(CourseStatus::Active, $course->status);
        $this->assertInstanceOf(Lesson::class, $lesson);
        $this->assertSame(2, $course->lessons()->count());
        $this->assertNotNull($course->approvedSyllabus()->first());
    }

    #[Test]
    public function approving_redirects_to_the_first_lesson(): void
    {
        $course = $this->course();
        $this->draft($course);

        $response = $this->actingAs($course->student)
            ->post(route('courses.syllabus.approval.store', $course));

        $response->assertRedirect(route('lessons.show', $course->fresh()?->lessons()->first()));
    }

    #[Test]
    public function revise_regenerates_against_the_typed_request(): void
    {
        $course = $this->course();
        $this->draft($course);

        $asked = null;

        StructuredGatewayAgent::fake(function (string $prompt) use (&$asked): array {
            $asked = $prompt;

            return CourseSyllabusFactory::exampleSyllabus();
        })->preventStrayPrompts();

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.revisions.store', $course), [
                'feedback' => 'Fewer lessons, and more drilling on equations.',
            ])
            ->assertRedirect(route('courses.syllabus.show', $course));

        $latest = $course->latestSyllabus()->first();

        $this->assertSame(2, $latest?->generation);
        $this->assertSame('Fewer lessons, and more drilling on equations.', $latest?->feedback);
        $this->assertStringContainsString(
            'more drilling on equations',
            (string) $asked,
            'The typed request is what the regeneration is against.',
        );
    }

    #[Test]
    public function a_revision_with_nothing_written_in_it_is_refused_at_the_field(): void
    {
        $course = $this->course();
        $this->draft($course);

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.revisions.store', $course), ['feedback' => '   '])
            ->assertSessionHasErrors('feedback');

        $this->assertSame(1, $course->syllabi()->count());
    }

    #[Test]
    public function running_out_of_revisions_says_so_rather_than_regenerating(): void
    {
        $course = $this->course();
        $this->draft($course, generation: 4);

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.revisions.store', $course), ['feedback' => 'Once more.'])
            ->assertSessionHasErrors('feedback');

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertInertia(fn ($page) => $page->where('revisions.remaining', 0));
    }

    /**
     * 8.7.5 -- non-blocking, which is only demonstrable by approving.
     */
    #[Test]
    public function a_mismatched_course_still_approves(): void
    {
        $course = $this->course();
        $note = 'The stated goals are about cooking, not algebra.';

        CourseSyllabus::factory()->for($course)->mismatched($note)->create();

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertInertia(fn ($page) => $page->where('syllabus.subtopic_mismatch', $note));

        $this->actingAs($course->student)
            ->post(route('courses.syllabus.approval.store', $course));

        $this->assertSame(CourseStatus::Active, $course->fresh()?->status);
    }

    /**
     * The resume table (8.6) -- and the reason `syllabus_revision` is on it:
     * the previous draft still stands and is still approvable.
     */
    #[Test]
    public function a_course_awaiting_a_regeneration_still_sees_the_draft_it_has(): void
    {
        $course = $this->course(CourseStatus::SyllabusRevision);
        $this->draft($course);

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('syllabus.generation', 1));
    }

    #[Test]
    public function an_active_course_is_sent_to_its_lesson_rather_than_its_syllabus(): void
    {
        $course = $this->course();
        $this->draft($course);

        $this->actingAs($course->student)->post(route('courses.syllabus.approval.store', $course));

        $this->actingAs($course->student)
            ->get(route('courses.syllabus.show', $course))
            ->assertRedirect(route('lessons.show', $course->fresh()?->lessons()->first()));
    }

    #[Test]
    public function no_one_may_approve_another_students_syllabus(): void
    {
        $course = $this->course();
        $this->draft($course);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.syllabus.approval.store', $course))
            ->assertForbidden();
    }

    private function course(CourseStatus $status = CourseStatus::SyllabusDraft): Course
    {
        return Course::factory()->for(User::factory(), 'student')->status($status)->create();
    }

    private function draft(Course $course, int $generation = 1): CourseSyllabus
    {
        return CourseSyllabus::factory()->for($course)->generation($generation)->create();
    }
}
