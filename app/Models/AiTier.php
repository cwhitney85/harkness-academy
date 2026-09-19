<?php

namespace App\Models;

use App\Ai\Catalog;
use Illuminate\Database\Eloquent\Model;

/**
 * A capability tier as a row (spec 14.1.2, ledger D52): a primary model, the
 * backups the SDK fails over to in order, and a timeout in seconds.
 *
 * Read through `App\Ai\Catalog`; a write here clears its cache.
 *
 * @property int $id
 * @property string $name
 * @property int $ai_model_id
 * @property list<int> $backup_model_ids
 * @property int $timeout
 */
class AiTier extends Model
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
            'backup_model_ids' => 'array',
            'timeout' => 'integer',
        ];
    }
}
