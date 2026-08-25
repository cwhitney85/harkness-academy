<?php

namespace App\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\LessonStatus;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Exceptions\NoSuchSection;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a lesson's position and status (spec 5.3, 5.4.1).
 *
 * The lesson counterpart of CourseTransitions, and for the same reason:
 * `status`, `section_index` and `completed_at` answer questions the whole
 * engine asks, and a second writer is how they start disagreeing.
 *
 * Invariant: `section_index` never goes backwards. It records the furthest
 * section reached, not the one currently on screen -- a student re-reading
 * section 2 of 4 has not un-learned section 3, and 5.3 restores where they
 * got to rather than where they last clicked.
 */
final class LessonProgress
{
    /**
     * Whether the student may open this section right now.
     *
     * Pre-check for every streamed turn, and the reason the transport can
     * treat a violation as a closed door rather than a retryable error.
     *
     * Sections open one at a time: the furthest reached, plus the next one.
     * Anything beyond that is a student jumping to section 4 without being
     * taught sections 2 and 3, which would then be missing from the transcript
     * the summarizer reads.
     *
     * @throws CourseNotOpen|LessonNotOpenable|NoSuchSection
     */
    public function assertSectionOpenable(Lesson $lesson, LessonSections $sections, int $index): void
    {
        if (! $lesson->course->isOpen()) {
            throw CourseNotOpen::for($lesson->course);
        }

        if (! $lesson->status->isOpenable()) {
            throw LessonNotOpenable::because($lesson, "it stands at [{$lesson->status->value}].");
        }

        if (! $sections->has($index)) {
            throw NoSuchSection::at($index, count($sections));
        }

        $reached = $lesson->section_index ?? 0;

        if ($index > $reached + 1) {
            throw LessonNotOpenable::because(
                $lesson,
                "section [{$index}] is ahead of section [{$reached}], the furthest reached.",
            );
        }
    }

    /**
     * Record that teaching of a section has begun.
     *
     * Post: the lesson is `in_progress`, `started_at` is stamped once and
     *       never moved, and `section_index` is the furthest section reached.
     */
    public function enterSection(Lesson $lesson, int $index): void
    {
        $lesson->forceFill([
            'status' => LessonStatus::InProgress,
            'section_index' => max($index, $lesson->section_index ?? 0),
            'started_at' => $lesson->started_at ?? now(),
        ])->save();
    }

    /**
     * The student has finished the lesson (spec 5.4.1, sync half).
     *
     * Pre: the course is open, the lesson is openable, and its last section
     *      has actually been taught. Completing a lesson the student never
     *      reached the end of would hand the summarizer half a transcript and
     *      call it a finished lesson.
     * Post: `status` is `completed` and `completed_at` is stamped. **This is
     *       irrevocable and happens before the chain is dispatched** -- 5.4.5
     *       requires that nothing the pipeline does or fails to do can take
     *       the completion back.
     *
     * Idempotent: completing twice returns the first completion untouched,
     * so a double-tapped button cannot restamp the time or re-dispatch work.
     *
     * @throws CourseNotOpen|LessonNotOpenable
     */
    public function complete(Lesson $lesson, LessonSections $sections, LessonTranscript $transcript): Lesson
    {
        if ($lesson->isCompleted()) {
            return $lesson;
        }

        if (! $lesson->course->isOpen()) {
            throw CourseNotOpen::for($lesson->course);
        }

        if (! $lesson->status->isOpenable()) {
            throw LessonNotOpenable::because($lesson, "it stands at [{$lesson->status->value}].");
        }

        $last = count($sections);

        if ($transcript->section($last) === null) {
            throw LessonNotOpenable::because($lesson, "its last section [{$last}] has not been taught.");
        }

        DB::transaction(function () use ($lesson, $last): void {
            $locked = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return;
            }

            $locked->forceFill([
                'status' => LessonStatus::Completed,
                'section_index' => $last,
                'completed_at' => now(),
            ])->save();
        });

        return $lesson->refresh();
    }

    /**
     * Open the next lesson of the course (spec 5.4.1, 5.4.6).
     *
     * Called at the end of the completion chain, so the next lesson's
     * bootstrap reads a WORKING_KNOWLEDGE document that already includes this
     * lesson. 5.4.6's escape hatch calls it too, from the other direction:
     * a chain parked long enough unlocks anyway, because a stale WK beats a
     * stuck course.
     *
     * Returns the lesson it unlocked, or null when this was the last one or
     * the next is already open.
     */
    public function unlockNext(Lesson $lesson): ?Lesson
    {
        $next = $lesson->next();

        if ($next === null || $next->status !== LessonStatus::Locked) {
            return null;
        }

        $next->forceFill(['status' => LessonStatus::Available])->save();

        return $next;
    }
}
