<?php

namespace App\Assessment;

/**
 * How many questions a quiz of a given purpose asks (spec 6.2, 6.3, 6.4).
 *
 * `json/quiz.v1` serves three purposes and they want different lengths, so the
 * count is stated here rather than in the schema: the shape is the contract,
 * the count is a property of what the quiz is for. A refresher is a warm-up, a
 * final exam is the last thing a student does in a course, and one range
 * covering both would be wrong for each.
 *
 * **The exam range is the one that carries weight.** `assessment.final_exam.pass_mark`
 * is a fraction, so what it demands in whole questions depends on how many
 * there are -- and until this class existed nothing fixed that number. An
 * unbounded exam meant a 0.7 pass mark could ask for 7 of 10 (70%) or 3 of 3
 * (100%) depending on how long the model felt like being. Narrowing the range
 * is what makes the configured number mean roughly one thing:
 *
 * | Questions | Needed to pass at 0.7 | Effective |
 * |---|---|---|
 * | 8 | 6 | 75.0% |
 * | 9 | 7 | 77.8% |
 * | 10 | 7 | 70.0% |
 * | 12 | 9 | 75.0% |
 *
 * A residual spread of 70–78% is inherent to counting whole questions; the
 * 70–100% it replaces was not.
 */
final readonly class QuizLength
{
    private function __construct(public int $min, public int $max) {}

    /**
     * Long enough that one question is not a large slice of the grade, short
     * enough to sit through on a phone (spec 6.3).
     */
    public static function exam(): self
    {
        return new self(8, 12);
    }

    /**
     * A lesson's worth of practice, aimed at what came out shaky (spec 6.2).
     */
    public static function homework(): self
    {
        return new self(3, 6);
    }

    /**
     * "Keep it short. A refresher is a warm-up, not an exam" (spec 6.4).
     */
    public static function refresher(): self
    {
        return new self(3, 5);
    }

    /**
     * The range as the prompt files state it.
     *
     * Prompt files are static text and cannot read a constant, so the numbers
     * appear in two places. This method is what a test compares them through,
     * the way LessonState publishes limits read from the classes that enforce
     * them (5.3): a prompt asking for a length the schema rejects would burn a
     * retry on every single call.
     */
    public function describe(): string
    {
        return "between {$this->min} and {$this->max} questions";
    }

    /**
     * How many right answers a fraction demands of a quiz this long.
     *
     * Post: the smallest whole number of correct answers scoring at or above
     *       `$passMark` -- the same comparison `FinalExam::sit` makes, so the
     *       two cannot disagree about what passing means.
     */
    public function toPass(int $questions, float $passMark): int
    {
        return (int) ceil($questions * $passMark - 1e-9);
    }
}
