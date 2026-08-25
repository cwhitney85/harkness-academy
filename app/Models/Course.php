<?php

namespace App\Models;

use App\Courses\CourseStatus;
use App\Courses\SkillLevel;
use App\Memory\WorkingKnowledge;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One student's run at one subtopic at one skill level (spec 4.1, ledger D7b).
 *
 * Status changes go through `App\Courses\CourseTransitions` and nowhere else;
 * writing `status` or `withdrawn_at` directly skips the legality check and the
 * event workflows hang off.
 *
 * @property int $id
 * @property int $user_id
 * @property string $module_slug
 * @property string $subtopic_slug
 * @property string $subtopic_title
 * @property SkillLevel $skill_level
 * @property int $attempt
 * @property CourseStatus $status
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skill_level' => SkillLevel::class,
            'status' => CourseStatus::class,
            'withdrawn_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Every syllabus this course has been shown, oldest first (spec 4.5.4.1).
     *
     * @return HasMany<CourseSyllabus, $this>
     */
    public function syllabi(): HasMany
    {
        return $this->hasMany(CourseSyllabus::class)->orderBy('generation');
    }

    /**
     * The generation the student accepted, once they have.
     *
     * Held to one row by `course_syllabi_one_approved_per_course`, so this is
     * a `hasOne` by constraint and not merely by convention.
     *
     * @return HasOne<CourseSyllabus, $this>
     */
    public function approvedSyllabus(): HasOne
    {
        return $this->hasOne(CourseSyllabus::class)->approved();
    }

    /**
     * The syllabus the student is currently looking at, if any.
     *
     * @return HasOne<CourseSyllabus, $this>
     */
    public function latestSyllabus(): HasOne
    {
        return $this->hasOne(CourseSyllabus::class)->latestOfMany('generation');
    }

    /**
     * The whole story: pipeline position, or the ending that overrode it.
     *
     * What a course list, badge or filter should read. The `status` column
     * alone answers only half the question -- a withdrawn course still holds
     * the position it was left on, which is what resume needs (ledger D7d).
     */
    public function standing(): CourseStatus
    {
        if ($this->isCompleted()) {
            return CourseStatus::Completed;
        }

        if ($this->isWithdrawn()) {
            return CourseStatus::Withdrawn;
        }

        return $this->status;
    }

    /**
     * Whether this course currently holds its subtopic's single open slot.
     *
     * Mirrors the `courses_one_open_per_subtopic` index predicate exactly.
     */
    public function isOpen(): bool
    {
        return $this->withdrawn_at === null && $this->completed_at === null;
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * The subtopic knowledge document this course reads and revises (D8).
     *
     * Shared with every other course in the subtopic, at every skill level --
     * that sharing is why only one course may be open here at a time.
     */
    public function workingKnowledgePath(): string
    {
        return WorkingKnowledge::forSubtopic($this->module_slug, $this->subtopic_slug);
    }

    /**
     * Where this course's own materials hang (spec 4.5.3).
     *
     * Carries no attempt number, so a later attempt at the same level
     * overwrites an earlier one's lesson guidance -- which is why only the
     * highest attempt may be resumed (ledger D7b).
     */
    public function materialsPrefix(): string
    {
        return "{$this->module_slug}/{$this->subtopic_slug}/".$this->skill_level->pathSegment();
    }

    /**
     * Courses holding their subtopic's open slot.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('withdrawn_at')->whereNull('completed_at');
    }

    /**
     * Every course one student has taken in one subtopic, at any level.
     *
     * The scope of the exclusivity rule (ledger D7a/D7b) and of the subtopic
     * WORKING_KNOWLEDGE.md they all share (D8).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeForSubtopic(Builder $query, User $student, string $moduleSlug, string $subtopicSlug): void
    {
        $query->where('user_id', $student->id)
            ->where('module_slug', $moduleSlug)
            ->where('subtopic_slug', $subtopicSlug);
    }

    /**
     * Narrower still: one subtopic at one skill level, which is what `attempt`
     * counts within.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeForLevel(Builder $query, User $student, string $moduleSlug, string $subtopicSlug, SkillLevel $level): void
    {
        $query->forSubtopic($student, $moduleSlug, $subtopicSlug)
            ->where('skill_level', $level->value);
    }
}
