<?php

namespace App\Courses;

/**
 * Why a withdrawn course cannot be picked back up (ledger D7b, spec 8.6).
 *
 * The three refusals ask the student for three different things -- wait,
 * start a fresh attempt, or go and finish the other course -- so the flow map
 * requires the refusal to say *which*. A single message string could not be
 * shown to a student and read by a screen at the same time, which is why this
 * exists: `reason()` is the developer's half, `explanation()` the student's.
 */
enum ResumeRefusal: string
{
    case NotWithdrawn = 'not_withdrawn';

    /**
     * Lesson guidance paths carry no attempt number (spec 4.5.3), so a later
     * attempt has already overwritten this one's teaching notes.
     */
    case Superseded = 'superseded';

    case SubtopicOccupied = 'subtopic_occupied';

    /**
     * The half that goes in the exception message and the log.
     */
    public function reason(): string
    {
        return match ($this) {
            self::NotWithdrawn => 'it was not withdrawn.',
            self::Superseded => 'a later attempt at this level has replaced it.',
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
            self::Superseded => 'You have since started this course again at the same level, and that newer attempt has the teaching notes. Carry on with it instead.',
            self::SubtopicOccupied => 'Another course in this subject is already open. Finish or set that one aside first.',
        };
    }
}
