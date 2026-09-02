<?php

namespace App\Assessment;

use App\Assessment\Exceptions\UnansweredQuestion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The single writer of `quiz_attempts` (spec 5.2.4, 6.1).
 *
 * **Code, not a model call** (ledger D5). The answer key is in the stored
 * payload, so grading is a string comparison; asking a model to mark an answer
 * it already knows the answer to would cost money and introduce disagreement
 * between the key and the mark.
 *
 * Three things this deliberately does not do:
 *   - it does not gate anything. 5.2.4: checkpoints inform, never gate
 *     Continue. Nothing here touches lesson or course status -- the final
 *     exam's gate is FinalExam's, and it reads what this wrote.
 *   - it does not reject a repeat. Answering again is ordinary; `attempt`
 *     counts, and the first attempt is the one 5.4.2 reads.
 *   - it does not require an order. Any question of the quiz, any time.
 */
final class QuizGrader
{
    /**
     * Grade one answer and hand back the key (spec 5.2.4).
     *
     * Pre: `$quiz` is a kind that discloses its key -- a final exam is sat as
     *      a whole through `sitting()`, and grading it a question at a time
     *      would hand out the answers mid-exam.
     * Pre: `$questionId` is asked by `$quiz`, and `$optionId` is one of that
     *      question's options. Both violations are malformed requests.
     * Post: exactly one `quiz_attempts` row, and the returned verdict is
     *       derived from the stored key rather than from anything the caller
     *       sent.
     *
     * @throws UnansweredQuestion
     */
    public function grade(Quiz $quiz, User $student, string $questionId, string $optionId): GradedAnswer
    {
        if (! $quiz->kind->disclosesAnswerKey()) {
            throw new LogicException("A [{$quiz->kind->value}] quiz is graded as a whole, not one answer at a time.");
        }

        $question = $this->answerable($quiz, $questionId, $optionId);

        $correctOptionId = (string) $question['correct_option_id'];

        return new GradedAnswer(
            correct: $optionId === $correctOptionId,
            correctOptionId: $correctOptionId,
            explanation: (string) $question['explanation'],
            attempt: $this->record($quiz, $student, $questionId, $optionId, $optionId === $correctOptionId, $this->nextAttempt($quiz, $student, $questionId)),
        );
    }

    /**
     * Grade a whole quiz in one go (spec 6.3).
     *
     * A sitting, not a series of answers: every row it writes carries the same
     * `attempt` number, so "the student's second go at the exam" is a query
     * rather than a reconstruction from timestamps. Answering a question twice
     * within one sitting is the caller's last word on it.
     *
     * Pre: every id in `$answers` is asked by `$quiz` and every option is
     *      offered by its question. An exam sent with a question missing is
     *      still gradable -- an unanswered question is simply wrong.
     * Post: one `quiz_attempts` row per answer given, all at the same attempt
     *       number, and nothing about the answer key reaches the caller. **Or
     *       no rows at all**: every answer is checked before any is written,
     *       so a malformed answer halfway down a submission cannot leave a
     *       half-recorded sitting that the student's real attempt then has to
     *       be numbered around.
     *
     * @param  array<string, string>  $answers  question id => option id
     *
     * @throws UnansweredQuestion
     */
    public function sitting(Quiz $quiz, User $student, array $answers): Sitting
    {
        $document = QuizDocument::of($quiz);

        $marks = [];

        foreach ($answers as $questionId => $optionId) {
            $question = $this->answerable($quiz, (string) $questionId, $optionId);

            $marks[(string) $questionId] = $optionId === (string) $question['correct_option_id'];
        }

        $number = $this->nextSitting($quiz, $student);

        foreach ($marks as $questionId => $correct) {
            $this->record($quiz, $student, $questionId, $answers[$questionId], $correct, $number);
        }

        return Sitting::of($document, $number, $marks);
    }

    /**
     * The last whole sitting this student had at this quiz, read back (6.3).
     *
     * The screen that shows a result is the same URL the exam was sat on
     * (spec 8.6), so a reload -- or a student coming back a week later -- has
     * to be able to ask what happened. Reading it here rather than in the
     * controller keeps `attempt`-is-the-sitting-number in the one class that
     * writes it.
     *
     * Post: the returned sitting is the same one `sitting()` returned when it
     *       was recorded, because both derive from the stored marks rather
     *       than re-grading. A question left unanswered in that sitting is
     *       absent from the marks and so is counted wrong, as Sitting says.
     */
    public function lastSitting(Quiz $quiz, User $student): ?Sitting
    {
        $number = (int) $this->attempts($quiz, $student)->max('attempt');

        if ($number === 0) {
            return null;
        }

        $marks = $this->attempts($quiz, $student)
            ->where('attempt', $number)
            ->get()
            ->mapWithKeys(static fn (QuizAttempt $attempt): array => [$attempt->question_id => $attempt->correct])
            ->all();

        return Sitting::of(QuizDocument::of($quiz), $number, $marks);
    }

    /**
     * The question an answer claims to answer, checked both ways.
     *
     * @return array<string, mixed>
     *
     * @throws UnansweredQuestion
     */
    private function answerable(Quiz $quiz, string $questionId, string $optionId): array
    {
        $question = QuizDocument::of($quiz)->question($questionId);

        if ($question === null) {
            throw UnansweredQuestion::noSuchQuestion($questionId);
        }

        $optionIds = array_map(
            static fn (array $option): string => (string) $option['id'],
            $question['options'],
        );

        if (! in_array($optionId, $optionIds, true)) {
            throw UnansweredQuestion::noSuchOption($questionId, $optionId);
        }

        return $question;
    }

    /**
     * Write the attempt at the number it was given.
     *
     * A double-tapped answer button races on that number. The unique index
     * catches the loser, which recounts and tries again rather than
     * overwriting the row that won.
     */
    private function record(Quiz $quiz, User $student, string $questionId, string $optionId, bool $correct, int $attempt): QuizAttempt
    {
        for ($try = 1; $try <= 3; $try++) {
            try {
                return DB::transaction(fn (): QuizAttempt => QuizAttempt::query()->create([
                    'quiz_id' => $quiz->id,
                    'user_id' => $student->id,
                    'question_id' => $questionId,
                    'option_id' => $optionId,
                    'correct' => $correct,
                    'attempt' => $attempt,
                    'created_at' => now(),
                ]));
            } catch (QueryException $e) {
                if ($try === 3) {
                    throw $e;
                }

                $attempt = $this->nextAttempt($quiz, $student, $questionId);
            }
        }

        // Unreachable: the loop either returns or rethrows.
        throw new UnansweredQuestion('Could not record the attempt.');
    }

    private function nextAttempt(Quiz $quiz, User $student, string $questionId): int
    {
        return 1 + (int) $this->attempts($quiz, $student)
            ->where('question_id', $questionId)
            ->max('attempt');
    }

    /**
     * This student's rows on this quiz, unordered.
     *
     * @return Builder<QuizAttempt>
     */
    private function attempts(Quiz $quiz, User $student): Builder
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id);
    }

    /**
     * One past the highest attempt on any of this quiz's questions.
     *
     * Across the whole quiz rather than per question, because a sitting is one
     * event: a student who skipped question 3 last time and answers it this
     * time is on their second sitting, not their first of that question.
     */
    private function nextSitting(Quiz $quiz, User $student): int
    {
        return 1 + (int) $this->attempts($quiz, $student)->max('attempt');
    }
}
