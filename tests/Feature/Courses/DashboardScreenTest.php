<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\LessonStatus;
use App\Courses\NextAction;
use App\Courses\ResumeRefusal;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.10.1 and 8.10.3 -- screen S1.
 *
 * The dashboard is the only screen that answers "what am I in the middle of?",
 * and its done-condition is that the starter kit's placeholder is gone. The
 * tests below are the other half of that: what replaced it has to be the
 * student's own courses, and the move offered in each has to be the one the
 * flow map's resume table names -- `NextAction`, and nothing this controller
 * decided for itself (8.6).
 */
class DashboardScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_placeholder_the_starter_kit_left_is_gone(): void
    {
        $this->assertFileDoesNotExist(
            resource_path('js/components/ui/placeholder-pattern.tsx'),
            'The dashboard no longer draws placeholder rectangles (8.10.1).',
        );
    }

    #[Test]
    public function a_student_with_nothing_underway_is_offered_the_way_to_start(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('in_progress', 0)
                ->where('urls.create', route('courses.create')),
            );
    }

    #[Test]
    public function every_open_course_is_listed_with_where_it_stands(): void
    {
        $student = User::factory()->create();

        Course::factory()->for($student, 'student')->status(CourseStatus::SyllabusDraft)->create([
            'subtopic_title' => 'Conversational Spanish',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('in_progress', 1)
                ->where('in_progress.0.title', 'Conversational Spanish')
                ->where('in_progress.0.level', 'Introductory')
                ->where('in_progress.0.standing', CourseStatus::SyllabusDraft->title()),
            );
    }

    /**
     * 8.6 says nothing but the resume table may decide where a course picks
     * back up. This is that, checked at the one screen that offers it for
     * every course at once.
     */
    #[Test]
    public function the_move_offered_is_the_one_the_resume_table_names(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->for($student, 'student')->status(CourseStatus::Active)->create();

        Lesson::factory()->for($course)->create(['position' => 1, 'status' => LessonStatus::Completed]);
        $owed = Lesson::factory()->for($course)->create(['position' => 2, 'status' => LessonStatus::Available]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('in_progress.0.url', NextAction::url($course->refresh()))
                ->where('in_progress.0.url', route('lessons.show', $owed))
                ->where('in_progress.0.detail', '1 of 2 lessons done'),
            );
    }

    #[Test]
    public function a_course_that_has_ended_is_not_in_progress(): void
    {
        $student = User::factory()->create();

        Course::factory()->for($student, 'student')->withdrawn()->create();
        Course::factory()->for($student, 'student')->completed()->create([
            'subtopic_slug' => 'geometry',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('in_progress', 0)
                ->has('set_aside', 1)
                ->has('completed', 1),
            );
    }

    /**
     * 8.10.3's done-condition, both halves: it leaves the in-progress list,
     * and it comes back at the status it was left on -- which is what
     * `withdraw` leaving `status` untouched is for (D7b).
     */
    #[Test]
    public function a_course_set_aside_leaves_the_list_and_returns_at_the_status_it_left(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->for($student, 'student')
            ->status(CourseStatus::SyllabusDraft)
            ->create();

        $this->actingAs($student)
            ->post(route('courses.withdraw', $course))
            ->assertRedirect();

        $this->assertTrue($course->refresh()->isWithdrawn());
        $this->assertSame(CourseStatus::SyllabusDraft, $course->status);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('in_progress', 0)
                ->has('set_aside', 1)
                ->where('set_aside.0.standing', CourseStatus::Withdrawn->title())
                ->where('set_aside.0.detail', CourseStatus::SyllabusDraft->title())
                ->where('set_aside.0.resume_url', route('courses.resume', $course))
                ->where('set_aside.0.refusal', null),
            );

        $this->actingAs($student)
            ->post(route('courses.resume', $course))
            ->assertRedirect(route('courses.syllabus.show', $course));

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('in_progress', 1)->has('set_aside', 0));
    }

    /**
     * The other half: a card that cannot be picked back up says why instead of
     * offering a button that 422s (8.10.3).
     */
    #[Test]
    public function a_course_whose_subtopic_is_taken_says_so_rather_than_offering_a_button(): void
    {
        $student = User::factory()->create();

        $first = Course::factory()->for($student, 'student')->withdrawn()->create(['attempt' => 1]);
        Course::factory()->for($student, 'student')->status(CourseStatus::Active)->create(['attempt' => 2]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('set_aside', 1)
                ->where('set_aside.0.id', $first->id)
                ->where('set_aside.0.resume_url', null)
                ->where('set_aside.0.refusal', ResumeRefusal::SubtopicOccupied->explanation()),
            );

        // And the engine agrees, which is why the card must not offer it.
        $this->actingAs($student)
            ->post(route('courses.resume', $first))
            ->assertSessionHasErrors('resume');
    }

    #[Test]
    public function an_open_course_carries_the_way_to_set_it_aside(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->for($student, 'student')->status(CourseStatus::Active)->create();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('in_progress.0.withdraw_url', route('courses.withdraw', $course)),
            );
    }

    #[Test]
    public function a_course_that_has_already_ended_cannot_be_set_aside_again(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->for($student, 'student')->withdrawn()->create();

        $this->actingAs($student)
            ->post(route('courses.withdraw', $course))
            ->assertSessionHasErrors('withdraw');
    }

    #[Test]
    public function no_one_else_may_set_a_course_aside(): void
    {
        $course = Course::factory()->status(CourseStatus::Active)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('courses.withdraw', $course))
            ->assertForbidden();

        $this->assertTrue($course->refresh()->isOpen());
    }

    #[Test]
    public function no_one_elses_courses_appear(): void
    {
        Course::factory()->status(CourseStatus::Active)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('in_progress', 0));
    }
}
