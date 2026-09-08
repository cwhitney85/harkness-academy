<?php

namespace App\Courses\Interviews;

use App\Courses\CourseStatus;
use App\Memory\MemoryTree;
use App\Memory\WorkingKnowledge;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Str;

/**
 * The knowledge interview (spec 4.3).
 *
 * Finds out what the student can already do, and writes it to the subtopic's
 * WORKING_KNOWLEDGE.md -- one document per subtopic, shared by every course in
 * it at every level (ledger D8).
 *
 * That sharing is why `prior_wk_doc` exists. `wk.create` used to write the
 * document blind, which erased what a 101 course recorded the moment a 201
 * course started; the accumulated document now goes in and the interview
 * revises it (ledger D7a). The first course in a subtopic passes an explicit
 * "none yet" rather than an empty string, because a blank in the middle of a
 * prompt reads as a bug to the model as easily as an absence.
 */
final class KnowledgeInterview implements Interview
{
    /**
     * What goes in `prior_wk_doc` when this is the subtopic's first course.
     */
    public const NO_PRIOR_KNOWLEDGE = 'None yet — this is the first course this student has taken in this subtopic.';

    public function __construct(private readonly MemoryTree $memory) {}

    public function kind(): InterviewKind
    {
        return InterviewKind::Knowledge;
    }

    public function status(): CourseStatus
    {
        return CourseStatus::KnowledgeInterview;
    }

    public function next(): CourseStatus
    {
        return CourseStatus::GoalsInterview;
    }

    /**
     * Five (spec 4.3.1). Enough to find the edge of what the student can do
     * unaided, and few enough that they still have the patience for the goals
     * interview waiting behind it.
     */
    public function questionCap(): int
    {
        return 5;
    }

    public function feature(): string
    {
        return 'course.knowledge_interview';
    }

    public function briefKey(): string
    {
        return 'interview.knowledge';
    }

    /**
     * @return array<string, string>
     */
    public function briefVars(Course $course): array
    {
        return [
            'module' => $this->moduleTitle($course),
            'subtopic' => $course->subtopic_title,
            'skill_level' => $course->skill_level->pathSegment(),
        ];
    }

    public function summaryKey(): string
    {
        return 'wk.create';
    }

    /**
     * @return array<string, string>
     */
    public function summaryVars(Course $course, string $transcript): array
    {
        return [
            'interview_transcript' => $transcript,
            'subtopic' => $course->subtopic_title,
            'skill_level' => $course->skill_level->pathSegment(),
            'prior_wk_doc' => $this->priorDocument($course),
        ];
    }

    public function documentPath(Course $course): string
    {
        return WorkingKnowledge::forSubtopic($course->module_slug, $course->subtopic_slug);
    }

    /**
     * Everything earlier courses in this subtopic established, or an explicit
     * absence.
     */
    private function priorDocument(Course $course): string
    {
        $prior = $this->memory->get($course->student, $this->documentPath($course));

        return $prior === null || trim($prior) === ''
            ? self::NO_PRIOR_KNOWLEDGE
            : $prior;
    }

    /**
     * The subject the subtopic sits inside, for the interviewer's framing.
     *
     * Courses keep their own slug rather than pointing at the catalog (D7), so
     * a course can outlive the module row it was created from. The slug is
     * still readable enough to interview against.
     */
    private function moduleTitle(Course $course): string
    {
        $title = Module::query()->where('slug', $course->module_slug)->value('title');

        return is_string($title) ? $title : Str::headline($course->module_slug);
    }
}
