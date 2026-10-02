<?php

namespace App\Models;

use App\Ai\Catalog;
use App\Ai\Providers\Providers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * One model in the catalog (spec 14.1.1, ledger D52, D53).
 *
 * Read through `App\Ai\Catalog`, which caches the whole catalog; a write here
 * clears that cache. A model can be active only if its provider has a class
 * (spec 14.2.2), so the check sits on every write rather than on one form.
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

    /**
     * The column's default, known before insert so the 14.2.2 check sees it.
     */
    protected $attributes = ['active' => true];

    protected static function booted(): void
    {
        static::saving(function (AiModel $model): void {
            if ($model->active && ! Providers::has($model->provider)) {
                throw ValidationException::withMessages([
                    'active' => "A {$model->provider} model cannot be active: no provider class speaks for {$model->provider} yet (spec 14.2.2).",
                ]);
            }
        });
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
