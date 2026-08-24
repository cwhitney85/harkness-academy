<?php

namespace App\Courses\Exceptions;

use App\Courses\CourseStatus;
use App\Models\Course;

/**
 * A status move that is not an arrow in the state machine (ledger D7).
 *
 * Always a caller bug: the transition service is the only writer of
 * `courses.status`, and it refuses before touching the row.
 */
final class IllegalTransition extends CourseException
{
    public static function between(Course $course, CourseStatus $from, CourseStatus $to): self
    {
        $allowed = array_map(
            fn (CourseStatus $status): string => $status->value,
            $from->successors(),
        );

        return new self(sprintf(
            'Course [%d] cannot move from [%s] to [%s]. Allowed from here: %s.',
            $course->id,
            $from->value,
            $to->value,
            $allowed === [] ? 'nothing' : '['.implode(', ', $allowed).']',
        ));
    }

    /**
     * Completion is gated on the final exam (spec 6.3), so a course cannot be
     * marked complete from anywhere else in the pipeline.
     */
    public static function intoCompletion(Course $course): self
    {
        return new self(sprintf(
            'Course [%d] cannot be completed from [%s]; it must be at [%s].',
            $course->id,
            $course->status->value,
            CourseStatus::FinalExam->value,
        ));
    }
}
