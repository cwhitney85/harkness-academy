<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

/**
 * Who may read and answer a quiz by its own id (spec 6.4).
 *
 * Checkpoints, homework and the final exam are all reached through the thing
 * they hang off, and are authorized by that thing's policy. A refresher has no
 * such route -- a student takes one and comes back to it, so it needs a URL of
 * its own -- and `quizzes.user_id` is the fact that answers this. Denormalised
 * from the course precisely so this question needs no join (5.4.1's schema).
 */
class QuizPolicy
{
    public function view(User $user, Quiz $quiz): bool
    {
        return $quiz->user_id === $user->id;
    }

    public function participate(User $user, Quiz $quiz): bool
    {
        return $this->view($user, $quiz);
    }
}
