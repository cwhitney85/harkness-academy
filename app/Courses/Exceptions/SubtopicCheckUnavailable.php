<?php

namespace App\Courses\Exceptions;

use App\Courses\SubtopicName;

/**
 * The scope check could not be run, so no course starts (spec 4.9.5).
 *
 * Failing closed, which is 4.5.1's rule as written rather than an exception to
 * it: nothing downstream exists to protect, so the cost of stopping is one
 * sentence. Degraded mode is explicit -- the catalog path calls no model and
 * keeps working through a provider outage.
 */
final class SubtopicCheckUnavailable extends CourseException
{
    public function __construct(
        public readonly SubtopicName $name,
        public readonly int $attempts,
    ) {
        parent::__construct(
            "The scope check for subtopic [{$name->slug}] did not answer in {$attempts} attempts."
        );
    }
}
