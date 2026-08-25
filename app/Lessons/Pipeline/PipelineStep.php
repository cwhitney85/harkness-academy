<?php

namespace App\Lessons\Pipeline;

/**
 * The steps a completed lesson runs through (spec 5.4.1).
 *
 * Ordered, and the order is not arbitrary. The summary is what the WK update
 * reads; the updated WK is what the next lesson's bootstrap reads (5.1); the
 * homework aims at what the summary called shaky. Unlocking last is 5.4.6's
 * rule -- the next lesson should open onto a WK that already knows about this
 * one.
 *
 * Idempotency is keyed on (lesson, step), so these values are stored in
 * `lesson_pipeline_steps.step` and renaming one is a migration.
 */
enum PipelineStep: string
{
    /** `lesson.summarize` → `lessons.summary_json` + SUMMARY.md (5.4.2, 5.4.3). */
    case Summarize = 'summarize';

    /** `wk.update` → a new version of the subtopic's WORKING_KNOWLEDGE.md (5.4.4). */
    case WorkingKnowledge = 'wk_update';

    /** `homework.generate` → a `quizzes` row of kind `homework` (6.2). */
    case Homework = 'homework';

    /** The next lesson becomes `available` (5.4.6). */
    case Unlock = 'unlock';

    /**
     * Every step, in the order the chain runs them.
     *
     * @return list<self>
     */
    public static function inOrder(): array
    {
        return [self::Summarize, self::WorkingKnowledge, self::Homework, self::Unlock];
    }
}
