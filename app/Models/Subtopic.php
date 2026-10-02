<?php

namespace App\Models;

use App\Courses\SubtopicOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A subtopic under a module: a catalog entry, or a name a student typed
 * (spec 16.1, ledger D59).
 *
 * `listed` is what the picker offers and what the scope check calls the
 * catalog. A student row is unlisted until an admin promotes it. A course
 * points here and still keeps its own frozen slugs, which are its memory
 * path; retitling this row moves nothing.
 *
 * @property int $id
 * @property int $module_id
 * @property string $slug
 * @property string $title
 * @property int $position
 * @property SubtopicOrigin $origin
 * @property bool $listed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at set with `removal_id`, by a removal (spec 16.2.1)
 * @property int|null $removal_id
 */
class Subtopic extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => SubtopicOrigin::class,
            'listed' => 'boolean',
        ];
    }

    /**
     * The row a course at this slug belongs to, created as a student row if
     * no row has the slug yet (16.1.2). Two students typing the same name
     * share one row through the (module, slug) key; `firstOrCreate` falls
     * back to reading it when a concurrent insert wins.
     */
    public static function findOrCreateFor(Module $module, string $slug, string $title): self
    {
        return self::query()->firstOrCreate(
            ['module_id' => $module->id, 'slug' => $slug],
            fn (): array => [
                'title' => $title,
                'origin' => SubtopicOrigin::Student,
                'listed' => false,
                'position' => 1 + (int) self::query()->where('module_id', $module->id)->max('position'),
            ],
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('listed', true);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
