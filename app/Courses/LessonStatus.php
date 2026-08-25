<?php

namespace App\Courses;

/**
 * Whether a lesson can be opened yet (spec 4.5.5).
 *
 * Approval creates every lesson of the course at once, with only the first one
 * open: a syllabus is a plan for the whole course, but a student walks it in
 * order.
 *
 * Deliberately two cases. Package 5 owns what happens once a lesson is opened
 * -- in progress, finished, and whatever resuming needs (spec 5.3) -- and
 * inventing those names here would be package 4 guessing at package 5's
 * lifecycle (ledger D10.5, following D9's precedent).
 */
enum LessonStatus: string
{
    case Locked = 'locked';
    case Available = 'available';

    /**
     * The status every lesson but the first is created with.
     */
    public static function forPosition(int $position): self
    {
        return $position === 1 ? self::Available : self::Locked;
    }
}
