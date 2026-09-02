<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\LessonStatus;
use App\Courses\NextAction;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.10.1 -- screen S1.
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
            ->assertInertia(fn ($page) => $page->has('in_progress', 0));
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
