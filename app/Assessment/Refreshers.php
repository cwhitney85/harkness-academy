<?php

namespace App\Assessment;

use App\Ai\Gateway;
use App\Assessment\Exceptions\RefresherNotAvailable;
use App\Assessment\Exceptions\RefresherUnusable;
use App\Courses\ModuleRollup;
use App\Courses\SubtopicPointer;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Throwable;

/**
 * Quizzes that draw on what was recorded rather than on what was just taught
 * (spec 6.4).
 *
 * Three grains -- one lesson, one subtopic, one module (6.5) -- and they
 * differ only in which documents the model is shown and which row the quiz
 * hangs off. That is why RefresherScope exists: build the scope, and
 * generation is one path.
 *
 * **Every refresher is a new quiz.** `quizzes_one_per_scope` excludes this
 * kind (6.1's migration) because coming back to the same lesson next month and
 * being asked the same questions is the failure a refresher exists to avoid.
 *
 * **The prompt's hard rule is enforced in code.** `quiz.refresher` says "only
 * ask about what these documents actually record"; a prompt rule is a hope, so
 * every question's concept tag is checked against the scope's vocabulary and
 * an out-of-scope quiz is regenerated rather than served. For a lesson that
 * vocabulary is its summary's own slugs, which is 6.4's done-condition --
 * "lesson refresher questions traceable to its summary" -- as an assertion
 * rather than an aspiration.
 */
final class Refreshers
{
    private const FEATURE = 'quiz.refresher';

    public function __construct(
        private readonly Gateway $gateway,
        private readonly MemoryTree $memory,
        private readonly Quizzes $quizzes,
        private readonly ModuleRollup $rollup,
    ) {}

    /**
     * A refresher over one finished lesson, from its SUMMARY.md (spec 6.4).
     *
     * Pre: the lesson is completed and its summary has been written and
     *      rendered -- chain step one (5.4.2, 5.4.3).
     * Post: a new `refresher` quiz hanging off the lesson, every question
     *       tagged with a concept that lesson's summary records.
     *
     * @throws RefresherNotAvailable|RefresherUnusable
     */
    public function forLesson(Lesson $lesson): Quiz
    {
        $title = (string) $lesson->syllabusEntry()['title'];
        $label = "the lesson \"{$title}\"";

        $summary = $lesson->summary();

        if ($summary === null) {
            throw RefresherNotAvailable::because($label, 'it has not been summarized yet.');
        }

        $path = SummaryDocument::pathFor($lesson);
        $document = $this->memory->get($lesson->course->student, $path);

        if ($document === null) {
            throw RefresherNotAvailable::because($label, "its summary document at [{$path}] is gone.");
        }

        return $this->generate(new RefresherScope(
            grain: RefresherGrain::Lesson,
            quizzable: $lesson,
            student: $lesson->course->student,
            course: $lesson->course,
            label: $label,
            // The one document, and nothing else. Adding the working-knowledge
            // document here would make richer questions and break the claim
            // this grain exists to support.
            documents: self::section($path, $document),
            vocabulary: $summary->slugs(),
        ));
    }

    /**
     * A refresher over a whole subtopic (spec 6.4).
     *
     * From the subtopic's working-knowledge document *and* the summaries of
     * every lesson under it -- 6.4's "WK + SUMMARY docs". The WK says where
     * the student stands; the summaries say what they were actually shown, and
     * a quiz built from the WK alone can only ask about bullet points.
     *
     * Hangs off the anchoring course rather than a `subtopics` row, because a
     * catalog row is a suggestion and not identity: a subtopic typed freehand
     * has no row at all (spec 4.1), so half of every student's subtopics would
     * have nothing to hang from.
     *
     * @throws RefresherNotAvailable|RefresherUnusable
     */
    public function forSubtopic(Course $course): Quiz
    {
        $label = "the subtopic \"{$course->subtopic_title}\"";

        $courses = $this->subtopicCourses($course);

        // The newest course in the subtopic anchors the row: its syllabus is
        // the most recent statement of the vocabulary the tags resolve
        // against. The list always contains `$course` itself, so the fallback
        // is unreachable rather than a default.
        $anchor = $courses === [] ? $course : $courses[count($courses) - 1];

        $path = $course->workingKnowledgePath();
        $wk = $this->memory->get($course->student, $path);

        if ($wk === null || trim($wk) === '') {
            throw RefresherNotAvailable::because($label, "nothing is recorded at [{$path}] yet.");
        }

        $documents = [self::section($path, $wk)];

        foreach ($courses as $sibling) {
            foreach ($sibling->lessons as $lesson) {
                if (! $lesson->isCompleted()) {
                    continue;
                }

                $summaryPath = SummaryDocument::pathFor($lesson);
                $summary = $this->memory->get($course->student, $summaryPath);

                if ($summary !== null) {
                    $documents[] = self::section($summaryPath, $summary);
                }
            }
        }

        return $this->generate(new RefresherScope(
            grain: RefresherGrain::Subtopic,
            quizzable: $anchor,
            student: $course->student,
            course: $anchor,
            label: $label,
            documents: implode("\n\n", $documents),
            vocabulary: $this->vocabularyOf($courses),
        ));
    }

