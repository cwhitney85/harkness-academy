<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Who may act on a course (spec 6.3, 6.4).
 *
 * The course-level twin of LessonPolicy, added here because package 6 is the
 * first to route anything at a course: package 4 shipped the engine with no
 * routes, so no policy existed to authorize one.
 *
 * One rule, stated once: a course belongs to a student, and no one else has
 * any business in it. Whether the course is *ready* -- lessons outstanding,
 * exam not opened, already ended -- is not an authorization question and lives
 * in FinalExam. Conflating the two would answer "you may not" to a student
 * whose real answer is "not yet".
 */
class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        return $course->user_id === $user->id;
    }

    public function participate(User $user, Course $course): bool
    {
        return $this->view($user, $course);
    }
}
