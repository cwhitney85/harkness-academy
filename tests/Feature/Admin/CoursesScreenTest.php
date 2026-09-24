<?php

namespace Tests\Feature\Admin;

use App\Admin\Removals;
use App\Assessment\QuizKind;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\GenerationKind;
use App\Courses\SkillLevel;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\ExamAnswerSheet;
use App\Models\GenerationRequest;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subtopic;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 17.2 -- Admin → Courses: every course with its standing and cost, one
 * course taken apart, and removing and restoring one.
 */
class CoursesScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Colin']);
    }

    #[Test]
    public function the_list_shows_every_course_with_its_standing_and_cost_as_sql_does(): void
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $active = Course::factory()->for($ada, 'student')->status(CourseStatus::Active)->create();
        $withdrawn = Course::factory()->for($ada, 'student')->withdrawn()->create(['subtopic_slug' => 'geometry', 'subtopic_title' => 'Geometry']);
        $removed = Course::factory()->for($ada, 'student')->create(['subtopic_slug' => 'calculus', 'subtopic_title' => 'Calculus', 'skill_level' => SkillLevel::Intermediate]);
        app(Removals::class)->remove($this->admin, $removed, 'test data');

        UsageEvent::factory()->for($ada)->create(['course_id' => $active->id, 'cost_usd' => 0.25]);
        UsageEvent::factory()->for($ada)->create(['course_id' => $removed->id, 'cost_usd' => 0.5]);
        UsageEvent::factory()->for($ada)->actingAs($this->admin->id)->create(['course_id' => $active->id, 'cost_usd' => 4]);

        $sql = collect(DB::select(<<<'SQL'
            select c.id, coalesce(sum(e.cost_usd), 0) as cost
            from courses c left join usage_events e on e.course_id = c.id and e.acting_user_id is null
            group by c.id order by c.id desc
        SQL))->map(fn ($row) => [$row->id, (float) $row->cost])->all();

        $this->actingAs($this->admin)->get('/admin/courses')->assertInertia(fn (Assert $page) => $page
            ->component('admin/courses/index')
            ->where('courses.data', fn ($courses) => collect($courses)->map(fn ($c) => [$c['id'], $c['cost']])->all() == $sql)
            ->where('courses.data.0.standing', 'created')
            ->where('courses.data.0.removed', true)
            ->where('courses.data.0.student.name', 'Ada')
            ->where('courses.data.1.standing', 'withdrawn')
            ->where('courses.data.2.standing', 'active')
            ->where('courses.data.2.cost', 0.25));

        $ids = fn (string $query) => array_column($this->actingAs($this->admin)->get("/admin/courses?{$query}")->inertiaProps('courses.data'), 'id');

        $this->assertSame([$removed->id], $ids('standing=removed'));
        $this->assertSame([$withdrawn->id], $ids('standing=withdrawn'));
        $this->assertSame([$active->id], $ids('standing=active'));
        $this->assertSame([], $ids('standing=created'), 'A removed course is removed, whatever it was.');
        $this->assertSame([$removed->id], $ids('level=201'));
        $this->assertSame([], $ids('module=history'));
    }

    #[Test]
    public function the_page_shows_every_part_of_the_course(): void
    {
        $course = Course::factory()->status(CourseStatus::FinalExam)->create(['subtopic_title' => 'Algebra']);
        $tree = app(MemoryTree::class);
        $tree->put($course, CourseCriteria::pathFor($course), 'wants proofs');
        $tree->put($course, $course->workingKnowledgePath(), 'knows fractions');
        $tree->put($course, $course->workingKnowledgePath(), 'knows fractions and ratios');

        CourseSyllabus::factory()->for($course)->generation(1)->create();
        CourseSyllabus::factory()->for($course)->generation(2)->approved()->create(['feedback' => 'Fewer lessons']);

        $lesson = Lesson::factory()->for($course)->position(1)->create();
        $lesson->messages()->create(['turn_id' => Str::uuid(), 'kind' => 'section', 'speaker' => 'tutor', 'section_index' => 1, 'content' => 'Variables name numbers.', 'position' => 1, 'created_at' => now()]);
        $lesson->messages()->create(['turn_id' => Str::uuid(), 'kind' => 'qa', 'speaker' => 'student', 'section_index' => 1, 'content' => 'Why x?', 'position' => 2, 'created_at' => now()]);

        $checkpoint = $this->quiz($course, QuizKind::Checkpoint, $lesson, section: 1);
        $this->answer($checkpoint, 1, ['q1' => 'a', 'q2' => 'c']);
        $final = $this->quiz($course, QuizKind::Final, $course);
        $this->answer($final, 1, ['q1' => 'b', 'q2' => 'c']);
        ExamAnswerSheet::query()->create(['quiz_id' => $final->id, 'user_id' => $course->user_id, 'attempt' => 1, 'selections' => ['q1' => 'b', 'q2' => 'c']]);
        ExamAnswerSheet::query()->create(['quiz_id' => $final->id, 'user_id' => $course->user_id, 'attempt' => 2, 'selections' => ['q1' => 'a']]);

        GenerationRequest::open($course, GenerationKind::FinalExam)?->fail('The exam could not be written.');

        UsageEvent::factory()->for($course->student)->create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'feature' => 'lesson.section', 'cost_usd' => 0.5]);
        UsageEvent::factory()->for($course->student)->actingAs($this->admin->id)->create(['course_id' => $course->id, 'feature' => 'lesson.qa', 'cost_usd' => 2]);

        $this->actingAs($this->admin)->get("/admin/courses/{$course->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/courses/show')
            ->where('course.standing', 'final_exam')
            ->where('course.removal', null)
            ->where('criteria', 'wants proofs')
            ->where('working_knowledge', 'knows fractions and ratios')
            ->has('syllabi', 2)
            ->where('syllabi.1.feedback', 'Fewer lessons')
            ->where('syllabi.0.approved', null)
            ->where('lessons.0.title', 'Letters as Numbers')
            ->where('lessons.0.messages.1', ['id' => $lesson->messages()->where('position', 2)->value('id'), 'kind' => 'qa', 'speaker' => 'student', 'section' => 1, 'content' => 'Why x?'])
            ->where('quizzes.0.kind', 'checkpoint')
            ->where('quizzes.0.lesson', 1)
            ->where('quizzes.0.section', 1)
            ->where('quizzes.0.questions.0.correct', 'a')
            ->where('quizzes.0.sittings.0.correct', 1)
            ->where('quizzes.0.sittings.0.total', 2)
            ->where('quizzes.0.sittings.0.answers', ['q1' => 'a', 'q2' => 'c'])
            ->where('quizzes.1.kind', 'final')
            ->where('quizzes.1.lesson', null)
            ->where('quizzes.1.open', [['sitting' => 2, 'ticked' => 1]])
            ->where('generations.0.state', 'failed')
            ->where('generations.0.failure', 'The exam could not be written.')
            ->where('cost.total', 0.5)
            ->where('cost.features', [['feature' => 'lesson.section', 'calls' => 1, 'cost' => 0.5]])
            ->has('usage', 2)
            ->where('usage.0.acting', 'Colin')
            ->where('usage.1.lesson', 1)
            ->where('usage.1.acting', null));
    }

    #[Test]
    public function a_course_is_removed_and_restored_from_its_page_and_a_swept_one_says_what_took_it(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->admin)->post("/admin/courses/{$course->id}/remove", ['reason' => 'Test account'])->assertRedirect();
        $this->assertSoftDeleted($course);

        $this->actingAs($this->admin)->get("/admin/courses/{$course->id}")->assertInertia(fn (Assert $page) => $page
            ->where('course.removal.own', true)
            ->where('course.removal.what', 'course')
            ->where('course.removal.reason', 'Test account'));

        $removal = Course::withTrashed()->findOrFail($course->id)->removal_id;
        $this->actingAs($this->admin)->post("/admin/catalog/removals/{$removal}/restore", ['reason' => 'Mistake'])->assertRedirect();
        $this->assertNotSoftDeleted($course);

        app(Removals::class)->remove($this->admin, Subtopic::query()->findOrFail($course->subtopic_id), 'Off-topic');

        $this->actingAs($this->admin)->get("/admin/courses/{$course->id}")->assertInertia(fn (Assert $page) => $page
            ->where('course.removal.own', false)
            ->where('course.removal.what', 'subtopic'));
    }

    #[Test]
    public function removing_asks_why(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->admin)->post("/admin/courses/{$course->id}/remove")->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted($course);
    }

    private function quiz(Course $course, QuizKind $kind, Course|Lesson $quizzable, ?int $section = null): Quiz
    {
        $question = fn (string $id, string $correct) => [
            'id' => $id, 'prompt' => "Question {$id}", 'concept_tag' => 'variables', 'correct_option_id' => $correct, 'explanation' => 'Because.',
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B'], ['id' => 'c', 'text' => 'C'], ['id' => 'd', 'text' => 'D']],
        ];

        return Quiz::query()->create([
            'user_id' => $course->user_id,
            'course_id' => $course->id,
            'kind' => $kind,
            'quizzable_type' => $quizzable->getMorphClass(),
            'quizzable_id' => $quizzable->id,
            'section_index' => $section,
            'payload' => [$kind->envelope() => ['questions' => [$question('q1', 'a'), $question('q2', 'b')]]],
        ]);
    }

    /**
     * @param  array<string, string>  $answers  question id => option id; the key is `a` for q1, `b` for q2
     */
    private function answer(Quiz $quiz, int $sitting, array $answers): void
    {
        foreach ($answers as $question => $option) {
            QuizAttempt::query()->create([
                'quiz_id' => $quiz->id, 'user_id' => $quiz->user_id, 'attempt' => $sitting,
                'question_id' => $question, 'option_id' => $option, 'correct' => ['q1' => 'a', 'q2' => 'b'][$question] === $option, 'created_at' => now(),
            ]);
        }
    }
}
