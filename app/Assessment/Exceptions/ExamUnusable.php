<?php

namespace App\Assessment\Exceptions;

use Throwable;

/**
 * The model would not write a usable final exam (spec 6.3).
 *
 * Fails **closed**, unlike a checkpoint. 4.5.1's rule is to fail open only
 * when failing closed would destroy student progress, and nothing is destroyed
 * here: the course stays `active` with every lesson still completed, and the
 * student can ask for their exam again. What failing open would cost is the
 * meaning of the gate -- a course marked complete on an exam that did not
 * cover what it claimed to.
 */
final class ExamUnusable extends AssessmentException
{
    /**
     * @param  list<string>  $reasons
     */
    public static function after(int $attempts, array $reasons, ?Throwable $previous = null): self
    {
        return new self(
            "`exam.final` did not produce a usable exam in {$attempts} attempts: "
                .($reasons === [] ? ($previous?->getMessage() ?? 'no reason recorded.') : implode(' ', $reasons)),
            previous: $previous,
        );
    }
}
