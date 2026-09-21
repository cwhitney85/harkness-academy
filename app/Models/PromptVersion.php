<?php

namespace App\Models;

use App\Ai\Prompts\PromptSource;
use App\Ai\Tier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One immutable version of a prompt's body and tier (spec 15.1.1, ledger D54).
 *
 * Invariant: a version is written once and never again. Tuning makes a new
 * version, so every usage row that names a version can be read back against
 * exactly the text that ran. The model enforces it rather than a convention:
 * there is no update path.
 *
 * `file_hash` is the sha256 of the file the version was synced from, and is
 * null exactly when `source` is `admin` -- `source` names the case, the null
 * does not.
 *
 * @property int $id
 * @property int $prompt_id
 * @property int $number
 * @property string $body
 * @property Tier $tier
 * @property PromptSource $source
 * @property ?int $author_id
 * @property ?string $file_hash
 * @property Carbon $created_at
 */
class PromptVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (PromptVersion $version): void {
            throw new LogicException(
                "Prompt version [{$version->number}] of prompt [{$version->prompt_id}] cannot be updated: "
                .'a version is immutable, and tuning makes a new one (spec 15.1.1).'
            );
        });

        static::saved(fn () => Prompt::forgetCache());
        static::deleted(fn () => Prompt::forgetCache());
    }

    /**
     * @return BelongsTo<Prompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'tier' => Tier::class,
            'source' => PromptSource::class,
            'created_at' => 'datetime',
        ];
    }
}
