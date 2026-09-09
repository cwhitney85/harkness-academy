<?php

namespace App\Courses\Exceptions;

use App\Courses\SubtopicName;
use App\Models\Module;

/**
 * The typed subtopic is not something OpenTutor teaches at all (spec 4.9.4).
 *
 * No module would take it, so there is nowhere to offer and nothing to
 * suggest -- the student is told so and left on the picker.
 */
final class SubtopicOutOfScope extends CourseException
{
    public function __construct(
        public readonly SubtopicName $name,
        public readonly Module $chosen,
    ) {
        parent::__construct(
            "Subtopic [{$name->slug}] is not taught under any module; it was typed under [{$chosen->slug}]."
        );
    }
}
