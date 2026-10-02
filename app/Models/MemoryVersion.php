<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The content one `put` wrote (spec 3.1).
 *
 * Append-only: a version row is never updated, so `version` ordering is the
 * document's history.
 *
 * @property int $id
 * @property int $memory_id
 * @property int $course_id the course whose workflow wrote it (spec 16.3.3)
 * @property int $version
 * @property string $content
 * @property int|null $acting_user_id the admin acting as the student (spec 13.4.3)
 * @property Carbon $created_at
 */
class MemoryVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
