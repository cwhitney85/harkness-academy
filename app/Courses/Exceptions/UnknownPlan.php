<?php

namespace App\Courses\Exceptions;

use App\Courses\SkillLevel;

/**
 * A plan has no depth configured at a skill level (spec 4.5.6.1).
 *
 * The only place an unknown plan can surface, because depth resolution is the
 * only thing in v1 that reads `users.plan` -- so this is where the value is
 * checked rather than at every site that passes it along (ledger D11).
 *
 * A caller bug or bad data, never a student-facing condition: the column
 * defaults to a configured plan and nothing in v1 writes it.
 */
final class UnknownPlan extends CourseException
{
    public static function at(SkillLevel $level, string $plan): self
    {
        return new self(sprintf(
            'No depth configured for plan [%s] at skill level [%d]; known plans are [%s].',
            $plan,
            $level->value,
            implode(', ', (array) config('courses.plans', [])),
        ));
    }
}
