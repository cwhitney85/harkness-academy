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
 *   - `briefVars()` and `summaryVars()` supply the vars their prompts declare
 *     in the 2.3.2 inventory, except `pacing`, which InterviewSession fills
 *     because only it knows how far through the cap the conversation is;
 *     anything else, or anything missing, is a render failure.
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
     * How many questions this interview is worth before the tutor is told to
     * wrap up (spec 4.3.1, 4.4.1).
     *
     * A budget, not a cap: spending it changes what the brief asks for on
     * every turn from then on, and nothing forces an ending. Only R3's two
     * doors end an interview. The number is never stated to the model, which
     * is told "ask your next question" or "wrap up now" and never how many are
     * left. It lives here because it is pedagogy -- how much of the student's
     * patience this particular conversation is worth -- and pedagogy is what
     * an Interview implementation owns.
     */
    public function questionBudget(): int;

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
