<?php

namespace App\Courses\Interviews;

use App\Courses\CourseStatus;
use App\Models\Course;

/**
 * One of the two interviews a course runs before it has a syllabus (4.3, 4.4).
 *
 * Both are the same machine -- stream a conversation, end it per 2.3.4,
 * summarize it into one document, move the course on -- so InterviewSession
 * holds all of that and the two implementations hold only what differs: which
 * prompts, which document, which status.
 *
 * Contract for an implementation:
 *   - `status()` is where the course stands while this interview runs, and
 *     `next()` is a declared successor of it (CourseStatus::successors).
 *   - `documentPath()` is a valid memory path (spec 3.2).
 *   - `briefVars()` and `summaryVars()` supply exactly the vars their prompts
 *     declare in the 2.3.2 inventory; anything else is a render failure.
 */
interface Interview
{
    public function kind(): InterviewKind;

    /**
     * Where the course stands while this interview is running.
     */
    public function status(): CourseStatus;

    /**
     * Where the course moves once this interview's document is written.
     */
    public function next(): CourseStatus;

    /**
     * The `usage_events` label for both of this interview's calls (spec 2.2).
     */
    public function feature(): string;

    /**
     * The 2.3.2 prompt key that runs the conversation.
     */
    public function briefKey(): string;

    /**
     * @return array<string, string>
     */
    public function briefVars(Course $course): array;

    /**
     * The 2.3.2 prompt key that turns the transcript into the document.
     */
    public function summaryKey(): string;

    /**
     * @return array<string, string>
     */
    public function summaryVars(Course $course, string $transcript): array;

    /**
     * Where the summary is written in the memory tree.
     */
    public function documentPath(Course $course): string;
}
