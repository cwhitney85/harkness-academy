<?php

namespace App\Courses\Events;

use App\Courses\CourseStatus;
use App\Models\Course;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A student stepped away from a course (ledger D7b).
 *
 * The row and its memory stay; `$at` is the status they left it on, which is
 * exactly where resuming puts them back.
 */
final class CourseWithdrawn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Course $course,
        public readonly CourseStatus $at,
    ) {}
}
