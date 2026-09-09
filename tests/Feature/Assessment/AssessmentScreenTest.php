<?php

namespace Tests\Feature\Assessment;

use App\Assessment\FinalExam;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Courses\CourseStatus;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use Database\Seeders\CourseCatalogSeeder;
use Database\Seeders\InterfaceFixtureSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.9 -- the assessment screens: S8, S9, S10 and S11.
 *
 * Read against 8.2's fixture rather than against quizzes built here, because
 * the states these screens have to draw are the ones the fixture exists to
 * hold: homework never opened, a course standing in front of a shut exam, and
 * a course that failed a sitting and came back to pass it.
 *
 * What is asserted is what the server can settle -- which state, and what does
 * and does not cross the wire. The photocopy stock, the blue book's rhythm and
 * both at 390px are the browser's half of these done-conditions and were
 * checked at 1440px and 390px in the session.
 */
class AssessmentScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');

        $this->seed([CourseCatalogSeeder::class, InterfaceFixtureSeeder::class]);
    }

    /**
     * 8.9.1 -- "the pre-answer DOM contains no `explanation` or
     * `correct_option_id`".
     *
     * Both halves: the rendered page holds neither field name nor the text of
     * an answer the student has not earned, and the questions that did arrive
     * carry no key of their own.
     */
    #[Test]
    public function unanswered_homework_reaches_the_sheet_with_no_answer_key(): void
    {
        $course = $this->course('conversational-spanish');
        $lesson = $course->lessons()->where('position', 1)->sole();

        $response = $this->actingAs($course->student)
            ->get(route('lessons.homework.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('lessons/homework'));

        $response->assertDontSee('correct_option_id', escape: false);
        $response->assertDontSee('Four in the afternoon is tardes', escape: false);

        $homework = $this->inertiaProps($response)['homework'];

        $this->assertSame('ready', $this->inertiaProps($response)['state']);
        $this->assertCount(4, $homework['questions']);

        foreach ($homework['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
            $this->assertArrayNotHasKey('explanation', $question);
        }

        // Nothing is answered, so the sheet has no verdict to draw either.
        $this->assertSame(
            [false, false, false, false],
            array_column($homework['progress'], 'answered'),
        );
    }

    /**
     * 8.9.1 -- the homework the completion chain has not written yet is a
     * state of this screen, not a missing page (5.4.5).
     */
    #[Test]
    public function homework_that_has_not_been_written_yet_renders_as_waiting(): void
    {
        $course = $this->course('conversational-spanish');
        $lesson = $course->lessons()->where('position', 2)->sole();

        $props = $this->inertiaProps(
            $this->actingAs($course->student)
                ->get(route('lessons.homework.show', $lesson))
                ->assertOk()
        );

        $this->assertSame('pending', $props['state']);
        $this->assertNull($props['homework']);
    }

    /**
     * 8.9.2 -- "closed book → opened, sat whole, handed in once".
     *
     * The first two of the three: a course that has not opened its exam is
     * shown a shut book carrying no questions, and asking opens it.
     */
    #[Test]
    public function the_exam_is_handed_over_shut_and_opened_by_asking(): void
    {
        $course = $this->course('ordering-food-in-spanish');

        $closed = $this->inertiaProps($this->visitExam($course));

        $this->assertSame('closed', $closed['state']);
        $this->assertSame([], $closed['questions']);
        $this->assertFalse($closed['resit']);
        $this->assertNull($closed['sitting']);
        $this->assertSame(0.7, $closed['pass_mark']);

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.store', $course))
            // 202: the writing is queued (ledger D25). On the `sync` driver
            // this suite runs, it has finished by the time this returns.
            ->assertStatus(202);

        $open = $this->inertiaProps($this->visitExam($course));

        $this->assertSame('open', $open['state']);
        $this->assertCount(10, $open['questions']);
        $this->assertSame(CourseStatus::FinalExam, $course->refresh()->status);
    }

    /**
     * 8.9.2 -- "no per-question feedback appears before hand-in" (6.3, D20).
     */
    #[Test]
    public function the_open_exam_discloses_nothing_before_it_is_handed_in(): void
    {
        $course = $this->openExam();

        $response = $this->visitExam($course);

        $response->assertDontSee('correct_option_id', escape: false);
        $response->assertDontSee('explanation', escape: false);

        foreach ($this->inertiaProps($response)['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
            $this->assertArrayNotHasKey('explanation', $question);
        }
    }

    /**
     * 8.9.3 -- "pass and fail each render; a re-sit reopens the same exam".
     *
     * The fail half, walked as the student walks it: hand the book in wrong,
     * read the gate, ask for the book again.
     */
    #[Test]
    public function a_failed_sitting_renders_its_gate_and_a_re_sit_reopens_the_same_exam(): void
    {
        $course = $this->openExam();
        $exam = app(FinalExam::class)->exam($course);

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.sittings.store', $course), [
                'answers' => $this->everyQuestionWrong($exam),
            ])
            ->assertOk();

        $result = $this->inertiaProps($this->visitExam($course));

        $this->assertSame('result', $result['state']);
        $this->assertFalse($result['sitting']['passed']);
        $this->assertSame(1, $result['sitting']['sitting']);
        $this->assertSame(0, $result['sitting']['correct']);
        $this->assertNotSame([], $result['sitting']['concepts_missed']);

        // The gate holds the course open rather than ending it (D7b), and the
        // rail's lessons are the whole of what "go and study" means here.
        $this->assertSame(CourseStatus::Active, $course->refresh()->status);
        $this->assertCount(5, $result['course']['lessons']);

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.store', $course))
            // 202: the writing is queued (ledger D25). On the `sync` driver
            // this suite runs, it has finished by the time this returns.
            ->assertStatus(202);

        $again = $this->inertiaProps($this->visitExam($course));

        $this->assertSame('open', $again['state']);
        $this->assertTrue($again['resit'], 'A book already sat opens with no ceremony (8.4.4).');
        $this->assertSame($exam->id, app(FinalExam::class)->exam($course->refresh())->id);
    }

    /**
     * 8.9.3 -- the pass half, from the fixture's re-sat course (8.2.7).
     */
    #[Test]
    public function a_passed_sitting_renders_its_gate(): void
    {
        $course = $this->course('spanish-pronunciation');

        $props = $this->inertiaProps($this->visitExam($course));

        $this->assertSame('result', $props['state']);
        $this->assertTrue($props['sitting']['passed']);
        $this->assertSame(2, $props['sitting']['sitting'], 'The fixture failed once and came back.');
        $this->assertSame(CourseStatus::Completed, $course->standing());
    }

    /**
     * 8.9.4 -- "lesson, subtopic and module grains render from one component".
     *
     * The rows are written rather than generated: what the grain decides is
     * which documents the model reads (6.4, 6.5), and that is settled long
     * before this screen. What is under test is that all three arrive at one.
     */
    #[Test]
    public function every_refresher_grain_lands_on_one_screen(): void
    {
        $course = $this->course('spanish-pronunciation');
        $lesson = $course->lessons()->where('position', 1)->sole();
        $module = Module::query()->where('slug', 'language')->sole();

        $grains = [
            'lesson' => $this->refresher('lesson', $lesson, $course),
            'subtopic' => $this->refresher('subtopic', $course, $course),
            'module' => $this->refresher('module', $module, null),
        ];

        foreach ($grains as $grain => $refresher) {
            $props = $this->inertiaProps(
                $this->actingAs($course->student)
                    ->get(route('refreshers.show', $refresher))
                    ->assertOk()
                    ->assertInertia(fn (AssertableInertia $page) => $page->component('refreshers/show'))
            );

            $this->assertSame("A warm-up on {$grain}", $props['refresher']['scope']);
            $this->assertArrayNotHasKey('correct_option_id', $props['refresher']['questions'][0]);
        }

        // The module grain hangs off no course, and the sheet says so rather
        // than printing a course it does not have (6.5).
        $this->assertNull(
            $this->inertiaProps($this->actingAs($course->student)->get(route('refreshers.show', $grains['module'])))['course'],
        );
    }

    /**
     * A course that has not sat its exam yet is not shown one at all -- the
     * resume table decides where it goes instead (8.6).
     */
    #[Test]
    public function a_course_with_lessons_still_owed_is_sent_back_to_the_lesson(): void
    {
        $course = $this->course('conversational-spanish');

        $this->actingAs($course->student)
            ->get(route('courses.final-exam.show', $course))
            ->assertRedirect(route('lessons.show', $course->lessons()->where('position', 2)->sole()));
    }

    private function course(string $slug): Course
    {
        return Course::query()->where('subtopic_slug', $slug)->sole();
    }

    /** The fixture's unsat course, with its book open in front of the student. */
    private function openExam(): Course
    {
        $course = $this->course('ordering-food-in-spanish');

        $this->actingAs($course->student)
            ->postJson(route('courses.final-exam.store', $course))
            // 202: the writing is queued (ledger D25). On the `sync` driver
            // this suite runs, it has finished by the time this returns.
            ->assertStatus(202);

        return $course->refresh();
    }

    private function visitExam(Course $course): TestResponse
    {
        return $this->actingAs($course->student)
            ->get(route('courses.final-exam.show', $course))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('courses/final-exam'));
    }

    /**
     * An answer to every question that is not the one the key names.
     *
     * @return array<string, string>
     */
    private function everyQuestionWrong(Quiz $exam): array
    {
        $answers = [];

        foreach ($exam->payload['quiz']['questions'] as $question) {
            $wrong = array_values(array_filter(
                $question['options'],
                static fn (array $option): bool => $option['id'] !== $question['correct_option_id'],
            ));

            $answers[$question['id']] = $wrong[0]['id'];
        }

        return $answers;
    }

    /** One refresher row at the given grain, written as 6.4 would write it. */
    private function refresher(string $grain, Model $quizzable, ?Course $course): Quiz
    {
        return app(Quizzes::class)->create(
            QuizKind::Refresher,
            $quizzable,
            $this->course('spanish-pronunciation')->student,
            $course,
            [
                'quiz' => [
                    'scope' => "A warm-up on {$grain}",
                    'questions' => [[
                        'id' => "r-{$grain}",
                        'prompt' => 'Which vowel does Spanish never glide?',
                        'options' => [
                            ['id' => 'a', 'text' => '«e», every time'],
                            ['id' => 'b', 'text' => 'None of them; all five are pure'],
                            ['id' => 'c', 'text' => '«o», before another vowel'],
                            ['id' => 'd', 'text' => '«i», at the end of a word'],
                        ],
                        'correct_option_id' => 'b',
                        'explanation' => 'All five Spanish vowels are pure.',
                        'concept_tag' => 'pure-vowels',
                    ]],
                    'suggested_reading' => [],
                ],
            ],
        );
    }
}
