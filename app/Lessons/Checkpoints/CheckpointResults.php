<?php

namespace App\Lessons\Checkpoints;

use App\Assessment\AnsweredQuestions;
use App\Assessment\QuizKind;
use App\Models\Lesson;
use App\Models\Quiz;

/**
 * How the lesson's checkpoints actually went (spec 5.4.2).
 *
 * The `checkpoint_results` variable of `lesson.summarize`, and the reason that
 * prompt can say "the model interprets grades, never recomputes them": the
 * marks are already decided by QuizGrader, and this renders them. Handing the
 * model the questions and the raw answers instead would let a summary disagree
 * with the mark the student was shown.
 *
 * The first-attempt rule and the row shape come from AnsweredQuestions, which
 * `homework_results` reads too (6.2). What is left here is the one thing only
 * a checkpoint has: the section it closes, which is what the sections below
 * group on.
 */
final readonly class CheckpointResults
{
    /**
     * @param  list<array{section_index: int, question_id: string, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>  $rows
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
            foreach (AnsweredQuestions::on($quiz, $lesson->course->student) as $row) {
                $rows[] = ['section_index' => (int) $quiz->section_index, ...$row];
            }
        }

        return new self($rows);
    }

    /**
     * @return list<array{section_index: int, question_id: string, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>
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

            $lines[] = AnsweredQuestions::bullet($row);
        }

        return implode("\n", $lines);
    }
}
