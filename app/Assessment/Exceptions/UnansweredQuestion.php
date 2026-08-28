<?php

namespace App\Assessment\Exceptions;

/**
 * An answer that does not fit the question it claims to answer (spec 5.2.4).
 *
 * A question id this quiz does not ask, or an option id this question does not
 * offer. Both are malformed requests rather than wrong answers -- a wrong
 * answer is a legitimate outcome and gets graded.
 *
 * Lives in `App\Assessment` rather than `App\Lessons` because all four kinds
 * are graded by the one service (spec 6.1); a checkpoint is not the only quiz
 * a client can send nonsense about.
 */
final class UnansweredQuestion extends AssessmentException
{
    public static function noSuchQuestion(string $questionId): self
    {
        return new self("This quiz does not ask question [{$questionId}].");
    }

    public static function noSuchOption(string $questionId, string $optionId): self
    {
        return new self("Question [{$questionId}] does not offer option [{$optionId}].");
    }
}
