<?php

namespace App\Courses\Syllabus;

use App\Ai\Gateway;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\DepthParameters;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\CourseRemoved;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\Exceptions\SyllabusUnusable;
use App\Courses\Exceptions\UndeclaredConcept;
use App\Memory\MemoryDocument;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turning what the interviews learned into a course (spec 4.5.1, 4.5.4).
 *
 * Both doors -- the first draft and each revision -- end in the same place: a
 * `course_syllabi` row pinned to the documents the model actually read
 * (ledger D10.1). What differs is the prompt and whether feedback came with it.
 *
 * **Nothing is written unless the model produced a usable syllabus.** One
 * retry, then fail closed (4.5.1's rule, and D9's precedent at the interview).
 * That is what lets `generation` count what the student saw rather than what
 * was attempted, and what lets a failed revision leave the previous draft
 * exactly where it was.
 *
 * This class performs no transition on the way in for a first draft: the goals
 * interview already moved the course to `syllabus_draft` when it wrote
 * COURSE_CRITERIA.md, so `syllabus_draft` means "in the drafting stage", and
 * the existence of a row is what answers "is there a draft?" (ledger D10.4).
 */
final class SyllabusGenerator
{
    /**
     * One retry, per spec 4.5.1 -- a plain re-issue of the same call.
     *
     * A repair turn ("your last output was invalid") would score better and
     * cost more: it breaks the cached prefix (2.4) and bills the invalid
     * output back as input (ledger D10.4).
     */
    private const ATTEMPTS = 2;

    private const FEATURE = 'course.syllabus';

    public function __construct(
        private readonly Gateway $gateway,
        private readonly MemoryTree $memory,
        private readonly CourseTransitions $transitions,
    ) {}

    /**
     * The first syllabus a course is shown.
     *
     * Pre: the course is open, stands at `syllabus_draft`, and has no
     *      syllabus rows.
     * Post: one row at generation 1, feedback null; or nothing at all.
     *
     * @throws CourseNotOpen|SyllabusNotDraftable|MissingCourseDocument|SyllabusUnusable
     */
    public function first(Course $course): CourseSyllabus
    {
        $this->assertDraftable($course);

        [$wk, $criteria] = $this->inputs($course);

        return $this->draft($course, 'syllabus.generate', [
            'wk_doc' => $wk->content,
            'criteria_doc' => $criteria->content,
            'skill_level' => $course->skill_level->pathSegment(),
            'depth_params' => $this->depth($course),
        ], generation: 1, feedback: null, wk: $wk, criteria: $criteria);
    }

    /**
     * Send the syllabus back with notes and get another one.
     *
     * Pre: the course is open, stands at `syllabus_draft` or -- on a retry
     *      after a failed regeneration -- `syllabus_revision`, has an
     *      unapproved draft, and has revisions left.
     * Post: a new row one generation on, carrying the feedback that produced
     *       it, and the course back at `syllabus_draft`.
     * On failure: the course stays at `syllabus_revision` and the previous
     *       draft is untouched, so the student can try again or approve what
     *       they already have.
     *
     * @throws CourseNotOpen|SyllabusNotDraftable|MissingCourseDocument|SyllabusUnusable
     */
    public function revision(Course $course, string $feedback): CourseSyllabus
    {
        $previous = $this->assertRevisable($course, $feedback);

        if ($course->status === CourseStatus::SyllabusDraft) {
            $this->transitions->to($course, CourseStatus::SyllabusRevision);
        } elseif ($course->status !== CourseStatus::SyllabusRevision) {
            throw SyllabusNotDraftable::because($course, "it stands at [{$course->status->value}].");
        }

        [$wk, $criteria] = $this->inputs($course);

        $syllabus = $this->draft($course, 'syllabus.revise', [
            'prev_syllabus_json' => $this->encode($previous->syllabus_json),
            'feedback' => $feedback,
            'wk_doc' => $wk->content,
            'criteria_doc' => $criteria->content,
            'depth_params' => $this->depth($course),
        ], generation: $previous->generation + 1, feedback: $feedback, wk: $wk, criteria: $criteria);

        $this->transitions->to($course, CourseStatus::SyllabusDraft);

        return $syllabus;
    }

