<?php

namespace App\Courses\Exceptions;

use App\Courses\ResumeRefusal;
use App\Models\Course;

/**
 * A withdrawn course that cannot be picked back up (ledger D7b).
 *
 * Carries *which* check failed, not only that one did: the flow map (8.6)
 * requires the refusal to say what the student can do about it.
 */
final class CourseNotResumable extends CourseException
{
    private function __construct(string $message, public readonly ResumeRefusal $refusal)
    {
        parent::__construct($message);
    }

    public static function because(Course $course, ResumeRefusal $refusal): self
    {
        return new self("Course [{$course->id}] cannot be resumed: {$refusal->reason()}", $refusal);
    }
}
