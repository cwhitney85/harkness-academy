<?php

namespace App\Courses;

use App\Courses\Exceptions\SubtopicAlreadyOpen;
use App\Courses\Exceptions\UnusableSubtopicName;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Start a course (spec 4.1).
 *
 * Two doors, one room: picking a preset and typing free text differ only in
 * where the name comes from. Everything after that -- slug, attempt number,
 * exclusivity -- is a single path, so the two cannot drift apart.
 *
 * Pre: the student has no open course in this subtopic.
 * Post: exactly one `courses` row, status `created`, `withdrawn_at` null,
 *       `attempt` one past the highest previous attempt at this level, and
 *       pointing at its subtopic row -- a typed name nobody has used before
 *       creates an unlisted `student` row (16.1.2).
 */
final class CreateCourse
{
    /**
     * @throws SubtopicAlreadyOpen
     */
    public function fromCatalog(User $student, Subtopic $subtopic, SkillLevel $level): Course
    {
        return $this->create(
            $student,
            $subtopic->module,
            SubtopicName::fromCatalog($subtopic),
            $level,
        );
    }

    /**
     * @throws UnusableSubtopicName
     * @throws SubtopicAlreadyOpen
     */
    public function fromFreeText(User $student, Module $module, string $name, SkillLevel $level): Course
    {
        return $this->create(
            $student,
            $module,
            SubtopicName::fromFreeText($name),
            $level,
        );
    }

    /**
     * @throws SubtopicAlreadyOpen
     */
    private function create(User $student, Module $module, SubtopicName $name, SkillLevel $level): Course
    {
        try {
            // The subtopic row is found or created in the course's own
            // transaction, so a course refused below leaves no row behind
            // (16.1.2). A catalog pick always finds its own row.
            return DB::transaction(fn (): Course => Course::query()->create([
                'user_id' => $student->id,
                'subtopic_id' => Subtopic::findOrCreateFor($module, $name->slug, $name->title)->id,
                'module_slug' => $module->slug,
                'subtopic_slug' => $name->slug,
                'subtopic_title' => $name->title,
                'skill_level' => $level,
                'attempt' => $this->nextAttempt($student, $module->slug, $name->slug, $level),
                'status' => CourseStatus::Created,
            ]));
        } catch (QueryException $e) {
            // The indexes are the authority, not a prior SELECT -- two
            // requests in the same millisecond both see nothing open.
            //
            // Either index firing means the same thing. `one_open_per_subtopic`
            // is the plain case. The attempt key can only collide when a
            // concurrent request inserted the number we just computed, and a
            // course inserted a moment ago is by definition still open.
            if ($this->violatesCourseUniqueness($e)) {
                throw SubtopicAlreadyOpen::for($student, $module->slug, $name->slug);
            }

            throw $e;
        }
    }

    private function violatesCourseUniqueness(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'courses_one_open_per_subtopic')
            || str_contains($e->getMessage(), 'courses_one_attempt_per_level');
    }

    /**
     * One past the highest run at this subtopic and level (ledger D7b),
     * removed runs included: the attempt key still counts them (16.2.5).
     *
     * Deliberately unlocked: Postgres refuses `FOR UPDATE` alongside an
     * aggregate, and the attempt unique key already rejects a number two
     * concurrent callers computed at once.
     */
    private function nextAttempt(User $student, string $moduleSlug, string $subtopicSlug, SkillLevel $level): int
    {
        return 1 + (int) Course::withTrashed()
            ->forLevel($student, $moduleSlug, $subtopicSlug, $level)
            ->max('attempt');
    }
}
