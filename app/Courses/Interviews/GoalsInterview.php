<?php

namespace App\Courses\Interviews;

use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Memory\MemoryTree;
use App\Models\Course;

/**
 * The goals interview (spec 4.4).
 *
 * Runs second, and reads what the first one wrote: the brief carries the
 * WORKING_KNOWLEDGE document so the model does not spend the student's
 * patience re-asking what it already knows.
 *
 * Its own document, COURSE_CRITERIA.md, is level-scoped -- the goals of a 201
 * course are not the goals of the 101 that preceded it.
 */
final class GoalsInterview implements Interview
{
    public function __construct(private readonly MemoryTree $memory) {}

    public function kind(): InterviewKind
    {
        return InterviewKind::Goals;
    }

    public function status(): CourseStatus
    {
        return CourseStatus::GoalsInterview;
    }

    public function next(): CourseStatus
    {
        return CourseStatus::SyllabusDraft;
    }

    /**
     * Four (spec 4.4.1) -- one for each section `criteria.create` writes.
     */
    public function questionCap(): int
    {
        return 4;
    }

    public function feature(): string
    {
        return 'course.goals_interview';
    }

    public function briefKey(): string
    {
        return 'interview.goals';
    }

    /**
     * Pre: the knowledge interview has written the subtopic's WK document.
     * The state machine guarantees it -- 4.3's summarization is the only way
     * a course reaches this status -- so its absence is a bug, not a case to
     * paper over with an empty string.
     *
     * @return array<string, string>
     *
     * @throws MissingCourseDocument
     */
    public function briefVars(Course $course): array
    {
        $path = $course->workingKnowledgePath();
        $wk = $this->memory->get($course->student, $path);

        if ($wk === null || trim($wk) === '') {
            throw MissingCourseDocument::at($course, $path);
        }

        return [
            'subtopic' => $course->subtopic_title,
            'skill_level' => $course->skill_level->pathSegment(),
            'wk_doc' => $wk,
        ];
    }

    public function summaryKey(): string
    {
        return 'criteria.create';
    }

    /**
     * @return array<string, string>
     */
    public function summaryVars(Course $course, string $transcript): array
    {
        return [
            'interview_transcript' => $transcript,
            'subtopic' => $course->subtopic_title,
        ];
    }

    public function documentPath(Course $course): string
    {
        return CourseCriteria::pathFor($course);
    }
}
