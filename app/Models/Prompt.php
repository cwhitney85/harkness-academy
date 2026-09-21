<?php

namespace App\Models;

use App\Ai\Prompts\PromptSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * One prompt in the 2.3.2 inventory, and the versions of it that have run
 * (spec 15.1.1, ledger D54).
 *
 * `output` and `vars` are the contract the calling code depends on, so they
 * come only from the file and `prompts:sync` overwrites them on every deploy.
 * The body and the tier live on the active version.
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
     * The newest version that came from a file -- the file's last known state,
     * which is what `prompts:sync` compares the file against.
     */
    public function latestFileVersion(): ?PromptVersion
    {
        return $this->versions()
            ->where('source', PromptSource::File)
            ->orderByDesc('number')
            ->first();
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

    /**
     * Whether a deploy has landed a file version this prompt is not running
     * (spec 15.1.3): the live edit stays active, and this is what the editor
     * flags.
     */
    public function hasNewerFileVersion(): bool
    {
        $active = $this->activeVersion;

        if ($active === null || $active->source === PromptSource::File) {
            return false;
        }

        return $this->versions()
            ->where('source', PromptSource::File)
            ->where('number', '>', $active->number)
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'vars' => 'array',
        ];
    }
}
