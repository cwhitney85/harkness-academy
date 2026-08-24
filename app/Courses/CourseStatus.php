<?php

namespace App\Courses;

/**
 * Where a student is in the course pipeline (ledger D7).
 *
 * created → knowledge_interview → goals_interview → syllabus_draft
 *   ⇄ syllabus_revision → active → final_exam
 *
 * The pipeline has no end state. Neither ending is a place a student stands:
 * `courses.withdrawn_at` says they stepped away from wherever they were, and
 * `courses.completed_at` says they passed the exam. Both are dates on the row
 * (ledger D7b), which is what keeps status names out of the open-course index.
 *
 * An enum rather than a lookup table for now, because a status needs code --
 * a screen, a button, an arrow into it -- before a row means anything. That
 * is a preference, not a constraint: the index no longer reads this column,
 * so nothing here blocks a `status_types` table later.
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
        };
    }

    public function canPrecede(self $next): bool
    {
        return in_array($next, $this->successors(), strict: true);
    }
}
