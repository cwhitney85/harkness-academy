<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A top-level subject in the preset catalog (spec 4.1).
 *
 * Catalog only. Courses copy the slug rather than pointing here, so nothing
 * a student owns breaks when this row is re-titled.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at set with `removal_id`, by a removal (spec 16.2.1)
 * @property int|null $removal_id
 */
class Module extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return HasMany<Subtopic, $this>
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(Subtopic::class)->orderBy('position');
    }
}
