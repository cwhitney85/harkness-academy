<?php

namespace Tests\Feature\Assessment;

use App\Assessment\Exceptions\UnansweredQuestion;
use App\Assessment\QuizDocument;
use App\Assessment\QuizGrader;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.1 -- "four types create rows via one service".
 *
 * The done-condition is a claim about writers, not about rows: any number of
 * classes can insert into `quizzes`, and the point of 6.1 is that exactly one
 * does. So the tests here go through `Quizzes` for the two kinds package 6
 * owns, and assert that package 5's two kinds arrive by the same door.
 */
class QuizServiceTest extends AssessmentTestCase
{
    #[Test]
    public function all_four_kinds_are_created_through_the_one_service(): void
    {
        $course = $this->activeCourse();
        $lesson = $this->lesson($course);
        $quizzes = app(Quizzes::class);

        $created = [
            $quizzes->create(QuizKind::Checkpoint, $lesson, $course, $this->checkpointResponse(1), 1),
            $quizzes->create(QuizKind::Homework, $lesson, $course, $this->quizResponse()),
            $quizzes->create(QuizKind::Final, $course, $course, $this->quizResponse()),
            $quizzes->create(QuizKind::Refresher, $lesson, $course, $this->quizResponse()),
        ];

        $this->assertSame(
            [QuizKind::Checkpoint, QuizKind::Homework, QuizKind::Final, QuizKind::Refresher],
            array_map(static fn (Quiz $quiz): QuizKind => $quiz->fresh()->kind, $created),
        );

        $this->assertSame(4, Quiz::query()->count());

        // The two denormalised columns come from the anchoring course, never
        // from the caller -- a quiz filed against someone else's course is not
        // a state this service can be talked into.
        foreach ($created as $quiz) {
            $this->assertSame($course->user_id, $quiz->user_id);
            $this->assertSame($course->id, $quiz->course_id);
        }
    }

    #[Test]
    public function package_fives_two_kinds_arrive_through_the_same_service(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        // A checkpoint per section, written by 5.2.1's generator, and one
        // homework, written by 5.4.1's chain -- both now created by Quizzes.
        $this->assertSame(2, $lesson->quizzes()->ofKind(QuizKind::Checkpoint)->count());
        $this->assertSame(1, $lesson->quizzes()->ofKind(QuizKind::Homework)->count());

        $this->assertNotNull(app(Quizzes::class)->existing(QuizKind::Homework, $lesson));
    }

