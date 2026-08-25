<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * A turn was offered to an interview that is not running.
 *
 * Either it was never opened, or it has already been summarized -- in which
 * case its transcript is gone (spec 4.3) and there is nothing to append to.
 */
final class InterviewNotUnderway extends CourseException
{
    public static function for(Course $course, string $kind): self
    {
        return new self(sprintf(
            'The %s interview for course [%d] is not underway; open it before answering.',
            $kind,
            $course->id,
        ));
    }
}
