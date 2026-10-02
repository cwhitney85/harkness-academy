<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One document in the memory tree (spec 3.1).
 *
 * Reached only through the path API (spec 3.2, `App\Memory\MemoryTree`) --
 * no other class may query this model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $owner_type Course, or Subtopic for working knowledge (spec 16.3.1)
 * @property int $owner_id
 * @property string $path
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at set with `removal_id`, by a removal (spec 16.2.1)
 * @property int|null $removal_id
 */
class Memory extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The course a document belongs to, or the subtopic whose working
     * knowledge it is (ledger D58, D69).
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<MemoryVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(MemoryVersion::class);
    }
}
