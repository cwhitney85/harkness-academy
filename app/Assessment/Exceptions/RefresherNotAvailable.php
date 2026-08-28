<?php

namespace App\Assessment\Exceptions;

/**
 * There is nothing recorded to refresh (spec 6.4).
 *
 * A lesson with no summary, a subtopic with no working-knowledge document, a
 * module the student has never studied. Not a failure -- the student has
 * simply not done the thing they are asking to be reminded of, and the
 * refresher prompt's hard rule is that it may only ask about what the
 * documents record.
 */
final class RefresherNotAvailable extends AssessmentException
{
    public static function because(string $label, string $why): self
    {
        return new self("There is no refresher for {$label}: {$why}");
    }
}
