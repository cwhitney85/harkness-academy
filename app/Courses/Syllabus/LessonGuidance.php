<?php

namespace App\Courses\Syllabus;

use App\Models\Course;
use Illuminate\Support\Str;

/**
 * Where one lesson's teaching notes live in the memory tree (spec 4.5.3).
 *
 * `{module}/{subtopic}/{level}/{lesson-slug}/GUIDANCE.md`.
 *
 * **Model-facing only.** Exactly one consumer reads these documents: the
 * lesson engine (5.1). The student's syllabus view renders from the approved
 * syllabus JSON instead, so the two can never drift. GuidanceDoorTest fails
 * the build if a route or component reaches for one.
 *
 * The path carries no attempt number, so a second attempt at a level
 * overwrites the first's notes -- which is why only the highest attempt is
 * resumable (ledger D7b).
 */
final class LessonGuidance
{
    public const FILENAME = 'GUIDANCE.md';

    /**
     * The `{lesson-slug}` segment for one syllabus lesson.
     *
     * Position-prefixed, zero-padded. Two lessons in one course may honestly
     * carry the same title -- "Practice", "Review" -- and a bare title slug
     * would then put their notes at the same path, so the second would
     * silently overwrite the first. The position is what makes the segment
     * unique, and it sorts in teaching order for free.
     *
     * @param  array<string, mixed>  $lesson  one entry of `lessons[]`
     */
    public static function slugFor(array $lesson): string
    {
        $position = str_pad((string) $lesson['position'], 2, '0', STR_PAD_LEFT);
        $title = Str::slug((string) $lesson['title']);

        // A title of only punctuation or of a script Str::slug cannot
        // transliterate leaves nothing behind; the position still names it.
        return $title === '' ? $position : "{$position}-{$title}";
    }

    /**
     * @param  array<string, mixed>  $lesson  one entry of `lessons[]`
     */
    public static function pathFor(Course $course, array $lesson): string
    {
        return $course->materialsPrefix().'/'.self::slugFor($lesson).'/'.self::FILENAME;
    }
}
