<?php

namespace Tests\Feature\Assessment;

use App\Assessment\FinalExam;
use App\Courses\CourseStatus;
use App\Models\Course;
use App\Models\ExamAnswerSheet;
use App\Models\QuizAttempt;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.3.4 -- a half-finished exam is a saved answer sheet (ledger D26).
 *
 * Every other quiz in the system saves itself as it is answered. The exam
 * cannot be graded a question at a time (D20), and that is the only reason it
 * did not save at all: what a student had ticked and not yet handed in lived
 * in React state and died with the tab.
 *
 * Saving and disclosing are separable, and only disclosure was ever the
 * constraint. What these tests hold is both halves of that: the ticks survive,
 * and nothing about the answer key travels with them.
 */
class AnswerSheetTest extends AssessmentTestCase
{
    #[Test]
    public function the_ticks_survive_a_reload(): void
    {
        $course = $this->seatedCourse();

        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'a', 'e3' => 'b']])
            ->assertOk()
            ->assertJson(['saved' => 2]);

        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get("/courses/{$course->id}/final-exam"),
        );

        $this->assertSame('open', $props['state']);
        $this->assertSame(['e1' => 'a', 'e3' => 'b'], (array) $props['sheet']);

        // Selections, and nothing else: a sheet is not an attempt, and
        // `QuizGrader` is still the only writer of `quiz_attempts`.
        $this->assertSame(0, QuizAttempt::query()->count());
        $this->assertSame([], array_diff(
            array_keys(ExamAnswerSheet::query()->firstOrFail()->getAttributes()),
            ['id', 'quiz_id', 'user_id', 'attempt', 'selections', 'created_at', 'updated_at'],
        ));
    }

    #[Test]
    public function a_later_save_replaces_the_sheet_rather_than_adding_to_it(): void
    {
        $course = $this->seatedCourse();

        foreach ([['e1' => 'a'], ['e1' => 'b', 'e2' => 'c'], ['e2' => 'c']] as $answers) {
            $this->actingAs($course->student)->postJson($this->url($course), ['answers' => $answers])->assertOk();
        }

        // The last save is the student's last word -- un-ticking an answer is
        // an ordinary save rather than a second endpoint.
        $this->assertSame(1, ExamAnswerSheet::query()->count());
        $this->assertSame(['e2' => 'c'], app(FinalExam::class)->sheet($course));
    }

    #[Test]
    public function handing_in_clears_the_sheet_and_a_re_sit_opens_an_empty_book(): void
    {
        $course = $this->seatedCourse();

        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'a', 'e2' => 'b']])
            ->assertOk();

        // Two of four is below the 0.7 mark, so the course comes back open.
        $this->actingAs($course->student)
            ->postJson("/courses/{$course->id}/final-exam/sittings", ['answers' => ['e1' => 'a', 'e2' => 'a']])
            ->assertOk();

        $this->assertSame(0, ExamAnswerSheet::query()->count());
        $this->assertSame(CourseStatus::Active, $course->refresh()->status);

        // The result renders on a plain reload, with no draft left behind.
        $props = $this->inertiaProps(
            $this->actingAs($course->student)->get("/courses/{$course->id}/final-exam"),
        );

        $this->assertSame('result', $props['state']);
        $this->assertSame([], (array) $props['sheet']);

        // The re-sit is the same exam at the next sitting, and its sheet is
        // its own: the first sitting's ticks are not on it.
        app(FinalExam::class)->open($course->refresh());

        $this->assertSame([], app(FinalExam::class)->sheet($course->refresh()));

        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e4' => 'a']])
            ->assertOk();

        $this->assertSame(2, ExamAnswerSheet::query()->firstOrFail()->attempt);
    }

    #[Test]
    public function a_selection_the_exam_does_not_offer_is_refused_and_saves_nothing(): void
    {
        $course = $this->seatedCourse();

        // The same rule grading applies, so a sheet cannot be filled in with
        // what hand-in would then refuse to mark.
        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'a', 'nope' => 'a']])
            ->assertStatus(422);

        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'z']])
            ->assertStatus(422);

        $this->assertSame(0, ExamAnswerSheet::query()->count());
    }

    #[Test]
    public function a_book_that_is_not_open_cannot_be_written_on(): void
    {
        $course = $this->taughtCourse();

        // Ready to open, but not opened: there is no sitting to be part-way
        // through, and 422 says which of the three checks failed.
        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'a']])
            ->assertStatus(422);

        $this->assertSame(0, ExamAnswerSheet::query()->count());
    }

    #[Test]
    public function one_students_sheet_is_never_another_students_to_read_or_write(): void
    {
        $course = $this->seatedCourse();
        $stranger = User::factory()->create();

        $this->actingAs($course->student)
            ->postJson($this->url($course), ['answers' => ['e1' => 'a']])
            ->assertOk();

        $this->actingAs($stranger)
            ->postJson($this->url($course), ['answers' => ['e1' => 'b']])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get("/courses/{$course->id}/final-exam")
            ->assertForbidden();

        $this->assertSame(['e1' => 'a'], app(FinalExam::class)->sheet($course));
        $this->assertSame($course->student->id, ExamAnswerSheet::query()->firstOrFail()->user_id);
    }

    /**
     * A course sitting its exam, with the book open in front of the student.
     */
    private function seatedCourse(): Course
    {
        $course = $this->taughtCourse();

        $this->scriptModel([$this->examResponse()]);

        app(FinalExam::class)->open($course);

        return $course->refresh();
    }

    private function url(Course $course): string
    {
        return "/courses/{$course->id}/final-exam/answers";
    }
}
