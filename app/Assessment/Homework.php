<?php

namespace App\Assessment;

use App\Models\Quiz;
use App\Models\User;

/**
 * A lesson's homework, as the student's screen sees it (spec 6.2).
 *
 * Questions with the answer key removed, the suggested reading beside them,
 * and how far the student has got. The key is disclosed one question at a time
 * by `POST .../homework/answers` and never here -- reloading the page is not a
 * way around 5.2.3.
 *
 * What a reload therefore does not restore is the explanations already earned,
 * only which questions were answered and how they went. That is the same trade
 * LessonState makes for checkpoints, and the same reason: one disclosure point
 * is auditable, two are a race to keep in step.
 */
final readonly class Homework
{
    private function __construct(
        private Quiz $quiz,
        private QuizDocument $document,
        private User $student,
    ) {}

    public static function of(Quiz $quiz, User $student): self
    {
        return new self($quiz, QuizDocument::of($quiz), $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quiz_id' => $this->quiz->id,
            'scope' => $this->document->scope(),
            'questions' => $this->document->clientPayload()['quiz']['questions'],
            'suggested_reading' => $this->document->suggestedReading(),
            'progress' => $this->progress(),
        ];
    }

    /**
     * Which questions have been answered, and how the first answer went.
     *
     * @return list<array{question_id: string, answered: bool, correct: bool, attempts: int}>
     */
    private function progress(): array
    {
        return array_map(
            static fn (array $row): array => [
                'question_id' => $row['question_id'],
                'answered' => $row['answered'],
                'correct' => $row['correct'],
                'attempts' => $row['attempts'],
            ],
            AnsweredQuestions::on($this->quiz, $this->student),
        );
    }
}