    /**
     * @param  array<string, string>  $vars
     *
     * @throws SyllabusUnusable
     */
    private function draft(
        Course $course,
        string $promptKey,
        array $vars,
        int $generation,
        ?string $feedback,
        MemoryDocument $wk,
        MemoryDocument $criteria,
    ): CourseSyllabus {
        $syllabus = $this->askUntilUsable($course, $promptKey, $vars);

        return $course->syllabi()->create([
            'generation' => $generation,
            'syllabus_json' => $syllabus,
            'feedback' => $feedback,
            'wk_version_id' => $wk->versionId,
            'criteria_version_id' => $criteria->versionId,
        ]);
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, mixed>
     *
     * @throws SyllabusUnusable
     */
    private function askUntilUsable(Course $course, string $promptKey, array $vars): array
    {
        $failure = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                return $this->ask($promptKey, $vars, $course);
            } catch (CourseRemoved $e) {
                // Gone mid-call (16.2.8): a retry would pay again for nothing.
                throw $e;
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        // The student is told only that nothing has changed and to ask again,
        // which is the right thing to tell them and no help at all to anyone
        // working out why. Two `top`-tier generations have just been spent and
        // billed; the reason they were wasted is written down here or nowhere.
        Log::error('Syllabus generation gave up; the student was asked to try again.', [
            'course_id' => $course->id,
            'prompt_key' => $promptKey,
            'attempts' => self::ATTEMPTS,
            // Reaching here means every attempt threw, so there is always a
            // reason to give.
            'reason' => $failure->getMessage(),
        ]);

        throw SyllabusUnusable::after($course, self::ATTEMPTS, $failure);
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, mixed>
     *
     * @throws UndeclaredConcept|SyllabusUnusable|CourseRemoved
     */
    private function ask(string $promptKey, array $vars, Course $course): array
    {
        $response = $this->gateway
            ->prompt($promptKey, $vars)
            ->feature(self::FEATURE)
            ->about($course)
            ->schema(SyllabusSchema::definition())
            ->generate();

        $syllabus = $response->structured;

        if (! is_array($syllabus)) {
            throw new SyllabusUnusable('The model returned no structured syllabus.');
        }

        $syllabus = self::asProse($syllabus);

        self::assertCoherent($syllabus);

        return $syllabus;
    }

    /**
     * Undo a model that escaped its own prose one level too many (spec 4.8).
     *
     * A generation on 2026-09-02 returned objectives reading `c. 476\u20131500`
     * and an overview with `\n\n` where a paragraph break belonged. Every
     * consumer takes these strings as display text -- the syllabus screen, the
     * lesson completion screen, and the GUIDANCE document the tutor reads back
     * as context -- and none can tell the difference, so the repair belongs
     * here, at the one place a syllabus is accepted.
     *
     * A string is repaired only if the whole of it reads as a valid JSON
     * string body; anything else is left exactly as it came. That keeps the
     * decode conservative -- a stray backslash repairs nothing rather than
     * something -- at the cost of taking a deliberate `\n` at its word, which
     * no syllabus has yet wanted to write.
     *
     * @param  array<string, mixed>  $syllabus
     * @return array<string, mixed>
     */
    private static function asProse(array $syllabus): array
    {
        array_walk_recursive($syllabus, static function (mixed &$value): void {
            if (! is_string($value)) {
                return;
            }

            $decoded = json_decode('"'.str_replace('"', '\\"', $value).'"');

            if (is_string($decoded)) {
                $value = $decoded;
            }
        });

        return $syllabus;
    }

    /**
     * The rules the JSON schema cannot state.
     *
     * Positions have to be a clean 1..N run because lesson rows and GUIDANCE
     * paths are both built from them (4.5.5, 4.5.3), and the exam may only
     * draw on concepts a lesson actually teaches (4.5.2's done-condition).
     *
     * @param  array<string, mixed>  $syllabus
     *
     * @throws UndeclaredConcept|SyllabusUnusable
     */
    public static function assertCoherent(array $syllabus): void
    {
        $positions = array_map(static fn (array $lesson): int => (int) $lesson['position'], $syllabus['lessons']);

        sort($positions);

        if ($positions !== range(1, count($positions))) {
            throw new SyllabusUnusable('Lesson positions are not a 1..N run: ['.implode(', ', $positions).'].');
        }

        SyllabusConcepts::from($syllabus)->assertDeclared($syllabus['final_exam_concepts']);
    }

    /**
     * The two documents a syllabus is built from, with their versions.
     *
     * Pre: both interviews have written their documents. The state machine
     * guarantees it -- reaching `syllabus_draft` is what writes the second --
     * so an absence is a bug, not a case to paper over.
     *
     * @return array{0: MemoryDocument, 1: MemoryDocument}
     *
     * @throws MissingCourseDocument
     */
    private function inputs(Course $course): array
    {
        return [
            $this->read($course, $course->workingKnowledgePath()),
            $this->read($course, CourseCriteria::pathFor($course)),
        ];
    }

    /**
     * @throws MissingCourseDocument
     */
    private function read(Course $course, string $path): MemoryDocument
    {
        $document = $this->memory->document($course->student, $path);

        if ($document === null || trim($document->content) === '') {
            throw MissingCourseDocument::at($course, $path);
        }

        return $document;
    }

    private function depth(Course $course): string
    {
        return DepthParameters::for($course->skill_level, $course->student->plan)->render();
    }

    /**
     * @param  array<string, mixed>  $syllabus
     */
    private function encode(array $syllabus): string
    {
        return json_encode($syllabus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @throws CourseNotOpen
     */
    private function assertOpen(Course $course): void
    {
        if (! $course->isOpen()) {
            throw CourseNotOpen::for($course);
        }
    }

    /**
     * Why this course cannot be asked for a syllabus right now, or null.
     *
     * Asking without generating, on `CourseTransitions::refusal()`'s precedent
     * (8.10.3). Generation runs on the queue now (ledger D25), so a controller
     * that dispatched first would turn "you have used all three revisions"
     * into a wait followed by a failure, when it is knowable in the request.
     *
     * The checks are not restated here -- this runs the same two asserts the
     * generators run, so the two can never drift apart.
     */
    public function refusal(Course $course, ?string $feedback = null): ?string
    {
        try {
            $feedback === null
                ? $this->assertDraftable($course)
                : $this->assertRevisable($course, $feedback);
        } catch (CourseNotOpen|SyllabusNotDraftable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Pre-flight for generation 1.
     *
     * @throws CourseNotOpen|SyllabusNotDraftable
     */
    private function assertDraftable(Course $course): void
    {
        $this->assertOpen($course);

        if ($course->status !== CourseStatus::SyllabusDraft) {
            throw SyllabusNotDraftable::because($course, "it stands at [{$course->status->value}], not [syllabus_draft].");
        }

        if ($course->syllabi()->exists()) {
            throw SyllabusNotDraftable::because($course, 'it already has a syllabus; revise it instead.');
        }
    }

    /**
     * Pre-flight for a revision, handing back the draft being revised.
     *
     * @throws CourseNotOpen|SyllabusNotDraftable
     */
    private function assertRevisable(Course $course, string $feedback): CourseSyllabus
    {
        $this->assertOpen($course);

        $previous = $course->latestSyllabus()->first();

        if ($previous === null) {
            throw SyllabusNotDraftable::because($course, 'there is no draft to revise.');
        }

        if ($course->approvedSyllabus()->exists()) {
            throw SyllabusNotDraftable::because($course, 'its syllabus is already approved.');
        }

        $used = $previous->generation - 1;
        $cap = (int) config('courses.max_syllabus_revisions');

        if ($used >= $cap) {
            throw SyllabusNotDraftable::because($course, "all {$cap} revisions are used; approve it or start over.");
        }

        if (trim($feedback) === '') {
            throw SyllabusNotDraftable::because($course, 'a revision needs feedback saying what to change.');
        }

        return $previous;
    }
}
