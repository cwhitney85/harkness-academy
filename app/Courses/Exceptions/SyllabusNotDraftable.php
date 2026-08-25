<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * A course cannot be given a new syllabus draft right now (spec 4.5.4).
 *
 * A caller bug rather than a student-facing condition in every case: the
 * revision cap, an approved syllabus and a missing draft are all things the
 * screen offering the button should already know.
 */
final class SyllabusNotDraftable extends CourseException
{
    public static function because(Course $course, string $reason): self
    {
        return new self("Course [{$course->id}] cannot take a new syllabus draft: {$reason}");
    }
}
