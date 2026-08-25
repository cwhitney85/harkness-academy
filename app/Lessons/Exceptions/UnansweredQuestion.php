<?php

namespace App\Lessons\Exceptions;

/**
 * An answer that does not fit the question it claims to answer (spec 5.2.4).
 *
 * A question id this checkpoint does not ask, or an option id this question
 * does not offer. Both are malformed requests rather than wrong answers -- a
 * wrong answer is a legitimate outcome and gets graded.
 */
final class UnansweredQuestion extends LessonException
{
    public static function noSuchQuestion(string $questionId): self
    {
        return new self("This checkpoint does not ask question [{$questionId}].");
    }

    public static function noSuchOption(string $questionId, string $optionId): self
    {
        return new self("Question [{$questionId}] does not offer option [{$optionId}].");
    }
}
