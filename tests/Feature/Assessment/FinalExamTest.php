<?php

namespace Tests\Feature\Assessment;

use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\ExamUnusable;
use App\Assessment\FinalExam;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Courses\CourseStatus;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.3 -- "pass flips state; fail records attempt, course stays `active`".
 *
 * The two halves of the gate. Passing is the only thing in the system that can
 * stamp `completed_at`; failing has to leave the student holding a course they
 * can go on studying, which is ledger D7b's reason for there being no `failed`
 * end state at all.
 */
class FinalExamTest extends AssessmentTestCase
{
    #[Test]
    public function the_exam_covers_every_lesson_the_syllabus_teaches(): void
    {
        $course = $this->taughtCourse();

        $this->scriptModel([$this->examResponse()]);

        $exam = app(FinalExam::class)->open($course);

        $this->assertSame(QuizKind::Final, $exam->kind);
        $this->assertSame($course->getMorphClass(), $exam->quizzable_type);

        // Two questions per lesson, and every lesson reachable through one of
        // them -- checked in code, not hoped for in the prompt (6.3.1).
        $this->assertCount(2 * $course->lessons->count(), $exam->payload['quiz']['questions']);

        $concepts = SyllabusConcepts::of($course->approvedSyllabus);
        $covered = [];

        foreach (array_column($exam->payload['quiz']['questions'], 'concept_tag') as $tag) {
            $covered = [...$covered, ...$concepts->taughtIn($tag)];
        }

        foreach ($course->lessons as $lesson) {
            $this->assertContains($lesson->position, $covered);
        }
    }

    #[Test]
    public function an_exam_that_skips_a_lesson_is_refused_and_says_which(): void
    {
        $course = $this->taughtCourse();

        // Four questions, all of them about lesson 1 -- the right length and
        // the right shape, and still not an exam on this course.
        $lopsided = $this->examResponse();

        foreach ($lopsided['quiz']['questions'] as $index => $question) {
            $lopsided['quiz']['questions'][$index]['concept_tag'] = $index === 0 ? 'expression' : 'coefficient';
        }

        // Two attempts, both lopsided (spec 4.5.1's shape: one retry, then fail).
        $this->scriptModel([$lopsided, $lopsided]);

        try {
            app(FinalExam::class)->open($course);
            $this->fail('An exam that asks nothing from a lesson should be refused.');
        } catch (ExamUnusable $e) {
            $this->assertStringContainsString('lesson 2 (Keeping Both Sides Equal)', $e->getMessage());
        }

        // D10.4: a failed generation writes nothing and moves nothing.
        $this->assertSame(0, Quiz::query()->ofKind(QuizKind::Final)->count());
        $this->assertSame(CourseStatus::Active, $course->refresh()->status);
    }

    #[Test]
    public function an_exam_that_omits_declared_exam_concepts_still_passes(): void
    {
        $course = $this->taughtCourse();

        // Neither `variable` nor `equation` is asked about, and both are named
        // in `final_exam_concepts`. Every lesson is still examined, which is
        // the whole of the rule now (6.3.1).
        $partial = $this->examResponse();
        $partial['quiz']['questions'][0]['concept_tag'] = 'expression';
        $partial['quiz']['questions'][1]['concept_tag'] = 'coefficient';
        $partial['quiz']['questions'][2]['concept_tag'] = 'inverse-operation';
        $partial['quiz']['questions'][3]['concept_tag'] = 'inverse-operation';

        $this->assertSame(
            ['variable', 'equation', 'inverse-operation'],
            $course->approvedSyllabus->syllabus_json['final_exam_concepts'],
        );

        $this->scriptModel([$partial]);

        $exam = app(FinalExam::class)->open($course);

        $this->assertNotContains('variable', array_column($exam->payload['quiz']['questions'], 'concept_tag'));
    }

    #[Test]
    public function a_tag_the_lessons_declare_is_kept_and_an_invented_one_is_dropped(): void
    {
        $course = $this->taughtCourse();

        // `expression` is declared by a lesson section and is *not* in
        // `final_exam_concepts` -- before 6.3.3 it was stripped, and lesson 1
        // went uncovered with it.
        $mixed = $this->examResponse();
        $mixed['quiz']['questions'][0]['concept_tag'] = 'expression';
        $mixed['quiz']['questions'][1]['concept_tag'] = 'quadratic-formula';

        $this->scriptModel([$mixed]);

        $exam = app(FinalExam::class)->open($course);
        $tags = array_column($exam->payload['quiz']['questions'], 'concept_tag');

        $this->assertSame('expression', $tags[0]);
        $this->assertNull($tags[1]);
    }