    #[Test]
    public function a_second_quiz_for_an_exclusive_scope_adopts_the_first(): void
    {
        $course = $this->activeCourse();
        $lesson = $this->lesson($course);
        $quizzes = app(Quizzes::class);

        $first = $quizzes->create(QuizKind::Homework, $lesson, $course, $this->quizResponse());
        $second = $quizzes->create(QuizKind::Homework, $lesson, $course, $this->quizResponse());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Quiz::query()->ofKind(QuizKind::Homework)->count());
    }

    #[Test]
    public function a_scope_may_hold_any_number_of_refreshers(): void
    {
        $course = $this->activeCourse();
        $lesson = $this->lesson($course);
        $quizzes = app(Quizzes::class);

        $first = $quizzes->create(QuizKind::Refresher, $lesson, $course, $this->quizResponse());
        $second = $quizzes->create(QuizKind::Refresher, $lesson, $course, $this->quizResponse());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($second->id, $quizzes->latest(QuizKind::Refresher, $lesson)->id);
    }

    #[Test]
    public function the_enum_and_the_index_agree_about_which_kinds_repeat(): void
    {
        $repeatable = array_values(array_filter(
            QuizKind::cases(),
            static fn (QuizKind $kind): bool => $kind->isRepeatable(),
        ));

        $this->assertSame([QuizKind::Refresher], $repeatable);

        // The index predicate is the other half of the same statement. If one
        // is edited without the other, a repeatable kind either collides on
        // insert or an exclusive one silently stops being exclusive.
        $definition = DB::selectOne(
            "select indexdef from pg_indexes where indexname = 'quizzes_one_per_scope'"
        )->indexdef;

        $this->assertStringContainsString("<> 'refresher'", $definition);
    }

    #[Test]
    public function a_section_index_is_required_exactly_when_the_kind_is_section_scoped(): void
    {
        $course = $this->activeCourse();
        $lesson = $this->lesson($course);
        $quizzes = app(Quizzes::class);

        $this->assertTrue(QuizKind::Checkpoint->isSectionScoped());

        try {
            $quizzes->create(QuizKind::Checkpoint, $lesson, $course, $this->checkpointResponse(1));
            $this->fail('A checkpoint without a section index should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('needs a section index', $e->getMessage());
        }

        try {
            $quizzes->create(QuizKind::Final, $course, $course, $this->quizResponse(), 1);
            $this->fail('A final exam with a section index should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has no section to index', $e->getMessage());
        }

        $this->assertSame(0, Quiz::query()->count());
    }

    #[Test]
    public function one_reader_strips_the_answer_key_from_both_contracts(): void
    {
        foreach ([
            [QuizKind::Checkpoint, $this->checkpointResponse(1)],
            [QuizKind::Homework, $this->quizResponse()],
        ] as [$kind, $payload]) {
            $client = QuizDocument::in($payload, $kind)->clientPayload();

            foreach ($client[$kind->envelope()]['questions'] as $question) {
                $this->assertArrayNotHasKey('correct_option_id', $question, "[{$kind->value}] leaked the key.");
                $this->assertArrayNotHasKey('explanation', $question, "[{$kind->value}] leaked the explanation.");
                $this->assertArrayHasKey('options', $question);
            }

            // The stored form is untouched -- 5.2.2's lossless round-trip.
            $this->assertSame($payload, QuizDocument::in($payload, $kind)->payload());
        }
    }

    #[Test]
    public function a_document_reports_what_makes_it_ungradable_rather_than_throwing(): void
    {
        $payload = $this->quizResponse();
        $payload['quiz']['questions'][0]['correct_option_id'] = 'nonexistent';

        $this->assertSame(
            ['Question [h1] keys an option it does not offer.'],
            QuizDocument::in($payload, QuizKind::Homework)->defects(),
        );

        $this->assertSame([], QuizDocument::in($this->quizResponse(), QuizKind::Homework)->defects());
    }

    #[Test]
    public function grading_is_code_and_reads_the_stored_key(): void
    {
        $course = $this->activeCourse();
        $lesson = $this->lesson($course);

        $quiz = app(Quizzes::class)->create(QuizKind::Homework, $lesson, $course, $this->quizResponse());

        $graded = app(QuizGrader::class)->grade($quiz, $course->student, 'h1', 'a');

        $this->assertTrue($graded->correct);
        $this->assertSame('a', $graded->correctOptionId);
        $this->assertSame(1, $graded->attempt->attempt);

        $again = app(QuizGrader::class)->grade($quiz, $course->student, 'h1', 'b');

        $this->assertFalse($again->correct);
        $this->assertSame(2, $again->attempt->attempt);
        $this->assertSame(2, QuizAttempt::query()->where('quiz_id', $quiz->id)->count());
    }

    #[Test]
    public function an_answer_that_does_not_fit_its_question_is_a_malformed_request(): void
    {
        $course = $this->activeCourse();
        $quiz = app(Quizzes::class)->create(QuizKind::Homework, $this->lesson($course), $course, $this->quizResponse());

        $this->expectException(UnansweredQuestion::class);

        app(QuizGrader::class)->grade($quiz, $course->student, 'h1', 'not-an-option');
    }

    #[Test]
    public function a_final_exam_refuses_to_be_graded_one_answer_at_a_time(): void
    {
        $course = $this->activeCourse();
        $quiz = app(Quizzes::class)->create(QuizKind::Final, $course, $course, $this->quizResponse());

        $this->expectException(LogicException::class);

        app(QuizGrader::class)->grade($quiz, $course->student, 'h1', 'a');
    }
}
