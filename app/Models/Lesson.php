<?php

namespace App\Models;

use App\Courses\LessonStatus;
use App\Lessons\LessonSummary;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One lesson of an approved course (spec 4.5.5.1, ledger D10.5).
 *
 * Carries no title or objective: those are read from the course's approved
 * syllabus JSON, which is the single source the student's syllabus view
 * renders from too.
 *
 * Status changes go through `App\Lessons\LessonProgress` and the completion
 * pipeline; writing `status`, `section_index` or `completed_at` from anywhere
 * else skips the openable check and the chain that hangs off completion.
 *
 * @property int $id
 * @property int $course_id
 * @property int $position
 * @property LessonStatus $status
 * @property int|null $section_index
 * @property string $guidance_path
 * @property Carbon|null $started_at
 * @property int|null $ai_model_id
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $summary_json
 * @property Carbon|null $pipeline_parked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => LessonStatus::class,
            'section_index' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary_json' => 'array',
            'pipeline_parked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The lesson transcript, in the order it was spoken (spec 5.2.6).
     *
     * Read through `App\Lessons\LessonTranscript` for anything but a count:
     * that class is the only writer, and it is where the rendering the
     * provider and the summarizer each need lives.
     *
     * @return HasMany<LessonMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LessonMessage::class)->orderBy('position');
    }

    /**
     * The checkpoints and homework this lesson has produced (spec 6.1).
     *
     * @return MorphMany<Quiz, $this>
     */
    public function quizzes(): MorphMany
    {
        return $this->morphMany(Quiz::class, 'quizzable');
    }

    /**
     * Which steps of the completion chain have already run (spec 5.4.1).
     *
     * @return HasMany<LessonPipelineStep, $this>
     */
    public function pipelineSteps(): HasMany
    {
        return $this->hasMany(LessonPipelineStep::class);
    }

    /**
     * The next lesson of the course, if this is not the last.
     *
     * What 5.4.1's chain unlocks when it finishes, and what 5.4.6's escape
     * hatch unlocks when it does not.
     */
    public function next(): ?self
    {
        return static::query()
            ->where('course_id', $this->course_id)
            ->where('position', $this->position + 1)
            ->first();
    }

    /**
     * The structured summary, once the chain has written one (spec 5.4.2).
     */
    public function summary(): ?LessonSummary
    {
        return $this->summary_json === null ? null : LessonSummary::of($this->summary_json);
    }

    /**
     * Whether this lesson is past what a demo visitor may take: the demo is
     * lesson 1 of an ordinary course, and signing up opens the rest (spec
     * 12.4.1).
     */
    public function isBeyondDemo(): bool
    {
        return $this->position > 1 && $this->course->student->isDemo();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Whether the completion chain gave up (spec 5.4.5).
     */
    public function isPipelineParked(): bool
    {
        return $this->pipeline_parked_at !== null;
    }

    /**
     * This lesson's entry in the approved syllabus.
     *
     * Pre: the course has an approved syllabus -- guaranteed, because approval
     * is the only thing that creates lesson rows (spec 4.5.5).
     *
     * @return array<string, mixed>
     */
    public function syllabusEntry(): array
    {
        $lessons = $this->course->approvedSyllabus->syllabus_json['lessons'];

        foreach ($lessons as $lesson) {
            if ((int) $lesson['position'] === $this->position) {
                return $lesson;
            }
        }

        throw new \LogicException("Approved syllabus has no lesson at position {$this->position}.");
    }
}
