<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Who may open a lesson (spec 5.2.6's "session auth").
 *
 * One rule, stated once: a lesson belongs to a course, a course belongs to a
 * student, and no one else has any business in either. Every lesson route
 * authorizes through this rather than repeating the ownership join, so a route
 * added later cannot forget it.
 *
 * Whether the lesson is *openable* -- locked, completed, course withdrawn --
 * is not an authorization question and lives in LessonProgress. Conflating the
 * two would answer "you may not" to a student whose real answer is "not yet".
 *
 * The one exception is the demo (spec 12.4.1): a demo visitor may take lesson 1
 * and nothing after it, on every lesson route, until they sign up. It is here
 * because every lesson route already asks here, so none can forget it. The
 * lesson screen itself sends them to sign-up before asking.
 */
class LessonPolicy
{
    public function view(User $user, Lesson $lesson): bool
    {
        return $lesson->course->user_id === $user->id && ! $lesson->isBeyondDemo();
    }

    public function participate(User $user, Lesson $lesson): bool
    {
        return $this->view($user, $lesson);
    }
}
