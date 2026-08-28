<?php

namespace App\Lessons\Exceptions;

/**
 * A side-tab highlight that is not part of this lesson's tutor text (5.5).
 *
 * One invariant, stated three ways: **a highlight is a part of a passage the
 * tutor wrote in this lesson.** The explainer's context is server state, never
 * the client's -- the request names a transcript row and the server reads the
 * passage out of it -- and these are the three ways a request can claim
 * otherwise.
 *
 * Every one of them is a 422 at the transport. None is a bug in the engine.
 */
final class UnusableSelection extends LessonException
{
    public static function fromAnotherLesson(int $messageId): self
    {
        return new self("Message [{$messageId}] is not part of this lesson.");
    }

    public static function fromTheStudentsOwnWords(int $messageId): self
    {
        return new self("Message [{$messageId}] is the student's own turn, not tutor text.");
    }

    /**
     * The bound that lets a whole section be highlighted while still keeping
     * the client from authoring the text we pay to read: a highlight is a part
     * of its passage, so it cannot be bigger than one.
     */
    public static function longerThanItsPassage(int $messageId): self
    {
        return new self("The highlight is longer than message [{$messageId}], the passage it claims to come from.");
    }
}
