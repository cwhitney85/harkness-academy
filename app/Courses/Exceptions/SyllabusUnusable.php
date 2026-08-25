<?php

namespace App\Courses\Exceptions;

use App\Models\Course;
use Throwable;

/**
 * The model could not produce a usable syllabus (spec 4.5.1).
 *
 * Fails **closed**, against the global rule's default: failing open would mean
 * showing the student a half-built course, and nothing downstream exists yet
 * to protect. Nothing is written -- no row, no transition -- so the student's
 * retry starts from exactly where they were (ledger D10.4).
 *
 * The one condition here that reaches a student's screen; the other syllabus
 * exceptions are caller bugs.
 */
final class SyllabusUnusable extends CourseException
{
    public static function after(Course $course, int $attempts, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Course [%d] got no usable syllabus in %d attempt(s): %s',
                $course->id,
                $attempts,
                $previous?->getMessage() ?? 'no further detail.',
            ),
            previous: $previous,
        );
    }
}
