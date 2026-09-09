<?php

namespace App\Courses\Exceptions;

use App\Courses\SubtopicName;
use App\Models\Module;

/**
 * The typed subtopic is taught here, but under another module (spec 4.9.4).
 *
 * A redirect rather than a dead end: the same name under `$suggested` is the
 * same slug and the same working-knowledge document (ledger D42), so the
 * student is one button away from the course they meant to start.
 *
 * Carries both modules because the copy the student reads is generated from
 * them -- never from the model's own sentence (4.9.6).
 */
final class SubtopicBelongsElsewhere extends CourseException
{
    public function __construct(
        public readonly SubtopicName $name,
        public readonly Module $chosen,
        public readonly Module $suggested,
    ) {
        parent::__construct(
            "Subtopic [{$name->slug}] does not belong under [{$chosen->slug}]; it belongs under [{$suggested->slug}]."
        );
    }
}
