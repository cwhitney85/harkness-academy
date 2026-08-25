<?php

namespace App\Lessons\Checkpoints;

use App\Assessment\QuizKind;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;

/**
 * How the lesson's checkpoints actually went (spec 5.4.2).
 *
 * The `checkpoint_results` variable of `lesson.summarize`, and the reason that
 * prompt can say "the model interprets grades, never recomputes them": the
 * marks are already decided by CheckpointGrader, and this renders them.
 * Handing the model the questions and the raw answers instead would let a
 * summary disagree with the mark the student was shown.
 *
 * **The first attempt is the one that counts.** A second attempt happens after
 * the explanation has been read, so it says what the student was told rather
 * than what they knew. Later attempts are still reported -- a concept that
 * took three goes is a different story from one that took one -- but the
 * verdict is the first.
 */
final readonly class CheckpointResults
{
    /**
     * @param  list<array{section_index: int, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>  $rows
     */
    private function __construct(private array $rows) {}

    public static function for(Lesson $lesson): self
    {
        $rows = [];

        /** @var list<Quiz> $checkpoints */
        $checkpoints = $lesson->quizzes()
            ->ofKind(QuizKind::Checkpoint)
            ->orderBy('section_index')
            ->get()
            ->all();

        foreach ($checkpoints as $quiz) {
            $attempts = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $lesson->course->user_id)
                ->orderBy('attempt')
                ->get()
                ->groupBy('question_id');

            foreach (Checkpoint::of($quiz)->questions() as $question) {
                $forQuestion = $attempts->get((string) $question['id']);
                $first = $forQuestion?->first();

                $rows[] = [
                    'section_index' => (int) $quiz->section_index,
                    'question' => (string) $question['prompt'],
                    'concept_tag' => $question['concept_tag'] ?? null,
                    'answered' => $first instanceof QuizAttempt,
                    'correct' => $first instanceof QuizAttempt && $first->correct,
                    'attempts' => $forQuestion?->count() ?? 0,
                ];
            }
        }

        return new self($rows);
    }

    /**
     * @return list<array{section_index: int, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * The markdown the summarizing prompt reads.
     *
     * A lesson whose checkpoints all failed open (5.2.1) has no rows, and says
     * so explicitly rather than rendering an empty section -- a blank in the
     * middle of a prompt reads as a bug to the model as easily as an absence
     * (the same reasoning as KnowledgeInterview::NO_PRIOR_KNOWLEDGE).
     */
    public function render(): string
    {
        if ($this->rows === []) {
            return 'No checkpoints were recorded for this lesson.';
        }

        $lines = [];
        $section = null;

        foreach ($this->rows as $row) {
            if ($row['section_index'] !== $section) {
                $section = $row['section_index'];
                $lines[] = ($lines === [] ? '' : "\n")."### Section {$section}";
                $lines[] = '';
            }

            $lines[] = sprintf(
                '- %s — %s%s',
                $row['question'],
                $this->verdict($row),
                $row['concept_tag'] === null ? '' : " (concept: {$row['concept_tag']})",
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{answered: bool, correct: bool, attempts: int}  $row
     */
    private function verdict(array $row): string
    {
        if (! $row['answered']) {
            return 'not answered';
        }

        $verdict = $row['correct'] ? 'correct first time' : 'wrong first time';

        return $row['attempts'] > 1
            ? $verdict.", answered {$row['attempts']} times"
            : $verdict;
    }
}
