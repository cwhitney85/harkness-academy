<?php

namespace App\Assessment\Exceptions;

use Throwable;

/**
 * A generated quiz that cannot be served or graded (spec 6.1).
 *
 * What `QuizDocument::defects()` found, raised. The checkpoint path does not
 * use this -- 5.2.1 fails open and raises its own CheckpointUnusable -- which
 * is exactly why the document reports defects rather than throwing them: the
 * value object states what is wrong, and each caller decides how loudly to
 * fail (spec 4.5.1's rule is per-caller, not per-document).
 */
final class QuizUnusable extends AssessmentException
{
    /**
     * @param  list<string>  $defects
     */
    public static function because(string $key, array $defects): self
    {
        return new self("The model's [{$key}] output cannot be graded: ".implode(' ', $defects));
    }

    public static function after(string $key, int $attempts, ?Throwable $previous = null): self
    {
        return new self(
            "[{$key}] did not produce a usable quiz in {$attempts} attempts"
                .($previous === null ? '.' : ": {$previous->getMessage()}"),
            previous: $previous,
        );
    }
}
