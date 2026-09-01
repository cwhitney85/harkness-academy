<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * An interview that has already been opened was asked to open again.
 *
 * `InterviewSession::open()` discards the transcript, so re-opening a
 * conversation the tutor has already spoken in destroys the student's
 * interview -- the one thing spec 8.7.2's done-condition checks. The flow map
 * makes it a client precondition ("the stream URL may be requested only when
 * the restored transcript holds nothing but the opening turn"); this is the
 * same rule enforced where a client bug cannot get past it.
 */
final class InterviewAlreadyUnderway extends CourseException
{
    public static function for(Course $course, string $kind): self
    {
        return new self(sprintf(
            'The %s interview for course [%d] is already underway; reload it rather than re-opening it.',
            $kind,
            $course->id,
        ));
    }
}
