<?php

namespace App\Assessment\Exceptions;

use Throwable;

/**
 * The model would not write a refresher that stays inside its scope (6.4).
 *
 * Fails closed, but cheaply: a refresher gates nothing, so the student is told
 * to try again and has lost nothing. The alternative -- serving a refresher
 * that asks about something the student was never taught -- is the exact
 * failure the prompt calls "the system having lost track of them", and it
 * costs more than a missing quiz does.
 */
final class RefresherUnusable extends AssessmentException
{
    /**
     * @param  list<string>  $reasons
     */
    public static function after(int $attempts, array $reasons, ?Throwable $previous = null): self
    {
        return new self(
            "`quiz.refresher` did not produce a usable quiz in {$attempts} attempts: "
                .($reasons === [] ? ($previous?->getMessage() ?? 'no reason recorded.') : implode(' ', $reasons)),
            previous: $previous,
        );
    }
}
