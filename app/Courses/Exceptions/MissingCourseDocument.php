<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * A pipeline stage needs a document an earlier stage should have written.
 *
 * Always a caller bug rather than a student-facing condition: the state
 * machine only lets a course reach a stage once the stage before it finished,
 * and finishing is what writes the document.
 */
final class MissingCourseDocument extends CourseException
{
    public static function at(Course $course, string $path): self
    {
        return new self(sprintf(
            'Course [%d] stands at [%s] but nothing is written at [%s].',
            $course->id,
            $course->status->value,
            $path,
        ));
    }
}
