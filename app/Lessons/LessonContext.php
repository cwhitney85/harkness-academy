<?php

namespace App\Lessons;

use App\Ai\Gateway;
use App\Ai\LessonSystemPrompt;
use App\Ai\Tier;
use App\Courses\CourseCriteria;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Memory\MemoryTree;
use App\Models\Lesson;

/**
 * The cached prefix of a lesson session (spec 5.1).
 *
 * `[persona | CRITERIA | WK | GUIDANCE]`, most stable first, because the
 * assembly order *is* the cache order (ledger D5's rider, spec 2.3.3). The
 * order lives in LessonSystemPrompt::ORDER and this class does not restate it
 * -- two lists that could disagree is one list too many.
 *
 * Why the order pays: persona is identical for every student, CRITERIA is
 * fixed for the whole course, WK changes once per lesson, GUIDANCE is
 * per-lesson. Putting the volatile document first would invalidate everything
 * behind it on every lesson, so the second lesson of a course would re-write
 * the entire prefix instead of reading it from cache (spec 2.4).
 *
 * All three documents are read through the path API and none may be missing:
 * the state machine cannot produce a lesson row without an approved syllabus,
 * and approval is what materialises GUIDANCE.md (4.5.5). An absence is a bug.
 */
final class LessonContext
{
    public function __construct(
        private readonly Gateway $gateway,
        private readonly LessonSystemPrompt $system,
        private readonly MemoryTree $memory,
    ) {}

    /**
     * The system prompt this lesson's session runs under.
     *
     * Pre: the lesson's course has an approved syllabus and both interview
     *      documents are written.
     * Post: the returned text is `[persona | CRITERIA | WK | GUIDANCE]` in
     *       that order, and is what PromptCaching puts the `system` cache
     *       breakpoint on.
     *
     * @throws MissingCourseDocument
     */
    public function systemPromptFor(Lesson $lesson): string
    {
        $course = $lesson->course;
        $student = $course->student;

        return $this->system->render(
            persona: [
                'student_name' => $student->name,
                'subtopic' => $course->subtopic_title,
                'skill_level' => $course->skill_level->pathSegment(),
            ],
            criteriaDoc: $this->read($lesson, CourseCriteria::pathFor($course)),
            wkDoc: $this->read($lesson, $course->workingKnowledgePath()),
            guidanceDoc: $this->read($lesson, $lesson->guidance_path),
        );
    }

    /**
     * The tier the whole session runs at.
     *
     * Taken from `lesson.system`'s frontmatter rather than named here: the
     * session's tier is a property of the session's prompt, and `lesson.section`
     * declares `tier: session` precisely so it inherits this rather than
     * choosing for itself (spec 2.1.1, 2.3.2).
     */
    public function tier(): Tier
    {
        return $this->gateway->definition(LessonSystemPrompt::KEY)->tier;
    }

    /**
     * The catalog id of the model this lesson's session runs on (spec 14.1.6).
     *
     * The session tier's model when the lesson first calls one, stamped on the
     * lesson and kept from then on: Anthropic's cache is keyed per model, so a
     * lesson moved to another model mid-way would pay to rewrite its whole
     * prefix, and its transcript would be two tutors'.
     */
    public function modelFor(Lesson $lesson): int
    {
        if ($lesson->ai_model_id === null) {
            $lesson->forceFill(['ai_model_id' => $this->tier()->primary()['id']])->save();
        }

        return $lesson->ai_model_id;
    }

    /**
     * @throws MissingCourseDocument
     */
    private function read(Lesson $lesson, string $path): string
    {
        $document = $this->memory->get($lesson->course->student, $path);

        if ($document === null || trim($document) === '') {
            throw MissingCourseDocument::at($lesson->course, $path);
        }

        return $document;
    }
}