    /**
     * A refresher over a whole module (spec 6.5).
     *
     * Built by walking the 3.3 rollup's pointers and reading the document each
     * one names. That is the point of the rollup being derived rather than
     * materialised: `{module}/WORKING_KNOWLEDGE.md` is never written, so a
     * quiz over a module has to be assembled from the subtopic documents that
     * are, and the pointer list is the index of which those are.
     *
     * Hangs off the `modules` row, the one catalog row a course is always
     * created from -- free text names a *subtopic* within a chosen module
     * (4.1), so unlike a subtopic a module always exists.
     *
     * Anchored to no course: its vocabulary spans every subtopic the rollup
     * points at, across levels, and naming one course would make
     * `quizzes.course_id` say something untrue.
     *
     * @throws RefresherNotAvailable|RefresherUnusable
     */
    public function forModule(User $student, Module $module): Quiz
    {
        $label = "the module \"{$module->title}\"";

        $pointers = $this->rollup->pointers($student, $module);

        if ($pointers === []) {
            throw RefresherNotAvailable::because($label, 'no subtopic under it has a knowledge record yet.');
        }

        $documents = [];

        foreach ($pointers as $pointer) {
            $document = $this->memory->get($student, $pointer->path);

            if ($document !== null && trim($document) !== '') {
                $documents[] = self::section($pointer->path, $document);
            }
        }

        if ($documents === []) {
            throw RefresherNotAvailable::because($label, 'every subtopic record under it is empty.');
        }

        return $this->generate(new RefresherScope(
            grain: RefresherGrain::Module,
            quizzable: $module,
            student: $student,
            course: null,
            label: $label.' ('.$this->subtopicList($pointers).')',
            documents: implode("\n\n", $documents),
            vocabulary: $this->vocabularyOf($this->moduleCourses($student, $module)),
        ));
    }

    /**
     * Write and store one refresher.
     *
     * @throws RefresherUnusable
     */
    private function generate(RefresherScope $scope): Quiz
    {
        return $this->quizzes->create(
            QuizKind::Refresher,
            $scope->quizzable,
            $scope->student,
            $scope->course,
            $this->askUntilUsable($scope)->payload(),
        );
    }

    /**
     * @throws RefresherUnusable
     */
    private function askUntilUsable(RefresherScope $scope): QuizDocument
    {
        $attempts = (int) config('assessment.refresher.generation_attempts', 2);
        $reasons = [];
        $failure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->gateway
                    ->prompt('quiz.refresher', $scope->promptVars())
                    ->feature(self::FEATURE)
                    ->for($scope->student)
                    ->schema(QuizSchema::definition())
                    ->generate();

                if (! is_array($response->structured)) {
                    $reasons = ['The model returned no structured quiz.'];

                    continue;
                }

                $document = QuizDocument::in($response->structured, QuizKind::Refresher)
                    ->withResolvedTags($scope->vocabulary);

                $defects = [...$document->defects(), ...$this->strayed($document)];

                if ($defects === []) {
                    return $document;
                }

                $reasons = $defects;
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        throw RefresherUnusable::after($attempts, $reasons, $failure);
    }

    /**
     * Questions that left the scope.
     *
     * A tag outside the vocabulary has already been resolved to null, so a
     * null tag here means one of two things -- the model tagged nothing, or it
     * tagged something the documents do not record -- and both are the same
     * failure for a refresher: a question the student cannot be expected to
     * have been taught.
     *
     * @return list<string>
     */
    private function strayed(QuizDocument $document): array
    {
        $untraceable = [];

        foreach ($document->questions() as $question) {
            if (($question['concept_tag'] ?? null) === null) {
                $untraceable[] = (string) $question['id'];
            }
        }

        return $untraceable === []
            ? []
            : ['Question(s) ['.implode(', ', $untraceable).'] ask about something these documents do not record.'];
    }

    /**
     * What the label names, so the model knows how wide it is casting.
     *
     * @param  list<SubtopicPointer>  $pointers
     */
    private function subtopicList(array $pointers): string
    {
        return implode(', ', array_map(
            static fn (SubtopicPointer $pointer): string => $pointer->title,
            $pointers,
        ));
    }

    /**
     * Every course this student has taken anywhere in the module, oldest first.
     *
     * @return list<Course>
     */
    private function moduleCourses(User $student, Module $module): array
    {
        /** @var list<Course> $courses */
        $courses = Course::query()
            ->where('user_id', $student->id)
            ->where('module_slug', $module->slug)
            ->with('approvedSyllabus')
            ->orderBy('id')
            ->get()
            ->all();

        return $courses;
    }

    /**
     * Every course this student has taken in the subtopic, oldest first.
     *
     * @return list<Course>
     */
    private function subtopicCourses(Course $course): array
    {
        /** @var list<Course> $courses */
        $courses = Course::query()
            ->forSubtopic($course->student, $course->module_slug, $course->subtopic_slug)
            ->with(['approvedSyllabus', 'lessons'])
            ->orderBy('id')
            ->get()
            ->all();

        return $courses;
    }

    /**
     * The union of what a set of courses declared, in slug order.
     *
     * The same widening ErosionGuard applies for the same reason (ledger D8):
     * a subtopic's record accumulates across skill levels, so a 201 course's
     * refresher may legitimately ask about something 101 taught.
     *
     * @param  list<Course>  $courses
     * @return list<string>
     */
    private function vocabularyOf(array $courses): array
    {
        $slugs = [];

        foreach ($courses as $course) {
            if ($course->approvedSyllabus === null) {
                continue;
            }

            foreach (SyllabusConcepts::of($course->approvedSyllabus)->declared() as $slug) {
                $slugs[$slug] = true;
            }
        }

        $slugs = array_keys($slugs);
        sort($slugs);

        return $slugs;
    }

    /**
     * One document, headed by the path it came from.
     *
     * The path is in the prompt on purpose: it tells the model these are
     * records of a particular student rather than reference material, which is
     * the framing the "only ask about what these record" rule depends on.
     */
    private static function section(string $path, string $content): string
    {
        return "### `{$path}`\n\n".trim($content);
    }
}
