<?php

namespace App\Models;

use App\Lessons\Pipeline\PipelineStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A step of the completion chain that has already run (spec 5.4.1).
 *
 * A row means done. There is no `failed` state: a step that failed leaves no
 * row, so a retried or re-dispatched chain resumes at exactly the step that
 * broke rather than replaying the ones that worked.
 *
 * @property int $id
 * @property int $lesson_id
 * @property PipelineStep $step
 * @property Carbon $completed_at
 */
class LessonPipelineStep extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => PipelineStep::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
