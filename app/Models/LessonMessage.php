<?php

namespace App\Models;

use App\Lessons\Speaker;
use App\Lessons\Streaming\TurnKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One completed turn of a lesson (spec 5.2.6).
 *
 * A row exists only for a turn that finished streaming. There is deliberately
 * no `partial` or `status` column: an interrupted turn leaves nothing, which
 * is what lets 5.2.6 say "replay if the turn completed, else regenerate" by
 * asking whether the row is there.
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $turn_id
 * @property TurnKind $kind
 * @property Speaker $speaker
 * @property int $section_index
 * @property string $content
 * @property int $position
 * @property Carbon $created_at
 */
class LessonMessage extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TurnKind::class,
            'speaker' => Speaker::class,
            'section_index' => 'integer',
            'position' => 'integer',
            'created_at' => 'datetime',
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
