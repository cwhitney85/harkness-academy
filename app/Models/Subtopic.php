<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A preset subtopic offered under a module (spec 4.1).
 *
 * A suggestion the picker shows, not the identity of anything a student owns.
 * Picking one and typing its name freehand produce the same course, because
 * both resolve to the same slug (ledger D7b).
 *
 * @property int $id
 * @property int $module_id
 * @property string $slug
 * @property string $title
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subtopic extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
