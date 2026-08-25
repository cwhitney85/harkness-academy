<?php

namespace App\Lessons;

use App\Ai\Gateway;
use App\Lessons\Checkpoints\CheckpointGenerator;
use App\Models\Lesson;

/**
 * Opens lesson sessions (spec 5.1).
 *
 * A session is bound to one lesson, so it cannot be a shared service; this is
 * the container-resolvable thing that makes one. The same shape as
 * `App\Courses\Interviews\Interviews`, for the same reason.
 */
final class LessonSessions
{
    public function __construct(
        private readonly Gateway $gateway,
        private readonly LessonContext $context,
        private readonly CheckpointGenerator $checkpoints,
        private readonly LessonProgress $progress,
    ) {}

    public function for(Lesson $lesson): LessonSession
    {
        return new LessonSession(
            $this->gateway,
            $this->context,
            $this->checkpoints,
            $this->progress,
            $lesson,
        );
    }
}
