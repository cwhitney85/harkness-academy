<?php

namespace App\Courses\Exceptions;

use App\Models\User;

/**
 * A student already has an open course in this subtopic (ledger D7a/D7b).
 *
 * The caller should have offered to resume or restart it rather than
 * creating a second one -- see `CourseTransitions::resumable()`.
 */
final class SubtopicAlreadyOpen extends CourseException
{
    public static function for(User $student, string $moduleSlug, string $subtopicSlug): self
    {
        return new self(
            "User [{$student->id}] already has an open course in [{$moduleSlug}/{$subtopicSlug}]."
        );
    }
}
