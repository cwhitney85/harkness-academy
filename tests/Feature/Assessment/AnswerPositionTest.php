<?php

namespace Tests\Feature\Assessment;

use App\Assessment\QuestionSchema;
use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.6 -- where the correct answer sits is ours, not the model's.
 *
 * The dogfood finding was that checkpoints clustered on A and B, which is what
 * a model asked for one right answer among four does. The fixed set below is
 * that clustering at its worst: forty questions whose key is the first option
 * every time. What comes out is what a student would see.
 */
class AnswerPositionTest extends AssessmentTestCase
{
    /** How many of the fixed set may key any one position (6.6). */
    private const CEILING = 0.4;

    #[Test]
    public function the_key_is_spread_across_positions_over_a_fixed_set(): void
    {
        $distributed = QuizDocument::in($this->clusteredQuiz(40), QuizKind::Homework)
            ->withDistributedAnswers();

        $positions = $this->keyPositions($distributed->questions());
        $questions = array_sum($positions);

        $this->assertSame(40, $questions);
        $this->assertCount(QuestionSchema::MAX_OPTIONS, $positions, 'A position was never chosen.');

        foreach ($positions as $position => $count) {
            $this->assertLessThanOrEqual(
                self::CEILING * $questions,
                $count,
                "Position {$position} keys {$count} of {$questions} questions.",
            );
        }
    }

    #[Test]
    public function only_the_order_changes(): void
    {
        $original = $this->clusteredQuiz(8);
        $distributed = QuizDocument::in($original, QuizKind::Homework)->withDistributedAnswers();

        foreach ($distributed->questions() as $index => $question) {
            $before = $original['quiz']['questions'][$index];

            $this->assertSame($before['id'], $question['id']);
            $this->assertSame($before['correct_option_id'], $question['correct_option_id']);
            $this->assertSame($before['explanation'], $question['explanation']);

            // The same options, still one each -- a question that lost or
            // gained one would be a different question.
            $this->assertEqualsCanonicalizing($before['options'], $question['options']);
        }
    }

    #[Test]
    public function a_question_whose_key_names_nothing_is_left_where_it_is(): void
    {
        // `defects()` reports this and every caller refuses on it. Moving an
        // option that is not the key would hide the defect behind a shuffle.
        $payload = $this->clusteredQuiz(1);
        $payload['quiz']['questions'][0]['correct_option_id'] = 'nonexistent';

        $this->assertSame(
            $payload['quiz']['questions'],
            QuizDocument::in($payload, QuizKind::Homework)->withDistributedAnswers()->questions(),
        );
    }

    #[Test]
    public function the_service_distributes_what_it_stores(): void
    {
        $course = $this->activeCourse();

        $quiz = app(Quizzes::class)->create(
            QuizKind::Homework,
            $this->lesson($course),
            $course->student,
            $course,
            $this->clusteredQuiz(40),
        );

        // Every kind is written here (6.1), which is why the rule lives here
        // rather than at the four call sites that generate a document.
        $positions = $this->keyPositions(QuizDocument::of($quiz->fresh())->questions());

        $this->assertLessThanOrEqual(self::CEILING * 40, max($positions));
    }

    /**
     * How many questions key each option position.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<int, int>
     */
    private function keyPositions(array $questions): array
    {
        $positions = [];

        foreach ($questions as $question) {
            foreach (array_values($question['options']) as $position => $option) {
                if ($option['id'] === $question['correct_option_id']) {
                    $positions[$position] = ($positions[$position] ?? 0) + 1;
                }
            }
        }

        ksort($positions);

        return $positions;
    }

    /**
     * A quiz of `$count` four-option questions, every one keyed on option A.
     *
     * @return array<string, mixed>
     */
    private function clusteredQuiz(int $count): array
    {
        $questions = [];

        for ($n = 1; $n <= $count; $n++) {
            $questions[] = [
                'id' => "q{$n}",
                'prompt' => "Question {$n}.",
                'options' => [
                    ['id' => "q{$n}-a", 'text' => 'The right answer'],
                    ['id' => "q{$n}-b", 'text' => 'A plausible mistake'],
                    ['id' => "q{$n}-c", 'text' => 'Another plausible mistake'],
                    ['id' => "q{$n}-d", 'text' => 'A third plausible mistake'],
                ],
                'correct_option_id' => "q{$n}-a",
                'explanation' => "Because of question {$n}.",
                'concept_tag' => null,
            ];
        }

        return [
            'quiz' => [
                'scope' => 'A clustered quiz',
                'questions' => $questions,
                'suggested_reading' => [],
            ],
        ];
    }
}
