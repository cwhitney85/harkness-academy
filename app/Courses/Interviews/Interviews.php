<?php

namespace App\Courses\Interviews;

use App\Ai\Gateway;
use App\Courses\CourseTransitions;
use App\Memory\MemoryTree;
use App\Models\Course;

/**
 * Opens interview sessions (spec 4.3, 4.4).
 *
 * A session is bound to one course, so it cannot be a shared service; this is
 * the container-resolvable thing that makes one.
 */
final class Interviews
{
    public function __construct(
        private readonly Gateway $gateway,
        private readonly MemoryTree $memory,
        private readonly CourseTransitions $transitions,
        private readonly KnowledgeInterview $knowledge,
        private readonly GoalsInterview $goals,
    ) {}

    /**
     * What the student already knows (spec 4.3).
     */
    public function knowledge(Course $course): InterviewSession
    {
        return $this->session($course, $this->knowledge);
    }

    /**
     * What the student wants (spec 4.4).
     */
    public function goals(Course $course): InterviewSession
    {
        return $this->session($course, $this->goals);
    }

    private function session(Course $course, Interview $interview): InterviewSession
    {
        return new InterviewSession(
            $this->gateway,
            $this->memory,
            $this->transitions,
            $course,
            $interview,
        );
    }
}