    #[Test]
    public function an_exam_too_short_to_grade_against_a_fraction_is_refused(): void
    {
        $course = $this->taughtCourse();

        // Three questions still touching both lessons: correctly scoped, and
        // still ungradable -- at a 0.7 pass mark it silently demands a perfect
        // score, which is not the number anyone configured.
        $short = $this->examResponse();
        $short['quiz']['questions'] = array_slice($short['quiz']['questions'], 0, 3);

        $this->scriptModel([$short, $short]);

        try {
            app(FinalExam::class)->open($course);
            $this->fail('An exam too short to grade against a fraction should be refused.');
        } catch (ExamUnusable $e) {
            $this->assertStringContainsString('exactly 4 questions', $e->getMessage());
        }

        $this->assertSame(0, Quiz::query()->ofKind(QuizKind::Final)->count());
        $this->assertSame(CourseStatus::Active, $course->refresh()->status);
    }

    #[Test]
    public function a_course_with_lessons_left_may_not_open_its_exam(): void
    {
        $course = $this->activeCourse();
        $this->completeLesson($this->lesson($course, 1));

        $this->expectException(ExamNotAvailable::class);

        app(FinalExam::class)->open($course->refresh());
    }

    #[Test]
    public function passing_flips_the_course_to_completed(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        app(FinalExam::class)->open($course);

        $result = app(FinalExam::class)->sit($course->refresh(), $this->allCorrect());

        $this->assertTrue($result->passed);
        $this->assertSame(1.0, $result->sitting->score());
        $this->assertSame([], $result->sitting->conceptsMissed);
        $this->assertSame(CourseStatus::Completed, $result->standing);

        $course->refresh();

        $this->assertNotNull($course->completed_at);
        $this->assertFalse($course->isOpen());

        // `status` keeps the furthest point reached; the ending is the date
        // (ledger D7c).
        $this->assertSame(CourseStatus::FinalExam, $course->status);
    }

    #[Test]
    public function failing_records_the_attempt_and_leaves_the_course_active(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        $exam = app(FinalExam::class)->open($course);

        // Two of four is 0.5, below the 0.7 pass mark.
        $result = app(FinalExam::class)->sit($course->refresh(), [
            ...$this->allCorrect(), 'e2' => 'b', 'e3' => 'b',
        ]);

        $this->assertFalse($result->passed);
        $this->assertSame(2, $result->sitting->correct);
        $this->assertSame(4, $result->sitting->total);
        $this->assertSame(['expression', 'equation'], $result->sitting->conceptsMissed);
        $this->assertSame(CourseStatus::Active, $result->standing);

        $course->refresh();

        $this->assertNull($course->completed_at);
        $this->assertTrue($course->isOpen());
        $this->assertSame(CourseStatus::Active, $course->status);

        // One row per answer, all at the same sitting number.
        $attempts = QuizAttempt::query()->where('quiz_id', $exam->id)->get();

        $this->assertCount(4, $attempts);
        $this->assertSame([1], array_values(array_unique($attempts->pluck('attempt')->all())));
    }

    #[Test]
    public function an_unanswered_question_is_a_wrong_one(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        app(FinalExam::class)->open($course);

        // Only the answers the student was confident about. Omission must not
        // raise the score.
        $result = app(FinalExam::class)->sit($course->refresh(), ['e1' => 'a']);

        $this->assertSame(4, $result->sitting->total);
        $this->assertSame(1, $result->sitting->correct);
        $this->assertFalse($result->passed);
    }

    #[Test]
    public function a_re_sit_is_the_same_exam_at_the_next_sitting_number(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        $first = app(FinalExam::class)->open($course);

        app(FinalExam::class)->sit($course->refresh(), ['e1' => 'b', 'e2' => 'b', 'e3' => 'b']);

        // Nothing is generated a second time -- the model is not scripted for
        // another call, so a regeneration would fail this test loudly.
        $second = app(FinalExam::class)->open($course->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Quiz::query()->ofKind(QuizKind::Final)->count());

        $result = app(FinalExam::class)->sit($course->refresh(), $this->allCorrect());

        $this->assertTrue($result->passed);
        $this->assertSame(2, $result->sitting->number);
    }

