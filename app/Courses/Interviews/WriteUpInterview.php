<?php

namespace App\Courses\Interviews;

use App\Courses\Exceptions\InterviewSummaryUnusable;
use App\Courses\GenerationKind;
use App\Models\Course;
use App\Models\GenerationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writing up an interview, off the request that ended it (spec 11.4).
 *
 * `wk.create` and `criteria.create` are blocking calls, and they used to run
 * at the end of the answer's stream -- after the tutor's last word, writing
 * nothing -- where the host cuts a silent response at 60 seconds (T10). The
 * screen now waits on the `GenerationRequest` row the way S5 waits on a
 * syllabus, and learns the write-up landed because the course has moved.
 *
 * Fails closed, like the write-up itself: the transcript stays, the course
 * stays, and the row carries the sentence that sends the student back to Done.
 */
class WriteUpInterview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const FAILURE = 'That could not be written up just now. Nothing is lost — press Done to try again.';

    /**
     * One attempt: a failed write-up is the student's to retry, and Done is
     * how they do it.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $courseId,
        public readonly InterviewKind $kind,
    ) {}

    /**
     * Pre:  a `GenerationRequest` for this course's interview write-up is open.
     * Post: either the document is written, the transcript gone and the course
     *       moved, with the request gone; or none of those, and the request
     *       carries what the student is owed. A redelivered job finds the
     *       transcript already gone and settles.
     */
    public function handle(Interviews $interviews): void
    {
        $course = Course::query()->find($this->courseId);

        if (! $course instanceof Course) {
            return;
        }

        $request = GenerationRequest::for($course, GenerationKind::InterviewSummary);

        try {
            $interviews->for($course, $this->kind)->writeUp();
        } catch (InterviewSummaryUnusable $e) {
            Log::warning('Interview write-up failed on the queue.', [
                'course_id' => $this->courseId,
                'kind' => $this->kind->value,
                'reason' => $e->getMessage(),
            ]);

            $request?->fail(self::FAILURE);

            return;
        }

        $request?->settle();
    }

    /**
     * The job died without reaching its own `catch` -- `DrawUpSyllabus`'s
     * reason, and the same obligation: a wait has to end in an answer.
     */
    public function failed(?Throwable $e): void
    {
        $course = Course::query()->find($this->courseId);

        if (! $course instanceof Course) {
            return;
        }

        Log::warning('Interview write-up was killed before it could report.', [
            'course_id' => $this->courseId,
            'kind' => $this->kind->value,
            'reason' => $e?->getMessage(),
        ]);

        GenerationRequest::for($course, GenerationKind::InterviewSummary)?->fail(self::FAILURE);
    }
}
