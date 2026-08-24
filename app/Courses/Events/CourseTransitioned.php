<?php

namespace App\Courses\Events;

use App\Courses\CourseStatus;
use App\Models\Course;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A course moved along the pipeline (spec 4.2).
 *
 * The hook the pipeline's workflows hang off: 4.3's knowledge interview,
 * 4.4's goals interview, 4.5's syllabus generation. Listeners branch on `$to`.
 *
 * Dispatched after commit, so a workflow never fires for a status change that
 * rolled back.
 */
final class CourseTransitioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Course $course,
        public readonly CourseStatus $from,
        public readonly CourseStatus $to,
    ) {}
}