    #[Test]
    public function a_malformed_answer_writes_no_part_of_the_sitting(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        $exam = app(FinalExam::class)->open($course);

        $this->actingAs($course->student)
            ->postJson("/courses/{$course->id}/final-exam/sittings", [
                'answers' => ['e1' => 'a', 'nope' => 'a', 'e3' => 'a'],
            ])
            ->assertStatus(422);

        // Not a partial sitting the student's real attempt then has to be
        // numbered around: nothing was recorded, and the course has not moved.
        $this->assertSame(0, QuizAttempt::query()->where('quiz_id', $exam->id)->count());
        $this->assertSame(CourseStatus::FinalExam, $course->refresh()->status);
        $this->assertTrue($course->isOpen());

        $result = app(FinalExam::class)->sit($course, ['e1' => 'a', 'e2' => 'a', 'e3' => 'a']);

        $this->assertSame(1, $result->sitting->number);
    }

    #[Test]
    public function a_completed_course_cannot_sit_its_exam_again(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        app(FinalExam::class)->open($course);
        app(FinalExam::class)->sit($course->refresh(), $this->allCorrect());

        $this->expectException(ExamNotAvailable::class);

        app(FinalExam::class)->sit($course->refresh(), $this->allCorrect());
    }

    #[Test]
    public function the_served_exam_carries_no_answer_key(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);

        // The POST now carries nothing of the exam at all: it asks for the
        // writing and answers 202 (ledger D25), so the key cannot travel on
        // it by construction rather than by remembering to strip it.
        $this->actingAs($course->student)
            ->postJson("/courses/{$course->id}/final-exam")
            ->assertStatus(202)
            ->assertJsonMissingPath('exam');

        $this->assertSame(CourseStatus::FinalExam, $course->refresh()->status);

        // The screen is where the exam is served, so that is where the
        // no-key rule now has to hold.
        foreach ($this->inertiaProps($this->actingAs($course->student)
            ->get("/courses/{$course->id}/final-exam"))['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
            $this->assertArrayNotHasKey('explanation', $question);
        }

        // And a failed sitting does not disclose it either -- what comes back
        // is the score and the concepts missed (ledger D7b).
        $result = $this->actingAs($course->student)
            ->postJson("/courses/{$course->id}/final-exam/sittings", [
                'answers' => array_map(static fn (): string => 'b', $this->allCorrect()),
            ])
            ->assertOk()
            ->json();

        $this->assertFalse($result['passed']);
        $this->assertSame(['variable', 'expression', 'equation', 'inverse-operation'], $result['concepts_missed']);
        $this->assertArrayNotHasKey('correct_option_id', $result);
        $this->assertArrayNotHasKey('explanation', $result);
    }

    #[Test]
    public function a_course_that_is_not_ready_answers_422_rather_than_moving(): void
    {
        $course = $this->activeCourse();

        $this->actingAs($course->student)
            ->postJson("/courses/{$course->id}/final-exam")
            ->assertStatus(422);

        $this->assertSame(CourseStatus::Active, $course->refresh()->status);
    }

    #[Test]
    public function another_student_may_not_open_or_sit_this_exam(): void
    {
        $course = $this->taughtCourse();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson("/courses/{$course->id}/final-exam")->assertForbidden();
        $this->actingAs($stranger)->postJson("/courses/{$course->id}/final-exam")->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/courses/{$course->id}/final-exam/sittings", ['answers' => ['e1' => 'a']])
            ->assertForbidden();
    }

    #[Test]
    public function the_exam_is_the_only_final_quiz_the_service_will_hold(): void
    {
        $course = $this->taughtCourse();
        $this->scriptModel([$this->examResponse()]);
        $first = app(FinalExam::class)->open($course);

        $second = app(Quizzes::class)->create(QuizKind::Final, $course, $course->student, $course, $this->examResponse());

        $this->assertSame($first->id, $second->id);
    }

    /**
     * Every question answered correctly.
     *
     * @return array<string, string>
     */
    private function allCorrect(): array
    {
        $answers = [];

        foreach ($this->examResponse()['quiz']['questions'] as $question) {
            $answers[$question['id']] = 'a';
        }

        return $answers;
    }
}
