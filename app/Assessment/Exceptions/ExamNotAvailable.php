<?php

namespace App\Assessment\Exceptions;

use App\Models\Course;

/**
 * The student cannot sit this exam right now (spec 6.3).
 *
 * Not an authorization failure -- it is their course -- and not a wrong
 * answer. It is the state of the course refusing: lessons still to teach, an
 * exam not opened, or a course that has already ended.
 */
final class ExamNotAvailable extends AssessmentException
{
    public static function because(Course $course, string $why): self
    {
        return new self("Course [{$course->id}] cannot sit its final exam: {$why}");
    }
}
