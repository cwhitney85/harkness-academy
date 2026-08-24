<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One document in the memory tree (spec 3.1).
 *
 * Reached only through the path API (spec 3.2, `App\Memory\MemoryTree`) --
 * no other class may query this model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Memory extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MemoryVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(MemoryVersion::class);
    }
}
