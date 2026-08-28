<?php

namespace App\Assessment;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * How one student did on one quiz, question by question (spec 5.4.2, 6.2).
 *
 * The primitive under every prompt variable that reports grades. Two of those
 * exist -- `checkpoint_results` for the summarizer and `homework_results` for
 * the working-knowledge update -- and the rule they share is the one worth
 * stating once:
 *
 * **The first attempt is the one that counts.** A second attempt happens after
 * the explanation has been read, so it says what the student was told rather
 * than what they knew. Later attempts are still reported -- a concept that
 * took three goes is a different story from one that took one -- but the
 * verdict is the first.
 *
 * Reads marks; never recomputes them. The marks were decided by QuizGrader
 * against the stored key, and a second opinion here could disagree with what
 * the student was shown.
 */
final class AnsweredQuestions
{
    /**
     * @return list<array{question_id: string, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>
     */
    public static function on(Quiz $quiz, User $student): array
    {
        $attempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->orderBy('attempt')
            ->get()
            ->groupBy('question_id');

        $rows = [];

        foreach (QuizDocument::of($quiz)->questions() as $question) {
            $forQuestion = $attempts->get((string) $question['id']);
            $first = $forQuestion?->first();

            $rows[] = [
                'question_id' => (string) $question['id'],
                'question' => (string) $question['prompt'],
                'concept_tag' => $question['concept_tag'] ?? null,
                'answered' => $first instanceof QuizAttempt,
                'correct' => $first instanceof QuizAttempt && $first->correct,
                'attempts' => $forQuestion?->count() ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Whether the student has answered any of this quiz's questions.
     */
    public static function touched(Quiz $quiz, User $student): bool
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->exists();
    }

    /**
     * One row as a prompt reads it.
     *
     * @param  array{answered: bool, correct: bool, attempts: int}  $row
     */
    public static function verdict(array $row): string
    {
        if (! $row['answered']) {
            return 'not answered';
        }

        $verdict = $row['correct'] ? 'correct first time' : 'wrong first time';

        return $row['attempts'] > 1
            ? $verdict.", answered {$row['attempts']} times"
            : $verdict;
    }

    /**
     * The markdown bullet a prompt reads for one row.
     *
     * @param  array{question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}  $row
     */
    public static function bullet(array $row): string
    {
        return sprintf(
            '- %s — %s%s',
            $row['question'],
            self::verdict($row),
            $row['concept_tag'] === null ? '' : " (concept: {$row['concept_tag']})",
        );
    }
}
