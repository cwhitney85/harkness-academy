<?php

namespace App\Lessons\Checkpoints;

use App\Courses\GenerationKind;
use App\Models\GenerationRequest;
use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writing a section's checkpoint, off the stream that taught it (spec 11.1).
 *
 * The section stream used to end with a blocking `checkpoint.generate` after
 * `turn.end`, writing nothing while it ran, and the host cuts a response that
 * is silent for 60 seconds (T10). So the stream ends with the tutor's turn,
 * and the screen learns the checkpoint exists the way S5 learns a syllabus
 * does: the request row goes, and the quiz is there.
 *
 * Fails open like the generator it wraps (5.2.1): every ending settles the
 * request, because there is no failure the student could act on -- the section
 * simply has no checkpoint, and the screen says so.
 */
class WriteCheckpoint implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** One attempt, for `DrawUpSyllabus`'s reason: the generator already retries. */
    public int $tries = 1;

    /** What the margin says when a section has no checkpoint (11.3). */
    private const FAILURE = 'No questions for this section this time.';

    public function __construct(
        public readonly int $lessonId,
        public readonly int $sectionIndex,
    ) {}

    /**
     * Pre:  the section's teaching turn is in the transcript.
     * Post: the course's checkpoint request has landed with the section's
     *       checkpoint, or failed open without one. A redelivered job adopts
     *       the checkpoint that already exists.
     */
    public function handle(CheckpointGenerator $checkpoints): void
    {
        $lesson = Lesson::query()->find($this->lessonId);

        if (! $lesson instanceof Lesson) {
            return;
        }

        $checkpoint = $checkpoints->forSection($lesson, $this->sectionIndex);
        $request = GenerationRequest::for($lesson->course, GenerationKind::Checkpoint);

        $checkpoint === null ? $request?->fail(self::FAILURE) : $request?->settle();
    }

    /**
     * The job died without finishing -- `DrawUpSyllabus`'s reason. Failing open
     * means the wait ends with no checkpoint rather than with an error.
     */
    public function failed(?Throwable $e): void
    {
        $lesson = Lesson::query()->find($this->lessonId);

        if (! $lesson instanceof Lesson) {
            return;
        }

        Log::warning('Checkpoint generation was killed before it could finish; the section completes quizless.', [
            'lesson_id' => $this->lessonId,
            'section_index' => $this->sectionIndex,
            'reason' => $e?->getMessage(),
        ]);

        GenerationRequest::for($lesson->course, GenerationKind::Checkpoint)?->fail(self::FAILURE);
    }
}
