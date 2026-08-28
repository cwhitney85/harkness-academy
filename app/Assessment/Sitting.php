<?php

namespace App\Assessment;

/**
 * One whole-quiz attempt, marked (spec 6.3).
 *
 * The unit a final exam is judged in. Grading question by question answers
 * "was that right?"; a sitting answers "did they pass?", and the two are not
 * the same question -- passing is a property of the set.
 *
 * **An unanswered question is a wrong one.** The denominator is every question
 * the quiz asks, not every question the client sent, so a client that submits
 * only the answers it is confident about cannot raise its own score by
 * omission.
 *
 * Carries no answer key. What a failed sitting is allowed to say is the score
 * and the concepts missed -- the signal ledger D7b named when it rejected a
 * `failed` end state, and the reason a re-sit of the same exam still measures
 * something (QuizKind::disclosesAnswerKey).
 */
final readonly class Sitting
{
    /**
     * @param  list<string>  $conceptsMissed
     */
    private function __construct(
        public int $number,
        public int $correct,
        public int $total,
        public array $conceptsMissed,
    ) {}

    /**
     * @param  array<string, bool>  $marks  question id => correct
     */
    public static function of(QuizDocument $document, int $number, array $marks): self
    {
        $correct = 0;
        $missed = [];

        foreach ($document->questions() as $question) {
            $id = (string) $question['id'];

            if ($marks[$id] ?? false) {
                $correct++;

                continue;
            }

            $tag = $question['concept_tag'] ?? null;

            if (is_string($tag) && $tag !== '') {
                $missed[$tag] = true;
            }
        }

        return new self(
            number: $number,
            correct: $correct,
            total: count($document->questions()),
            conceptsMissed: array_keys($missed),
        );
    }

    /**
     * The fraction answered correctly, 0 through 1.
     *
     * A quiz with no questions scores zero rather than dividing by it; such a
     * document is rejected before it is stored (QuizDocument::defects), so
     * this is a floor and not a case anyone should meet.
     */
    public function score(): float
    {
        return $this->total === 0 ? 0.0 : $this->correct / $this->total;
    }

    /**
     * @return array{sitting: int, correct: int, total: int, score: float, concepts_missed: list<string>}
     */
    public function toArray(): array
    {
        return [
            'sitting' => $this->number,
            'correct' => $this->correct,
            'total' => $this->total,
            'score' => round($this->score(), 4),
            'concepts_missed' => $this->conceptsMissed,
        ];
    }
}
