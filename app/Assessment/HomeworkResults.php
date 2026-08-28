<?php

namespace App\Assessment;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;

/**
 * The student's homework record for a subtopic (spec 6.2).
 *
 * The `homework_results` variable of `wk.update`, and the mechanism behind
 * 6.2's "result feeds next WK update". It has to be its own variable, because
 * homework cannot arrive through the summary: the summary is written at step
 * one of the completion chain and the homework it aims at is generated at step
 * three, so lesson N's summary is finished before lesson N's homework exists.
 * The evidence therefore lands on the *next* lesson's update, which is exactly
 * what the spec says.
 *
 * **Scope is the subtopic, not the course** (ledger D8). The document being
 * updated is shared by every course in the subtopic at every level, so
 * homework from a 101 course is evidence about the same knowledge a 201 course
 * is revising.
 *
 * **Cumulative, not a delta.** The tempting version of this class reports only
 * what the last update has not seen, and the tempting way to decide that is to
 * compare an attempt's timestamp against the last `wk_update` step's. Those
 * two clocks belong to different subsystems and are written milliseconds
 * apart; ordering them is a coin flip that silently drops a student's homework
 * or double-counts it. There is no delta worth that. What is reported instead
 * is every homework in the subtopic that has been answered, in teaching order,
 * and the prompt is told the list is a record rather than news -- so late
 * homework is picked up whenever it is finally done, and a full-document
 * replacement re-reading evidence it has already absorbed changes nothing.
 *
 * The one thing deliberately excluded is the lesson's own homework: at step
 * two it does not exist yet, and after a retry it exists but is unanswered.
 * Reporting it would put "not answered" against every question of a quiz the
 * student has not been shown.
 */
final readonly class HomeworkResults
{
    /**
     * @param  list<array{lesson_title: string, question_id: string, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>  $rows
     */
    private function __construct(private array $rows) {}

    /**
     * Every homework this student has answered in this lesson's subtopic,
     * except this lesson's own, in teaching order.
     *
     * Teaching order is structural -- course id, then lesson position -- and
     * not a timestamp. Only one course per subtopic may be open at a time
     * (ledger D7a), so the courses of a subtopic are themselves sequential.
     */
    public static function forSubtopicOf(Lesson $lesson): self
    {
        $course = $lesson->course;

        /** @var list<Lesson> $lessons */
        $lessons = Lesson::query()
            ->whereIn('course_id', Course::query()
                ->forSubtopic($course->student, $course->module_slug, $course->subtopic_slug)
                ->select('id'))
            ->whereKeyNot($lesson->id)
            ->orderBy('course_id')
            ->orderBy('position')
            ->get()
            ->all();

        $rows = [];

        foreach ($lessons as $taught) {
            $homework = $taught->quizzes()->ofKind(QuizKind::Homework)->first();

            if (! $homework instanceof Quiz || ! AnsweredQuestions::touched($homework, $course->student)) {
                continue;
            }

            $title = (string) $taught->syllabusEntry()['title'];

            foreach (AnsweredQuestions::on($homework, $course->student) as $row) {
                $rows[] = ['lesson_title' => $title, ...$row];
            }
        }

        return new self($rows);
    }

    /**
     * @return list<array{lesson_title: string, question_id: string, question: string, concept_tag: string|null, answered: bool, correct: bool, attempts: int}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * The markdown `wk.update` reads.
     *
     * Says "none" in words rather than rendering an empty section, for the
     * same reason CheckpointResults does: a blank in the middle of a prompt
     * reads as a bug to the model as easily as an absence.
     */
    public function render(): string
    {
        if ($this->rows === []) {
            return 'The student has not done any homework in this subtopic yet.';
        }

        $lines = [];
        $title = null;

        foreach ($this->rows as $row) {
            if ($row['lesson_title'] !== $title) {
                $title = $row['lesson_title'];
                $lines[] = ($lines === [] ? '' : "\n")."### Homework for *{$title}*";
                $lines[] = '';
            }

            $lines[] = AnsweredQuestions::bullet($row);
        }

        return implode("\n", $lines);
    }
}
