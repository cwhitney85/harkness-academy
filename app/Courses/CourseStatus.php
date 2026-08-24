<?php

namespace App\Courses;

/**
 * Where a student is in the course pipeline (ledger D7).
 *
 * created → knowledge_interview → goals_interview → syllabus_draft
 *   ⇄ syllabus_revision → active → final_exam
 *
 * ...plus the two ways a course stops. Every state a course can be in is in
 * this one list (ledger D7d).
 *
 * **`withdrawn` and `completed` are never stored in `courses.status`.** They
 * are derived by `Course::standing()` from `withdrawn_at` and `completed_at`,
 * because storing `withdrawn` would overwrite the pipeline position a resumed
 * course returns to, and storing `completed` would give one fact two homes
 * that can disagree (ledger D7b/D7c). Neither is a successor of anything, so
 * the transition service cannot reach them -- `CourseTransitionsTest` pins
 * that shut.
 *
 * An enum rather than a lookup table for now, because a status needs code --
 * a screen, a button, an arrow into it -- before a row means anything. That
 * is a preference, not a constraint: the open-course index no longer reads
 * this column, so nothing here blocks a `status_types` table later.
 */
enum CourseStatus: string
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

    /**
     * Whether the course has stopped, for either reason.
     *
     * The same question the open-course index asks -- of the two dates, not of
     * this value, which is why the rule survives a change to this list.
     */
    public function hasEnded(): bool
    {
        return $this === self::Withdrawn || $this === self::Completed;
    }

    /**
     * Whether `courses.status` may hold this value.
     */
    public function isPipelinePosition(): bool
    {
        return ! $this->hasEnded();
    }

    /**
     * The statuses reachable from here in one step.
     *
     * @return list<self>
     */
    public function successors(): array
    {
        return match ($this) {
            self::Created => [self::KnowledgeInterview],
            self::KnowledgeInterview => [self::GoalsInterview],
            self::GoalsInterview => [self::SyllabusDraft],
            // Approve, or ask for changes (spec 4.5.4).
            self::SyllabusDraft => [self::SyllabusRevision, self::Active],
            // A regenerated syllabus is a draft again, awaiting approval.
            self::SyllabusRevision => [self::SyllabusDraft],
            self::Active => [self::FinalExam],
            // Failing hands the student back their course to keep studying
            // (spec 6.3). Passing is not a move -- it stamps `completed_at`
            // through CourseTransitions::complete().
            self::FinalExam => [self::Active],
            // Not reachable, and nothing leads out of them: these two are
            // derived, never stored.
            self::Withdrawn, self::Completed => [],
        };
    }

    public function canPrecede(self $next): bool
    {
        return in_array($next, $this->successors(), strict: true);
    }
}
