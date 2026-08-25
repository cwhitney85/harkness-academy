<?php

namespace App\Lessons\Checkpoints;

use App\Lessons\Exceptions\UnansweredQuestion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Grading a checkpoint answer (spec 5.2.4).
 *
 * **Code, not a model call** (ledger D5). The answer key is in the stored
 * payload, so grading is a string comparison; asking a model to mark an
 * answer it already knows the answer to would cost money and introduce
 * disagreement between the key and the mark.
 *
 * Three things this deliberately does not do:
 *   - it does not gate anything. 5.2.4: checkpoints inform, never gate
 *     Continue. Nothing here touches lesson status.
 *   - it does not reject a repeat. Answering again is ordinary; `attempt`
 *     counts, and the first attempt is the one 5.4.2 reads.
 *   - it does not require an order. Any question of the checkpoint, any time.
 *
 * Pre: `$questionId` is asked by `$quiz`, and `$optionId` is one of that
 *      question's options. Both violations are malformed requests.
 * Post: exactly one `quiz_attempts` row, and the returned verdict is derived
 *       from the stored key rather than from anything the caller sent.
 */
final class CheckpointGrader
{
    /**
     * @throws UnansweredQuestion
     */
    public function grade(Quiz $quiz, User $student, string $questionId, string $optionId): GradedAnswer
    {
        $question = Checkpoint::of($quiz)->question($questionId);

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

        $correctOptionId = (string) $question['correct_option_id'];
        $correct = $optionId === $correctOptionId;

        return new GradedAnswer(
            correct: $correct,
            correctOptionId: $correctOptionId,
            explanation: (string) $question['explanation'],
            attempt: $this->record($quiz, $student, $questionId, $optionId, $correct),
        );
    }

    /**
     * Write the attempt, numbering it one past whatever is already there.
     *
     * A double-tapped answer button races on that number. The unique index
     * catches the loser, which recounts and tries again rather than
     * overwriting the row that won.
     */
    private function record(Quiz $quiz, User $student, string $questionId, string $optionId, bool $correct): QuizAttempt
    {
        for ($try = 1; $try <= 3; $try++) {
            try {
                return DB::transaction(fn (): QuizAttempt => QuizAttempt::query()->create([
                    'quiz_id' => $quiz->id,
                    'user_id' => $student->id,
                    'question_id' => $questionId,
                    'option_id' => $optionId,
                    'correct' => $correct,
                    'attempt' => $this->nextAttempt($quiz, $student, $questionId),
                    'created_at' => now(),
                ]));
            } catch (QueryException $e) {
                if ($try === 3) {
                    throw $e;
                }
            }
        }

        // Unreachable: the loop either returns or rethrows.
        throw new UnansweredQuestion('Could not record the attempt.');
    }

    private function nextAttempt(Quiz $quiz, User $student, string $questionId): int
    {
        return 1 + (int) QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->where('question_id', $questionId)
            ->max('attempt');
    }
}
