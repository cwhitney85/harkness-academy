<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * A withdrawn course that cannot be picked back up (ledger D7b).
 */
final class CourseNotResumable extends CourseException
{
    public static function because(Course $course, string $reason): self
    {
        return new self("Course [{$course->id}] cannot be resumed: {$reason}");
    }
}
