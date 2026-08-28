<?php

namespace App\Assessment;

use App\Courses\CourseStatus;

/**
 * What one sitting of a final exam did to the course (spec 6.3).
 *
 * The whole of what a student is told: whether they passed, how they scored,
 * and which concepts let them down. No answer key -- see
 * QuizKind::disclosesAnswerKey for why an exam that hands back its key stops
 * measuring anything on the re-sit.
 *
 * `standing` is the course's standing *after* the sitting, so a client never
 * has to infer the consequence from the verdict: pass leaves `completed`, fail
 * leaves `active` (ledger D7b -- failing is not an ending, it hands the
 * student back their course to keep studying).
 */
final readonly class ExamResult
{
    public function __construct(
        public bool $passed,
        public Sitting $sitting,
        public CourseStatus $standing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'standing' => $this->standing->value,
            ...$this->sitting->toArray(),
        ];
    }
}
