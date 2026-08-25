<?php

namespace App\Models;

use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Speaker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One turn of one interview (spec 4.3, 4.4).
 *
 * Written and deleted by `App\Courses\Interviews\Transcript` and nowhere else:
 * the guarantee that a transcript does not outlive its summary is only worth
 * anything if one class owns the delete.
 *
 * @property int $id
 * @property int $course_id
 * @property InterviewKind $kind
 * @property Speaker $speaker
 * @property string $content
 * @property int $position
 * @property Carbon $created_at
 */
class InterviewMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InterviewKind::class,
            'speaker' => Speaker::class,
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
