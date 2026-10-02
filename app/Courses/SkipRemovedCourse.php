<?php

namespace App\Courses;

use App\Courses\Exceptions\CourseRemoved;
use Closure;

/**
 * Job middleware: a course removed while its job's model call was out ends
 * the job quietly, as one removed before it started does (spec 16.2.8). The
 * removal already closed the student's wait; there is nothing to retry.
 */
final class SkipRemovedCourse
{
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (CourseRemoved) {
            //
        }
    }
}
