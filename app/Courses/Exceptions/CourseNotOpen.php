<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * Work attempted on a course the student is not currently taking.
 *
 * A withdrawn course must be resumed first; a completed one is finished.
 */
final class CourseNotOpen extends CourseException
{
    public static function for(Course $course): self
    {
        return new self(sprintf(
            'Course [%d] is not open: status [%s]%s.',
            $course->id,
            $course->status->value,
            $course->isWithdrawn() ? ', withdrawn '.$course->withdrawn_at?->toDateString() : '',
        ));
    }
}
