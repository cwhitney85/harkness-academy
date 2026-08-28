<?php

namespace App\Assessment;

use App\Models\QuizAttempt;

/**
 * What the student learns by answering (spec 5.2.4).
 *
 * Exactly the three fields 5.2.4 names. The answer key travels here and
 * nowhere earlier: 5.2.3 strips it from the served quiz, and this is the
 * moment the student has earned it.
 *
 * Only the three kinds that teach produce one (QuizKind::disclosesAnswerKey).
 * A final exam is sat as a whole and answers with an ExamResult instead.
 */
final readonly class GradedAnswer
{
    public function __construct(
        public bool $correct,
        public string $correctOptionId,
        public string $explanation,
        public QuizAttempt $attempt,
    ) {}

    /**
     * The response body of `POST .../answers`.
     *
     * @return array{correct: bool, correct_option_id: string, explanation: string}
     */
    public function toArray(): array
    {
        return [
            'correct' => $this->correct,
            'correct_option_id' => $this->correctOptionId,
            'explanation' => $this->explanation,
        ];
    }
}
