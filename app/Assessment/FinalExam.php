<?php

namespace App\Assessment;

use App\Ai\Gateway;
use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\ExamUnusable;
use App\Assessment\Exceptions\UnansweredQuestion;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\LessonStatus;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Quiz;
use Throwable;

/**
 * The exam that ends a course (spec 6.3).
 *
 * Two moments, and the order inside each is the design:
 *
 * **Opening.** The exam is generated *before* the course moves to
 * `final_exam`, so a generation that fails writes nothing and moves nothing
 * (ledger D10.4). A course whose exam would not generate is still `active`
 * with every lesson completed, and the student can ask again.
 *
 * **Sitting.** The whole exam is marked at once. Grading it a question at a
 * time would hand out the answers mid-exam, and passing is a property of the
 * set rather than of any one question. Pass stamps `completed_at`; fail moves
 * the course back to `active`, which is 6.3's done-condition and D7b's reason
 * for having no `failed` end state -- failing hands the student back their
 * course to keep studying.
 *
 * **One exam per course, re-sat.** `quizzes_one_per_scope` holds the course to
 * one, and reopening after a failure returns that same exam at the next
 * sitting number. That only measures anything because the key is never
 * disclosed (QuizKind::disclosesAnswerKey); what a failed sitting hands back
 * is the score and the concepts missed.
 */
final class FinalExam
{
    private const FEATURE = 'course.final_exam';

    public function __construct(
        private readonly Gateway $gateway,
        private readonly MemoryTree $memory,
        private readonly Quizzes $quizzes,
        private readonly QuizGrader $grader,
        private readonly CourseTransitions $transitions,
    ) {}

    /**
     * Whether the student has earned the right to sit it.
     *
     * Either every lesson is finished and the course is still `active`, or the
     * course already stands at `final_exam` -- a student who failed and came
     * back is as entitled to sit as one arriving for the first time.
     */
    public function isAvailable(Course $course): bool
    {
        if (! $course->isOpen()) {
            return false;
        }

        if ($course->status === CourseStatus::FinalExam) {
            return true;
        }

        return $course->status === CourseStatus::Active && $this->lessonsRemaining($course) === 0;
    }

    /**
     * Sit the student down in front of their exam (spec 6.3).
     *
     * Pre: `isAvailable`.
     * Post: the course stands at `final_exam` and exactly one `final` quiz
     *       exists for it. On any failure neither is true: nothing is written
     *       and nothing has moved.
     *
     * Idempotent, and that is what makes a re-sit the same exam: a course that
     * already has one is seated in front of it rather than paying for a second
     * `top`-tier generation and being asked different questions.
     *
     * @throws ExamNotAvailable|ExamUnusable
     */
    public function open(Course $course): Quiz
    {
        $this->assertAvailable($course);

        // Generated before the course moves, so a generation that fails writes
        // nothing and moves nothing (ledger D10.4).
        $exam = $this->exam($course) ?? $this->quizzes->create(
            QuizKind::Final,
            $course,
            $course->student,
            $course,
            $this->askUntilUsable($course)->payload(),
        );

        if ($course->status !== CourseStatus::FinalExam) {
            $this->transitions->to($course, CourseStatus::FinalExam);
        }

        return $exam;
    }

    /**
     * The exam this course already has, if any.
     */
    public function exam(Course $course): ?Quiz
    {
        return $this->quizzes->existing(QuizKind::Final, $course);
    }

