<?php

namespace App\Courses\Events;

use App\Models\Course;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A student passed the final exam (spec 6.3, ledger D7b).
 *
 * The one ending that is not a status: the course keeps `final_exam` as the
 * furthest point it reached, and `completed_at` records that it was cleared.
 */
final class CourseCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Course $course,
    ) {}
}
