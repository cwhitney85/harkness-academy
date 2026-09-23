<?php

namespace App\Assessment;

use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\ExamUnusable;
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
 * Opening the book, off the request that asked for it (ledger D25).
 *
 * The same move as `DrawUpSyllabus` and for the same reason: writing an exam
 * is the most expensive call in the system (6.3) and takes longer than any
 * edge will hold a request open.
 *
 * `FinalExam::open()` both writes the exam and moves the course to
 * `final_exam`, and that pairing is what S9 reads to decide it is showing an
 * open book. So this job changes nothing about the outcome -- it only moves
 * where the waiting happens.
 */
class WriteFinalExam implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** One attempt, for `DrawUpSyllabus`'s reason: the service already retries. */
    public int $tries = 1;

    public function __construct(public readonly int $courseId) {}

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
     * Pre:  a `GenerationRequest` for this course's exam is open.
     * Post: either the exam exists with the course at `final_exam` and the
     *       request is gone, or nothing was written, nothing moved, and the
     *       request carries what the student is owed (6.3 fails closed).
     */
    public function handle(FinalExam $exams): void
    {
        $course = Course::query()->find($this->courseId);

        if (! $course instanceof Course) {
            return;
        }

        $request = GenerationRequest::for($course, GenerationKind::FinalExam);

        try {
            // Opening an exam that already exists adopts it, so a redelivered
            // job is a no-op rather than a second `top`-tier bill.
            $exams->open($course);
        } catch (ExamNotAvailable $e) {
            $request?->fail($e->getMessage());

            return;
        } catch (ExamUnusable $e) {
            Log::warning('Exam generation failed on the queue.', [
                'course_id' => $this->courseId,
                'reason' => $e->getMessage(),
            ]);

            $request?->fail('The exam could not be written. Nothing has changed; try again in a moment.');

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

        Log::warning('Exam generation was killed before it could report.', [
            'course_id' => $this->courseId,
            'reason' => $e?->getMessage(),
        ]);

        GenerationRequest::for($course, GenerationKind::FinalExam)
            ?->fail('The exam could not be written. Nothing has changed; try again in a moment.');
    }
}
