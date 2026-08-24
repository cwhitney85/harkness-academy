<?php

namespace App\Courses;

use App\Models\Course;

/**
 * The complete answer to "what is going on with this course?" (ledger D7d).
 *
 * `CourseStatus` is the machine's business: where in the pipeline a course
 * sits, and the only thing the transition service moves. It deliberately has
 * no cases for the two endings, because storing `withdrawn` there would
 * overwrite the position a resumed course needs to return to, and storing
 * `completed` there would give one fact two homes that can disagree.
 *
 * This enum is the other half: the whole vocabulary, endings included, for
 * anything that displays, filters or reasons about a course. Derived on read,
 * never stored, so it cannot drift from the row it describes.
 */
enum CourseStanding: string
{
    case Created = 'created';
    case KnowledgeInterview = 'knowledge_interview';
    case GoalsInterview = 'goals_interview';
    case SyllabusDraft = 'syllabus_draft';
    case SyllabusRevision = 'syllabus_revision';
    case Active = 'active';
    case FinalExam = 'final_exam';

    /**
     * Stepped away, reversibly. Outranks the pipeline position: a student who
     * withdrew during syllabus revision is withdrawn, not revising.
     */
    case Withdrawn = 'withdrawn';

    case Completed = 'completed';

    public static function for(Course $course): self
    {
        if ($course->isCompleted()) {
            return self::Completed;
        }

        if ($course->isWithdrawn()) {
            return self::Withdrawn;
        }

        return self::from($course->status->value);
    }

    /**
     * Whether the course has stopped -- for whichever of the two reasons.
     *
     * The same question the open-course rule asks in SQL, which asks it of the
     * two dates rather than of this value (ledger D7c).
     */
    public function hasEnded(): bool
    {
        return $this === self::Withdrawn || $this === self::Completed;
    }
}