    /**
     * Mark a whole sitting and let it decide the course (spec 6.3).
     *
     * Pre: the course is open, stands at `final_exam`, and has an exam.
     * Post: one `quiz_attempts` row per answer at one sitting number, and the
     *       course either carries `completed_at` or stands at `active` again.
     *       Nothing about the answer key reaches the caller either way.
     *
     * A malformed answer -- a question this exam does not ask -- writes
     * nothing and leaves the course exactly where it was.
     *
     * @param  array<string, string>  $answers  question id => option id
     *
     * @throws ExamNotAvailable|UnansweredQuestion
     */
    public function sit(Course $course, array $answers): ExamResult
    {
        if (! $course->isOpen()) {
            throw ExamNotAvailable::because($course, 'it has already ended.');
        }

        if ($course->status !== CourseStatus::FinalExam) {
            throw ExamNotAvailable::because($course, "it stands at [{$course->status->value}]; open the exam first.");
        }

        $exam = $this->exam($course);

        if (! $exam instanceof Quiz) {
            throw ExamNotAvailable::because($course, 'no exam has been generated for it.');
        }

        $sitting = $this->grader->sitting($exam, $course->student, $answers);

        $passed = $sitting->score() >= $this->passMark();

        // The grade is recorded before the course moves, so a transition that
        // throws leaves the sitting on the record rather than losing it.
        $course = $passed
            ? $this->transitions->complete($course)
            : $this->transitions->to($course, CourseStatus::Active);

        return new ExamResult($passed, $sitting, $course->standing());
    }

    public function passMark(): float
    {
        return (float) config('assessment.final_exam.pass_mark', 0.7);
    }

    /**
     * The same rule as `isAvailable`, said out loud.
     *
     * A refusal here is never an authorization answer -- it is the student's
     * own course telling them what is still in the way.
     *
     * @throws ExamNotAvailable
     */
    private function assertAvailable(Course $course): void
    {
        if ($this->isAvailable($course)) {
            return;
        }

        if (! $course->isOpen()) {
            throw ExamNotAvailable::because($course, 'it has already ended.');
        }

        if ($course->status !== CourseStatus::Active) {
            throw ExamNotAvailable::because($course, "it stands at [{$course->status->value}], not [active].");
        }

        throw ExamNotAvailable::because(
            $course,
            $this->lessonsRemaining($course).' lesson(s) are still to be completed.',
        );
    }

    /**
     * How many of the course's lessons are not finished.
     */
    private function lessonsRemaining(Course $course): int
    {
        return $course->lessons()->where('status', '!=', LessonStatus::Completed->value)->count();
    }

    /**
     * Write an exam that covers what the syllabus said it would.
     *
     * "Scoped by `final_exam_concepts`" is checked here rather than hoped for
     * in the prompt: every declared exam concept has to be reachable through
     * some question, and a tag outside that scope is dropped the way 5.2.2
     * drops an invented one. A schema cannot state either rule -- the legal
     * set is one document's, not the contract's.
     *
     * @throws ExamUnusable
     */
    private function askUntilUsable(Course $course): QuizDocument
    {
        $syllabus = $course->approvedSyllabus;
        $scope = array_values($syllabus->syllabus_json['final_exam_concepts']);

        // Every exam slug is declared by a lesson section (4.5.2.2 checks this
        // before a syllabus row is written), so the exam's vocabulary is the
        // narrower of the two and the one that matters here.
        SyllabusConcepts::of($syllabus)->assertDeclared($scope);

        $vars = [
            'syllabus_json' => json_encode(
                $syllabus->syllabus_json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            'wk_doc' => $this->memory->get($course->student, $course->workingKnowledgePath())
                ?? 'No working-knowledge document has been written for this subtopic.',
        ];

        $attempts = (int) config('assessment.final_exam.generation_attempts', 2);
        $reasons = [];
        $failure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->gateway
                    ->prompt('exam.final', $vars)
                    ->feature(self::FEATURE)
                    ->for($course->user_id)
                    ->schema(QuizSchema::definition())
                    ->generate();

                if (! is_array($response->structured)) {
                    $reasons[] = 'The model returned no structured exam.';

                    continue;
                }

                $document = QuizDocument::in($response->structured, QuizKind::Final)->withResolvedTags($scope);

                $defects = [...$document->defects(), ...$this->uncovered($document, $scope)];

                if ($defects === []) {
                    return $document;
                }

                $reasons = $defects;
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        throw ExamUnusable::after($attempts, $reasons, $failure);
    }

    /**
     * The exam concepts no question asks about.
     *
     * @param  list<string>  $scope
     * @return list<string>
     */
    private function uncovered(QuizDocument $document, array $scope): array
    {
        $missing = array_values(array_diff($scope, $document->conceptTags()));

        return $missing === []
            ? []
            : ['It asks nothing about ['.implode(', ', $missing).'].'];
    }
}
