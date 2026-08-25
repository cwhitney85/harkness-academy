<?php

namespace App\Lessons\Exceptions;

use App\Models\Lesson;

/**
 * The student cannot teach themselves this lesson right now.
 *
 * A locked lesson, a completed one, or one whose course has ended. Distinct
 * from NoSuchSection: the URL is real, the lesson is not available.
 */
final class LessonNotOpenable extends LessonException
{
    public static function because(Lesson $lesson, string $why): self
    {
        return new self("Lesson [{$lesson->id}] cannot be opened: {$why}");
    }
}
