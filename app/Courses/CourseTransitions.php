<?php

namespace App\Courses;

use App\Courses\Events\CourseCompleted;
use App\Courses\Events\CourseResumed;
use App\Courses\Events\CourseTransitioned;
use App\Courses\Events\CourseWithdrawn;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\CourseNotResumable;
use App\Courses\Exceptions\IllegalTransition;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of `courses.status` and `courses.withdrawn_at` (spec 4.2).
 *
 * Nothing else may set either column. Every method re-reads its row under a
 * lock before deciding, so two requests arriving together cannot both see the
 * old status and both act on it.
 *
 * Invariant: `status` only ever moves along an arrow declared in CourseStatus,
 * and every move that commits dispatches exactly one event.
 */
final class CourseTransitions
{
    /**
     * Move a course to its next status.
     *
     * Pre: the course is open, and `$next` is a successor of its current
     *      status. Either violation is a caller bug.
     * Post: `status` is `$next` and one CourseTransitioned is dispatched.
     *
     * @throws CourseNotOpen|IllegalTransition
     */
    public function to(Course $course, CourseStatus $next): Course
    {
        DB::transaction(function () use ($course, $next): void {
            $locked = $this->lock($course);

            if (! $locked->isOpen()) {
                throw CourseNotOpen::for($locked);
            }

            $from = $locked->status;

            if (! $from->canPrecede($next)) {
                throw IllegalTransition::between($locked, $from, $next);
            }

            $locked->forceFill(['status' => $next])->save();

            CourseTransitioned::dispatch($locked, $from, $next);
        });

        return $course->refresh();
    }

    /**
     * Record that the student passed the final exam (spec 6.3).
     *
     * Not a transition: `status` stays on `final_exam`, the furthest point the
     * course reached, and the ending is the date. That is what keeps status
     * names out of the open-course index (ledger D7b).
     *
     * Pre: the course is open and standing at `final_exam`.
     * Post: `completed_at` is stamped, the subtopic's open slot is free, and
     *       one CourseCompleted is dispatched.
     *
     * @throws CourseNotOpen|IllegalTransition
     */
    public function complete(Course $course): Course
    {
        DB::transaction(function () use ($course): void {
            $locked = $this->lock($course);

            if (! $locked->isOpen()) {
                throw CourseNotOpen::for($locked);
            }

            if ($locked->status !== CourseStatus::FinalExam) {
                throw IllegalTransition::intoCompletion($locked);
            }

            $locked->forceFill(['completed_at' => now()])->save();

            CourseCompleted::dispatch($locked);
        });

        return $course->refresh();
    }

    /**
     * Step away from a course, keeping the row and its progress (ledger D7b).
     *
     * Pre: the course is open.
     * Post: `withdrawn_at` is stamped, `status` is untouched -- that is what
     *       lets `resume` put the student back where they were.
     *
     * @throws CourseNotOpen
     */
    public function withdraw(Course $course): Course
    {
        DB::transaction(function () use ($course): void {
            $locked = $this->lock($course);

            if (! $locked->isOpen()) {
                throw CourseNotOpen::for($locked);
            }

            $locked->forceFill(['withdrawn_at' => now()])->save();

            CourseWithdrawn::dispatch($locked, $locked->status);
        });

        return $course->refresh();
    }

    /**
     * Pick a withdrawn course back up where it was left.
     *
     * Pre: the course is withdrawn and nothing else is open in its subtopic.
     *      Any attempt qualifies, not only the newest: each keeps its own
     *      documents in its own folder (ledger D64).
     * Post: `withdrawn_at` is cleared, `status` unchanged.
     *
     * @throws CourseNotResumable
     */
    public function resume(Course $course): Course
    {
        DB::transaction(function () use ($course): void {
            $locked = $this->lock($course);

            $this->assertResumable($locked);

            $locked->forceFill(['withdrawn_at' => null])->save();

            CourseResumed::dispatch($locked, $locked->status);
        });

        return $course->refresh();
    }

    /**
     * The attempts at this level a student could pick back up, newest first.
     *
     * What the "carry on, or start over?" prompt offers before creating a new
     * course (spec 16.4.2). Empty means free to start fresh.
     *
     * @return Collection<int, Course>
     */
    public function resumable(User $student, string $moduleSlug, string $subtopicSlug, SkillLevel $level): Collection
    {
        return Course::query()
            ->forLevel($student, $moduleSlug, $subtopicSlug, $level)
            ->orderByDesc('attempt')
            ->get()
            ->filter(fn (Course $course): bool => $this->refusal($course) === null)
            ->values();
    }

    /**
     * Why this course cannot be picked back up, or null if it can.
     *
     * The same checks `resume` makes, asked without making the move -- what
     * the set-aside list needs to say "your other course is open" instead of
     * offering a button that 422s (8.10.3). Public so that the one
     * statement of the rule serves both the question and the act.
     *
     * Post: null iff `resume` would commit.
     */
    public function refusal(Course $course): ?ResumeRefusal
    {
        try {
            $this->assertResumable($course);
        } catch (CourseNotResumable $e) {
            return $e->refusal;
        }

        return null;
    }

    /**
     * @throws CourseNotResumable
     */
    private function assertResumable(Course $course): void
    {
        if (! $course->isWithdrawn()) {
            throw CourseNotResumable::because($course, ResumeRefusal::NotWithdrawn);
        }

        // Backstopped by courses_one_open_per_subtopic -- checked here only to
        // fail with a diagnosis rather than a constraint violation.
        $occupied = Course::query()
            ->forSubtopic($course->student, $course->module_slug, $course->subtopic_slug)
            ->whereKeyNot($course->id)
            ->open()
            ->exists();

        if ($occupied) {
            throw CourseNotResumable::because($course, ResumeRefusal::SubtopicOccupied);
        }
    }

    private function lock(Course $course): Course
    {
        return Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
    }
}
