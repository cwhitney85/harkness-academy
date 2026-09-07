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
 * **The exam's length is the one that carries weight.** `assessment.final_exam.pass_mark`
 * is a fraction, so what it demands in whole questions depends on how many
 * there are -- and until this class existed nothing fixed that number. An
 * unbounded exam meant a 0.7 pass mark could ask for 7 of 10 (70%) or 3 of 3
 * (100%) depending on how long the model felt like being.
 *
 * The exam asks **two questions per lesson** (spec 6.3.2), so its length is a
 * property of the course rather than a range:
 *
 * | Lessons | Questions | Needed to pass at 0.7 | Effective |
 * |---|---|---|---|
 * | 5 | 10 | 7 | 70.0% |
 * | 7 | 14 | 10 | 71.4% |
 * | 8 | 16 | 12 | 75.0% |
 * | 10 | 20 | 14 | 70.0% |
 *
 * A residual spread is inherent to counting whole questions; the 70-100% an
 * unbounded exam allowed was not. The fixed 8-12 range this replaces was no
 * narrower -- and it was arithmetically at war with the coverage rule, which
 * is how a real course of 8 lessons and 44 exam concepts arrived at an exam
 * that could not be written at all (found 2026-09-04, ledger D20 rider 3).
 */
final readonly class QuizLength
{
    /**
     * How many questions each lesson is worth in the final exam (spec 6.3.2).
     *
     * "The student picks 1-5 per lesson" is a v1.1 idea; until then it is one
     * number, here, and the prompt is handed the product rather than the rule.
     */
    public const PER_LESSON = 2;

    private function __construct(public int $min, public int $max) {}

    /**
     * Two questions for every lesson the syllabus teaches (spec 6.3.2).
     *
     * Pre: `$lessons` is how many lessons the course has -- the caller has
     *      the syllabus in hand already, and this class does not read rows.
     *
     * An exact length rather than a range, because the coverage rule it is
     * paired with is per lesson (6.3.1): the number of questions is what makes
     * covering every lesson possible, so leaving it to the model is leaving it
     * to the model whether the exam can be written at all.
     */
    public static function exam(int $lessons): self
    {
        return new self(self::PER_LESSON * $lessons, self::PER_LESSON * $lessons);
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
     * retry on every single call. The exam's number is a prompt *variable*
     * (6.3.2), so what its file holds is the sentence around it.
     */
    public function describe(): string
    {
        return $this->min === $this->max
            ? "exactly {$this->min} questions"
            : "between {$this->min} and {$this->max} questions";
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
