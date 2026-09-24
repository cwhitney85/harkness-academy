<?php

namespace App\Courses;

/**
 * Why a withdrawn course cannot be picked back up (ledger D7b, spec 8.6).
 *
 * The flow map requires a refusal to name the move the student has left. A
 * single message string could not be shown to a student and read by a screen
 * at the same time, which is why this exists: `reason()` is the developer's
 * half, `explanation()` the student's.
 */
enum ResumeRefusal: string
{
    case NotWithdrawn = 'not_withdrawn';

    case SubtopicOccupied = 'subtopic_occupied';

    /**
     * The half that goes in the exception message and the log.
     */
    public function reason(): string
    {
        return match ($this) {
            self::NotWithdrawn => 'it was not withdrawn.',
            self::SubtopicOccupied => 'another course in this subtopic is already open.',
        };
    }

    /**
     * The half a student reads, which has to name the move they have left.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::NotWithdrawn => 'This course has not been set aside, so there is nothing to pick back up.',
            self::SubtopicOccupied => 'Another course in this subject is already open. Finish or set that one aside first.',
        };
    }
}
