<?php

namespace App\Lessons\Exceptions;

use Throwable;

/**
 * The model could not produce a usable checkpoint (spec 5.2.1).
 *
 * Caught, never surfaced: 5.2.1 fails **open**, so the section completes
 * quizless and the incident is logged. This type exists so the fail-open path
 * catches one named thing rather than every Throwable, which would swallow
 * bugs in the persistence that follows generation.
 */
final class CheckpointUnusable extends LessonException
{
    public static function after(int $attempts, ?Throwable $previous = null): self
    {
        return new self("No usable checkpoint after {$attempts} attempts.", previous: $previous);
    }
}
