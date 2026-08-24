<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 */
class Module extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany<Subtopic, $this>
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(Subtopic::class)->orderBy('position');
    }
}
