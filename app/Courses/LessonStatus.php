<?php

namespace App\Courses;

/**
 * Whether a lesson can be opened yet (spec 4.5.5).
 *
 * Approval creates every lesson of the course at once, with only the first one
 * open: a syllabus is a plan for the whole course, but a student walks it in
 * order.
 *
 * Approval creates only the first two. `InProgress` and `Completed` were left
 * for package 5 to name once it knew what opening and finishing a lesson
 * actually involved (ledger D10.5, following D9's precedent); the package 5
 * session added them, and `lessons.section_index`, `started_at` and
 * `completed_at` are the state they stand for.
 */
enum LessonStatus: string
{
    case Locked = 'locked';
    case Available = 'available';

    /**
     * Opened at least once and not yet finished (spec 5.3).
     *
     * What resume looks for: an in-progress lesson has a `section_index` to
     * return the student to and a transcript to restore.
     */
    case InProgress = 'in_progress';

    /**
     * Finished, irrevocably (spec 5.4.1).
     *
     * Set synchronously when the student completes the last section, before
     * the summarization chain is dispatched -- so nothing the chain does or
     * fails to do can take the completion back (5.4.5).
     */
    case Completed = 'completed';

    /**
     * The status every lesson but the first is created with.
     */
    public static function forPosition(int $position): self
    {
        return $position === 1 ? self::Available : self::Locked;
    }

    /**
     * Whether the student may stream sections of a lesson in this status.
     */
    public function isOpenable(): bool
    {
        return $this === self::Available || $this === self::InProgress;
    }
}
