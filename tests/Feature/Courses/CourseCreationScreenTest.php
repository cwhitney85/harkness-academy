<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\SkillLevel;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.7.1 and 8.7.6 -- screen S2.
 *
 * 8.7.1's done-condition is the bluntest in package 8: "a course row exists
 * after one pass with no developer touching the DB". Every test here goes
 * through the HTTP layer for exactly that reason -- package 4's engine has
 * been passing its own tests since August without a single course ever having
 * been created by a student.
 */
class CourseCreationScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);

        // Free text is checked against its module before a course exists
        // (4.9), and every free-text post below is about something other than
        // that check. Faked once, in scope, so those tests keep asserting what
        // they were written to assert -- and so none of them reaches a
        // provider, which `preventStrayPrompts` is what stops.
        $this->fakeModel(fn (): array => [
            'verdict' => 'in_scope',
            'module' => null,
            'reason' => 'In scope for the purposes of this test.',
        ]);
    }

    #[Test]
    public function the_picker_offers_the_catalog_and_the_three_levels(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('courses.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/create')
                ->has('modules')
                ->has('levels', 3)
                ->where('resumable', null)
                ->where('occupied', null),
            );
    }

    #[Test]
    public function one_pass_through_the_form_creates_a_course_from_the_catalog(): void
    {
        $student = User::factory()->create();
        $subtopic = $this->subtopic('language', 'spanish');

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'catalog',
                'subtopic_id' => $subtopic->id,
                'level' => 101,
            ])
            ->assertRedirect();

        $course = Course::query()->sole();

        $this->assertSame($student->id, $course->user_id);
        $this->assertSame('language', $course->module_slug);
        $this->assertSame('spanish', $course->subtopic_slug);
        $this->assertSame(SkillLevel::Introductory, $course->skill_level);
        $this->assertSame(CourseStatus::Created, $course->status);
        $this->assertSame(1, $course->attempt);
    }

    /**
     * The payload the screen actually sends, field for field.
     *
     * The tests around this one post the minimum the rules require, which is
     * not what a form submits: `useForm` sends every field it holds, so the
     * unused half of the either/or arrives empty -- and Laravel's global
     * `ConvertEmptyStringsToNull` turns empty into null before validation
     * sees it. A rule set that types those fields without allowing null
     * refuses a submission that named a catalog subtopic perfectly well.
     */
    #[Test]
    public function the_forms_own_payload_creates_a_course_from_the_catalog(): void
    {
        $student = User::factory()->create();
        $subtopic = $this->subtopic('history', 'medieval-history');

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'catalog',
                'module_slug' => 'history',
                'subtopic_id' => $subtopic->id,
                // The free-text field the student never touched.
                'name' => '',
                'level' => 101,
                'start_over' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('medieval-history', Course::query()->sole()->subtopic_slug);
    }

    #[Test]
    public function the_forms_own_payload_creates_a_course_from_free_text(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'history',
                // The catalog choice the student cleared by typing.
                'subtopic_id' => null,
                'name' => 'The Wars of the Roses',
                'level' => 201,
                'start_over' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('the-wars-of-the-roses', Course::query()->sole()->subtopic_slug);
    }

    #[Test]
    public function the_student_lands_on_the_knowledge_interview(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'catalog',
                'subtopic_id' => $this->subtopic('math', 'algebra')->id,
                'level' => 201,
            ])
            ->assertRedirect(route('courses.knowledge-interview.show', Course::query()->sole()));
    }

    #[Test]
    public function free_text_creates_a_course_under_the_chosen_module(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'language',
                'name' => 'Conversational Spanish',
                'level' => 101,
            ])
            ->assertRedirect();

        $course = Course::query()->sole();

        $this->assertSame('conversational-spanish', $course->subtopic_slug);
        $this->assertSame('Conversational Spanish', $course->subtopic_title);
    }

    #[Test]
    public function a_name_that_cannot_become_a_folder_is_refused_at_the_field(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'math',
                'name' => '。。。',
                'level' => 101,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Course::query()->count());
    }

    /**
     * 8.7.6 -- the question, not a second course.
     */
    #[Test]
    public function a_withdrawn_attempt_asks_d7bs_question_instead_of_creating(): void
    {
        $student = User::factory()->create();
        $withdrawn = Course::factory()
            ->for($student, 'student')
            ->status(CourseStatus::GoalsInterview)
            ->withdrawn()
            ->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'math',
                'name' => 'Algebra',
                'level' => 101,
            ])
            ->assertRedirect()
            ->assertSessionHas('resumable', fn (array $question): bool => $question['course_id'] === $withdrawn->id
                && $question['resume_url'] === route('courses.resume', $withdrawn));

        $this->assertSame(1, Course::query()->count(), 'The question replaces the second course, it does not follow it.');
    }

    /**
     * 8.7.6 -- picking up lands on the resume table's URL for that status.
     */
    #[Test]
    public function picking_up_resumes_the_course_where_it_was_left(): void
    {
        $student = User::factory()->create();
        $withdrawn = Course::factory()
            ->for($student, 'student')
            ->status(CourseStatus::GoalsInterview)
            ->withdrawn()
            ->create();

        $this->actingAs($student)
            ->post(route('courses.resume', $withdrawn))
            ->assertRedirect(route('courses.goals-interview.show', $withdrawn));

        $this->assertTrue($withdrawn->fresh()?->isOpen());
        $this->assertSame(CourseStatus::GoalsInterview, $withdrawn->fresh()?->status);
    }

    /**
     * 8.7.6 -- and starting over creates attempt + 1.
     */
    #[Test]
    public function starting_over_creates_the_next_attempt(): void
    {
        $student = User::factory()->create();
        Course::factory()->for($student, 'student')->withdrawn()->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'math',
                'name' => 'Algebra',
                'level' => 101,
                'start_over' => true,
            ])
            ->assertRedirect();

        $this->assertSame(2, Course::query()->count());
        $this->assertSame(2, Course::query()->orderByDesc('attempt')->first()?->attempt);
    }

    /**
     * A refusal that names which of the three checks failed (8.6), because
     * "a later attempt replaced it" asks the student for something different
     * from "your other course is open".
     */
    #[Test]
    public function a_superseded_attempt_says_so_rather_than_resuming(): void
    {
        $student = User::factory()->create();
        $old = Course::factory()->for($student, 'student')->withdrawn()->create();
        Course::factory()->for($student, 'student')->state(['attempt' => 2])->withdrawn()->create();

        $this->actingAs($student)
            ->post(route('courses.resume', $old))
            ->assertSessionHasErrors('resume');

        $this->assertTrue($old->fresh()?->isWithdrawn());
    }

    #[Test]
    public function an_open_course_in_the_subtopic_is_offered_rather_than_duplicated(): void
    {
        $student = User::factory()->create();
        $open = Course::factory()
            ->for($student, 'student')
            ->status(CourseStatus::SyllabusDraft)
            ->create();

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'free_text',
                'module_slug' => 'math',
                'name' => 'Algebra',
                'level' => 101,
            ])
            ->assertRedirect()
            ->assertSessionHas('occupied', fn (array $notice): bool => $notice['course_id'] === $open->id
                && $notice['continue_url'] === route('courses.syllabus.show', $open));

        $this->assertSame(1, Course::query()->count());
    }

    #[Test]
    public function no_one_may_resume_another_students_course(): void
    {
        $withdrawn = Course::factory()->withdrawn()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('courses.resume', $withdrawn))
            ->assertForbidden();
    }

    private function subtopic(string $module, string $slug): Subtopic
    {
        return Subtopic::query()
            ->where('module_id', Module::query()->where('slug', $module)->value('id'))
            ->where('slug', $slug)
            ->sole();
    }
}
