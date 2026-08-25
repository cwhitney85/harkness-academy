<?php

namespace App\Courses\Exceptions;

use App\Models\Course;

/**
 * The model returned nothing worth writing down.
 *
 * Fail-closed, per spec 4.5.1's global rule: failing closed here destroys no
 * student progress -- the transcript is still there and the interview can be
 * ended again -- while failing open would overwrite the subtopic's
 * accumulated knowledge with an empty document.
 */
final class InterviewSummaryUnusable extends CourseException
{
    public static function for(Course $course, string $promptKey): self
    {
        return new self(sprintf(
            'Prompt [%s] returned an empty document for course [%d]; the transcript is kept.',
            $promptKey,
            $course->id,
        ));
    }
}
