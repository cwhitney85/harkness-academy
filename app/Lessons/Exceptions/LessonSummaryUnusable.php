<?php

namespace App\Lessons\Exceptions;

use App\Models\Lesson;
use Throwable;

/**
 * The summarizer produced nothing the schema and the lesson's own concept
 * vocabulary both accept (spec 5.4.2).
 *
 * Unlike a checkpoint, this one is allowed to fail: the completion is already
 * irrevocable (5.4.1), so the chain retries and eventually parks (5.4.5)
 * rather than writing a summary that claims mastery of concepts the lesson
 * never taught.
 */
final class LessonSummaryUnusable extends LessonException
{
    public static function for(Lesson $lesson, string $why, ?Throwable $previous = null): self
    {
        return new self("Lesson [{$lesson->id}] produced no usable summary: {$why}", previous: $previous);
    }
}
