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
use App\Models\ExamAnswerSheet;
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
     * Why this course cannot be asked for its exam right now, or null.
     *
     * Asking without opening, on `SyllabusGenerator::refusal()`'s precedent.
     * The writing runs on the queue now (ledger D25), and availability is
     * knowable without the model -- so a controller that dispatched first
     * would turn "two lessons still to finish" into a wait ending in a
     * refusal. Runs the same assert `open()` runs, so the two cannot drift.
     */
    public function refusal(Course $course): ?string
    {
        try {
            $this->assertAvailable($course);
        } catch (ExamNotAvailable $e) {
            return $e->getMessage();
        }

        return null;
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
        $exam = $this->seated($course);
        $sitting = $this->grader->sitting($exam, $course->student, $answers);

        // The book is in. What is left of the sheet is a draft of a sitting
        // that has happened, and a re-sit opens an empty book (6.3.4).
        ExamAnswerSheet::query()->where($this->sheetKeys($exam, $course, $sitting->number))->delete();

        $passed = $sitting->score() >= $this->passMark();

        // The grade is recorded before the course moves, so a transition that
        // throws leaves the sitting on the record rather than losing it.
        $course = $passed
            ? $this->transitions->complete($course)
            : $this->transitions->to($course, CourseStatus::Active);

        return new ExamResult($passed, $sitting, $course->standing());
    }

    /**
     * Write down what the student has ticked so far (spec 6.3.4, ledger D26).
     *
     * Pre:  the course is open, stands at `final_exam`, and has an exam; every
     *       question id is one that exam asks and every option one that
     *       question offers -- the same rule grading applies, through the same
     *       method, so a sheet cannot be filled in with what cannot be marked.
     * Post: exactly one sheet for (exam, student, the sitting about to
     *       happen), holding those selections and **no verdict of any kind**.
     *       Nothing is graded, nothing is disclosed, and the course has not
     *       moved.
     *
     * Replaced whole rather than merged: the screen holds the whole book, so
     * the last save is the student's last word, and un-ticking is then an
     * ordinary save rather than a second endpoint.
     *
     * @param  array<string, string>  $answers  question id => option id
     *
     * @throws ExamNotAvailable|UnansweredQuestion
     */
    public function save(Course $course, array $answers): void
    {
        $exam = $this->seated($course);

        $selections = [];

        foreach ($answers as $questionId => $optionId) {
            $this->grader->answerable($exam, (string) $questionId, $optionId);

            $selections[(string) $questionId] = $optionId;
        }

        $keys = $this->sheetKeys($exam, $course, $this->nextSitting($exam, $course));

        // One statement, so two selections clicked in the same second cannot
        // race each other into two rows the unique index would then refuse.
        ExamAnswerSheet::query()->upsert(
            [[...$keys, 'selections' => json_encode($selections, JSON_THROW_ON_ERROR)]],
            array_keys($keys),
            ['selections'],
        );
    }

    /**
     * What the student has ticked in the book they are sitting now (6.3.4).
     *
     * Post: question id => option id, empty when nothing has been ticked --
     *       which is also what a re-sit reads, because the sheet is cleared at
     *       hand-in.
     *
     * @return array<string, string>
     */
    public function sheet(Course $course): array
    {
        $exam = $this->exam($course);

        if (! $exam instanceof Quiz) {
            return [];
        }

        $sheet = ExamAnswerSheet::query()
            ->where($this->sheetKeys($exam, $course, $this->nextSitting($exam, $course)))
            ->first();

        return $sheet instanceof ExamAnswerSheet ? $sheet->selections : [];
    }

    /**
     * The last sitting this course's exam had, if it has been sat (spec 6.3).
     *
     * S10 is a state of S9 rather than a URL of its own (spec 8.6), so the
     * result has to survive a reload: this is what makes "pass and fail each
     * render" true on a plain GET rather than only in the reply to the POST
     * that handed the book in.
     */
    public function lastSitting(Course $course): ?Sitting
    {
        $exam = $this->exam($course);

        return $exam instanceof Quiz
            ? $this->grader->lastSitting($exam, $course->student)
            : null;
    }

    public function passMark(): float
    {
        return (float) config('assessment.final_exam.pass_mark', 0.7);
    }

    /**
     * The exam this student is sitting right now.
     *
     * Three checks, shared by `sit()` and `save()`: a sheet is part of sitting
     * the exam, so anything that may not be sat may not be written on either,
     * and the student is owed the same sentence about why.
     *
     * @throws ExamNotAvailable
     */
    private function seated(Course $course): Quiz
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

        return $exam;
    }

    /**
     * The sitting number the next hand-in will carry.
     *
     * Read through the grader, which is where `attempt`-is-the-sitting-number
     * lives, so a sheet is filed under the number its answers will be recorded
     * at rather than under a second opinion about which sitting this is.
     */
    private function nextSitting(Quiz $exam, Course $course): int
    {
        return 1 + ($this->grader->lastSitting($exam, $course->student)->number ?? 0);
    }

    /**
     * What identifies one student's sheet for one sitting of one exam.
     *
     * The three columns of `exam_answer_sheets_one_per_sitting`, in one place,
     * because they are read, written and cleared from three different methods
     * and the student column is what keeps one student's book out of
     * another's hands.
     *
     * @return array{quiz_id: int, user_id: int, attempt: int}
     */
    private function sheetKeys(Quiz $exam, Course $course, int $sitting): array
    {
        return [
            'quiz_id' => $exam->id,
            'user_id' => $course->student->id,
            'attempt' => $sitting,
        ];
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
     * Write an exam that covers every lesson the syllabus teaches (6.3.1).
     *
     * "Covers the course" is checked here rather than hoped for in the prompt:
     * every lesson has to be reachable through some question, and a tag the
     * syllabus does not declare is dropped the way 5.2.2 drops an invented
     * one. A schema cannot state either rule -- the legal set is one
     * document's, not the contract's.
     *
     * **Per lesson, not per concept, and that is the 2026-09-04 repair.** The
     * rule used to require every slug in `final_exam_concepts` to be some
     * question's tag. A question carries exactly one tag, so coverage could
     * never exceed the question count, and the first real course declared 44
     * exam concepts against a 12-question exam: it was unsatisfiable, and no
     * fixture had ever been large enough to say so (6.3.5 now is). Lessons are
     * what the exam is sat on, they are bounded by the same depth parameters
     * that set the question count, and `final_exam_concepts` goes back to
     * being the emphasis it reads as.
     *
     * @throws ExamUnusable
     */
    private function askUntilUsable(Course $course): QuizDocument
    {
        $syllabus = $course->approvedSyllabus;
        $concepts = SyllabusConcepts::of($syllabus);

        // Every concept the lessons declare, not the exam list: a question
        // about a real lesson-4 concept outside that list would otherwise lose
        // its tag, and its lesson would count as uncovered (6.3.3).
        $vocabulary = $concepts->declared();
        $lessons = $this->lessonTitles($syllabus->syllabus_json);
        $length = QuizLength::exam(count($lessons));

        $vars = [
            'syllabus_json' => json_encode(
                $syllabus->syllabus_json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            'wk_doc' => $this->memory->get($course->student, $course->workingKnowledgePath())
                ?? 'No working-knowledge document has been written for this subtopic.',
            // The arithmetic is done here rather than by the model: asking a
            // `top`-tier call to multiply and then rejecting its answer is the
            // most expensive way to get a number we already have (6.3.2).
            'question_count' => (string) $length->min,
        ];

        $attempts = (int) config('assessment.final_exam.generation_attempts', 2);
        $reasons = [];
        $failure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->gateway
                    ->prompt('exam.final', $vars)
                    ->feature(self::FEATURE)
                    ->about($course)
                    ->schema(QuizSchema::definition($length))
                    ->generate();

                if (! is_array($response->structured)) {
                    $reasons[] = 'The model returned no structured exam.';

                    continue;
                }

                $document = QuizDocument::in($response->structured, QuizKind::Final)->withResolvedTags($vocabulary);

                $defects = [
                    ...$document->defects(),
                    ...$this->unexamined($document, $concepts, $lessons),
                    ...$this->misjudgedLength($document, $length),
                ];

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
     * The lessons no question asks anything from (spec 6.3.1).
     *
     * A tag reaches its lesson through `SyllabusConcepts`, which already holds
     * slug => lesson positions -- every position, because a concept taught in
     * two lessons is the same concept in both (the prompts require it), so a
     * question about it is a question about either.
     *
     * Pre: `$document` has had its tags resolved against the same syllabus,
     *      so no tag here is undeclared.
     *
     * @param  array<int, string>  $lessons  position => title
     * @return list<string>
     */
    private function unexamined(QuizDocument $document, SyllabusConcepts $concepts, array $lessons): array
    {
        $covered = [];

        foreach ($document->conceptTags() as $tag) {
            foreach ($concepts->taughtIn($tag) as $position) {
                $covered[$position] = true;
            }
        }

        $missing = [];

        foreach ($lessons as $position => $title) {
            if (! isset($covered[$position])) {
                $missing[] = "{$position} ({$title})";
            }
        }

        return $missing === []
            ? []
            : ['It asks nothing from lesson '.implode(', nor from lesson ', $missing).'.'];
    }

    /**
     * Every lesson the syllabus teaches, by position.
     *
     * @param  array<string, mixed>  $syllabus  a `json/syllabus.v1` document
     * @return array<int, string> position => title
     */
    private function lessonTitles(array $syllabus): array
    {
        $lessons = [];

        foreach ($syllabus['lessons'] as $lesson) {
            $lessons[(int) $lesson['position']] = (string) $lesson['title'];
        }

        ksort($lessons);

        return $lessons;
    }

    /**
     * An exam the wrong length to grade against a fraction.
     *
     * The schema already states the length, and a provider that honours it will
     * never reach this. It is checked again here because this is the one quiz
     * whose length changes what passing means: `pass_mark` is a fraction, so a
     * three-question exam quietly turns 0.7 into "get all three right". The
     * two kinds that do not gate anything are left to the schema alone --
     * failing a homework generation over a question count would park a
     * completed lesson's pipeline for a cosmetic reason (5.4.5).
     *
     * @return list<string>
     */
    private function misjudgedLength(QuizDocument $document, QuizLength $length): array
    {
        $asked = count($document->questions());

        if ($asked >= $length->min && $asked <= $length->max) {
            return [];
        }

        return ["It asks {$asked} questions; an exam graded against a pass mark needs {$length->describe()}."];
    }
}
