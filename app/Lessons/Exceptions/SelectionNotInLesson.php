<?php

namespace App\Lessons\Exceptions;

/**
 * A side-tab selection whose passage is not this lesson's tutor text (5.5).
 *
 * The explainer's context is server state, never the client's: the request
 * names a transcript row and the server reads the passage out of it. A row
 * belonging to another lesson -- or to the student's own half of this one --
 * is the one way that boundary can be pushed on, so it is named rather than
 * left to a generic 422.
 */
final class SelectionNotInLesson extends LessonException
{
    public static function message(int $messageId): self
    {
        return new self("Message [{$messageId}] is not tutor text in this lesson.");
    }
}
