<?php

namespace App\Models;

use App\Courses\LessonStatus;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One lesson of an approved course (spec 4.5.5.1, ledger D10.5).
 *
 * Carries no title or objective: those are read from the course's approved
 * syllabus JSON, which is the single source the student's syllabus view
 * renders from too.
 *
 * @property int $id
 * @property int $course_id
 * @property int $position
 * @property LessonStatus $status
 * @property string $guidance_path
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
