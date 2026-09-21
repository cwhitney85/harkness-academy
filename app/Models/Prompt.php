<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * One prompt in the 2.3.2 inventory, and the versions of it that have run
 * (spec 15.1.1, ledger D65).
 *
 * `output` and `vars` are the contract the calling code depends on, so they
 * come only from the file and `prompts:sync` overwrites them on every deploy.
 * The body and the tier live on the active version, which the editor owns:
 * the file's body seeds version 1 and is never read again.
 *
 * Read through `App\Ai\Prompts\PromptRepository`, which caches the whole
 * registry; a write here or to a version clears that cache.
 *
 * @property int $id
 * @property string $key
 * @property string $output The raw frontmatter contract, e.g. `json/quiz.v1`.
 * @property list<string> $vars
 * @property ?int $active_version_id
 */
class Prompt extends Model
{
    /**
     * The cache key the repository reads the whole registry under.
     */
    public const CACHE_KEY = 'ai.prompts';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    /**
     * @return HasMany<PromptVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    /**
     * @return BelongsTo<PromptVersion, $this>
     */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'active_version_id');
    }

    /**
     * Make this version the one that runs.
     *
     * Pre: the version belongs to this prompt.
     * Post: exactly one version of this prompt is active, and it is this one.
     */
    public function activate(PromptVersion $version): void
    {
        $this->active_version_id = $version->id;
        $this->save();
        $this->setRelation('activeVersion', $version);
    }

    protected function casts(): array
    {
        return [
            'vars' => 'array',
        ];
    }
}
