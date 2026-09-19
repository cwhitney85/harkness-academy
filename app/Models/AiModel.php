<?php

namespace App\Models;

use App\Ai\Catalog;
use Illuminate\Database\Eloquent\Model;

/**
 * One model in the catalog (spec 14.1.1, ledger D52, D53).
 *
 * Read through `App\Ai\Catalog`, which caches the whole catalog; a write here
 * clears that cache.
 *
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property string $display_name
 * @property bool $active
 * @property string $input_per_mtok
 * @property string $output_per_mtok
 * @property string $cache_read_per_mtok
 * @property string $cache_write_per_mtok
 */
class AiModel extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => Catalog::forget());
        static::deleted(fn () => Catalog::forget());
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'input_per_mtok' => 'decimal:4',
            'output_per_mtok' => 'decimal:4',
            'cache_read_per_mtok' => 'decimal:4',
            'cache_write_per_mtok' => 'decimal:4',
        ];
    }
}
