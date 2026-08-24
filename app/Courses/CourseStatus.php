<?php

namespace App\Courses;

/**
 * Where a student is in the course pipeline (ledger D7).
 *
 * created → knowledge_interview → goals_interview → syllabus_draft
 *   ⇄ syllabus_revision → active → final_exam → completed
 *
 * This is deliberately an enum and not a lookup table: a partial unique index
 * predicate cannot join to another table, so the "one open course per
 * subtopic" rule could not be enforced by the database if the terminal set
 * lived in rows. A status is also a place in the app's behaviour rather than
 * content -- an unreferenced row would grant no screen and no transition
 * (ledger D7b).
 *
 * Withdrawal is not here. `courses.withdrawn_at` answers whether the student
 * is engaged; this answers where they are (D7b).
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
    case Completed = 'completed';

    /**
     * Whether the pipeline ends here.
     *
     * Only `completed`. Failing the final exam returns the student to `active`
     * rather than ending the course (spec 6.3), and a student who gives up has
     * withdrawn, which is not a status at all (D7b).
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed;
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
            // Passing gates `completed`; failing hands the student back their
            // course to keep studying (spec 6.3).
            self::FinalExam => [self::Completed, self::Active],
            self::Completed => [],
        };
    }

    public function canPrecede(self $next): bool
    {
        return in_array($next, $this->successors(), strict: true);
    }
}
