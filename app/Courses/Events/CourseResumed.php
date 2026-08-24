<?php

namespace App\Courses\Events;

use App\Courses\CourseStatus;
use App\Models\Course;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A student picked a withdrawn course back up (ledger D7b).
 */
final class CourseResumed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Course $course,
        public readonly CourseStatus $at,
    ) {}
}
