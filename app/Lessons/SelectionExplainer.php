<?php

namespace App\Lessons;

use App\Ai\Gateway;
use App\Lessons\Exceptions\SelectionNotInLesson;
use App\Models\Lesson;
use App\Models\LessonMessage;

/**
 * The select-mode side-tab (spec 5.5).
 *
 * One stateless `explainer.select` call, and the emphasis is on *stateless*:
 * this class holds no reference to LessonTranscript and there is no code path
 * from here to a write. 5.5's done-condition is that the transcript is
 * byte-identical before and after the side-tab is used, and the way to make
 * that a property rather than a promise is for the explainer to have nothing
 * to write with.
 *
 * That is also why the side-tab is not a Q&A turn. A question asked in the
 * lesson joins the conversation and the next section is taught by a model that
 * remembers it (ledger D5, D14). A phrase highlighted here is a glance
 * sideways: the tutor never learns it happened, and the summarizer never sees
 * it. The two live in the same UI and are different features.
 *
 * Pre: the student may participate in `$lesson`, and `$source` is a tutor turn
 *      of that lesson's transcript.
 * Post: markdown suitable for a side panel, one `usage_events` row under
 *       `explainer.select`, and zero rows written or changed anywhere else.
 */
final class SelectionExplainer
{
    /**
     * The longest highlight the endpoint will explain.
     *
     * The selection is the only text in the call the client authors, so it is
     * also the only place a caller could spend our tokens on something that is
     * not lesson text. A highlight is a phrase or a sentence; nothing about
     * the feature wants a page.
     */
    public const MAX_SELECTION = 500;

    /**
     * How much of the passage travels on either side of the highlight.
     *
     * Enough to disambiguate a pronoun or a "this", not the whole section.
     * `explainer.select` runs on the cheap tier, whose caching floor our
     * prompts never reach (docs/anthropic-specific.md), so every character
     * here is paid for in full on every call.
     */
    private const CHUNK_RADIUS = 600;

    /**
     * The cap on the fallback chunk, when the highlight is not found verbatim.
     */
    private const MAX_CHUNK = 4000;

    public function __construct(private readonly Gateway $gateway) {}

    /**
     * Explain a highlighted phrase, without disturbing the lesson.
     *
     * @throws SelectionNotInLesson
     */
    public function explain(Lesson $lesson, LessonMessage $source, string $selection): string
    {
        if ($source->lesson_id !== $lesson->id || $source->speaker !== Speaker::Tutor) {
            throw SelectionNotInLesson::message($source->id);
        }

        $course = $lesson->course;

        return trim($this->gateway
            ->prompt('explainer.select', [
                'selection' => $selection,
                'surrounding_chunk' => self::chunkAround($source->content, $selection),
                'subtopic' => $course->subtopic_title,
                'skill_level' => $course->skill_level->pathSegment(),
            ])
            ->feature('lesson.explainer')
            ->for($course->user_id)
            ->generate()
            ->text);
    }

    /**
     * The passage the highlight came from, narrowed to what surrounds it.
     *
     * A highlight is made against *rendered* markdown, so one that spans a
     * `**` or a link will not appear verbatim in the source we stored. Rather
     * than normalising both sides and mapping offsets back -- fragile, and
     * wrong in a way nothing would notice -- a miss widens to the whole
     * passage. The model then certainly has the context; the only cost of a
     * miss is tokens.
     */
    private static function chunkAround(string $passage, string $selection): string
    {
        $at = mb_stripos($passage, $selection);

        if ($at === false) {
            return self::clip($passage, 0, self::MAX_CHUNK);
        }

        $start = max(0, $at - self::CHUNK_RADIUS);
        $length = mb_strlen($selection) + ($at - $start) + self::CHUNK_RADIUS;

        return self::clip($passage, $start, $length);
    }

    /**
     * A window of the passage, marked with ellipses where it was cut.
     */
    private static function clip(string $passage, int $start, int $length): string
    {
        $window = mb_substr($passage, $start, $length);

        if ($start > 0) {
            $window = '…'.$window;
        }

        if ($start + $length < mb_strlen($passage)) {
            $window .= '…';
        }

        return $window;
    }
}
