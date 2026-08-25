<?php

namespace App\Courses;

use App\Models\Course;

/**
 * Where a course's criteria document lives in the memory tree (spec 4.4).
 *
 * Level-scoped, unlike WORKING_KNOWLEDGE.md: what a student wants out of a 201
 * course is not what they wanted out of the 101 (ledger D8 draws the same line
 * -- WK is student state, this is course material).
 *
 * Read by the lesson session's system prompt as `criteria_doc` (spec 2.3.3).
 */
final class CourseCriteria
{
    public const FILENAME = 'COURSE_CRITERIA.md';

    public static function pathFor(Course $course): string
    {
        return $course->materialsPrefix().'/'.self::FILENAME;
    }
}
