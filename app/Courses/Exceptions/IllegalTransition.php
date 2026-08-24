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
            $allowed === [] ? 'nothing -- it is a terminal status' : '['.implode(', ', $allowed).']',
        ));
    }
}
