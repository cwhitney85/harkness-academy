<?php

namespace App\Courses\Syllabus;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\Exceptions\SyllabusUnusable;
use App\Courses\GenerationKind;
use App\Courses\SkipRemovedCourse;
use App\Models\Course;
use App\Models\GenerationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drawing up a syllabus, off the request that asked for it (ledger D25).
 *
 * A `top`-tier generation runs for minutes and the edge cuts a request long
 * before that, so S5 asks for the work and then watches for it. Everything the
 * screen used to learn from the response it now learns from the
 * `GenerationRequest` row: still out, failed, or gone because the draft is
 * here.
 *
 * Both doors are this one job. A revision is the same call with the student's
 * feedback attached, and splitting them would give one lifecycle two homes.
 *
 * Holds the course **id**, not the model, for `PipelineJob`'s reason: the
 * generator transitions the course, and a serialized copy would be stale by
 * the time it ran.
 */
class DrawUpSyllabus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * One attempt, because `SyllabusGenerator` already makes two (4.5.1) and
     * a third is a third `top`-tier bill for a model that has now failed
     * twice. A queue retry on top of that would be spending real money on the
     * same hope.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $courseId,
        public readonly ?string $feedback = null,
    ) {}

    /**
     * A course removed mid-call ends the job quietly (16.2.8).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SkipRemovedCourse];
    }

    /**
     * Pre:  a `GenerationRequest` for this course's syllabus is open.
     * Post: either a draft exists and the request is gone, or nothing was
     *       written and the request carries the sentence the student is owed.
     *       The course is never left mid-move: the generator fails closed.
     */
    public function handle(SyllabusGenerator $generator): void
    {
        $course = Course::query()->find($this->courseId);

        if (! $course instanceof Course) {
            return;
        }

        $request = GenerationRequest::for($course, GenerationKind::Syllabus);

        try {
            $this->feedback === null
                ? $generator->first($course)
                : $generator->revision($course, $this->feedback);
        } catch (SyllabusNotDraftable $e) {
            // The draft this job was dispatched for already exists. That is
            // what a redelivered job looks like -- managed queues deliver at
            // least once -- and the student is owed a syllabus, not an error
            // about one they can already see.
            if ($course->syllabi()->exists()) {
                $request?->settle();

                return;
            }

            $request?->fail($e->getMessage());

            return;
        } catch (SyllabusUnusable|MissingCourseDocument|CourseNotOpen $e) {
            Log::warning('Syllabus generation failed on the queue.', [
                'course_id' => $this->courseId,
                'revision' => $this->feedback !== null,
                'reason' => $e->getMessage(),
            ]);

            $request?->fail($e instanceof SyllabusUnusable
                ? 'The syllabus could not be drawn up just now. Nothing has changed — ask again.'
                : $e->getMessage());

            return;
        }

        $request?->settle();
    }

    /**
     * The job died without reaching its own `catch` (ledger D25).
     *
     * A worker `--timeout` kill, an out-of-memory restart, a Flex worker
     * reclaimed mid-run: none of them unwind through `handle`, so without this
     * the request row stays open and the student watches a screen that polls
     * for a generation nobody is running. The wait has to end in an answer,
     * and "ask again" is a true one -- the generators fail closed, so nothing
     * was written and nothing moved.
     */
    public function failed(?Throwable $e): void
    {
        $course = Course::query()->find($this->courseId);

        if (! $course instanceof Course) {
            return;
        }

        Log::warning('Syllabus generation was killed before it could report.', [
            'course_id' => $this->courseId,
            'reason' => $e?->getMessage(),
        ]);

        GenerationRequest::for($course, GenerationKind::Syllabus)
            ?->fail('The syllabus could not be drawn up just now. Nothing has changed — ask again.');
    }
}
