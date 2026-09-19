<?php

namespace App\Ai;

use App\Models\AiModel;
use App\Models\AiTier;
use Illuminate\Support\Facades\Cache;

/**
 * The model catalog and the tiers over it, as one cached read (spec 14.1.3).
 *
 * Every AI call resolves its tier here, so the catalog is read once and kept:
 * memoised within a request or job, and in the cache store across them until
 * an `AiModel` or `AiTier` write clears it. Plain arrays, because this app's
 * cache refuses to unserialize objects (`cache.serializable_classes`).
 *
 * @phpstan-type ModelRow array{id: int, provider: string, model: string, input: float, output: float, cache_read: float, cache_write: float}
 * @phpstan-type TierRow array{model: int, backups: list<int>, timeout: int}
 */
final class Catalog
{
    private const KEY = 'ai.catalog';

    /**
     * @return TierRow|null
     */
    public static function tier(string $name): ?array
    {
        return self::load()['tiers'][$name] ?? null;
    }

    /**
     * @return ModelRow|null
     */
    public static function model(int $id): ?array
    {
        return self::load()['models'][$id] ?? null;
    }

    public static function forget(): void
    {
        Cache::memo()->forget(self::KEY);
    }

    /**
     * @return array{models: array<int, ModelRow>, tiers: array<string, TierRow>}
     */
    private static function load(): array
    {
        return Cache::memo()->rememberForever(self::KEY, fn (): array => [
            'models' => AiModel::all()->mapWithKeys(fn (AiModel $row): array => [$row->id => [
                'id' => $row->id,
                'provider' => $row->provider,
                'model' => $row->model,
                'input' => (float) $row->input_per_mtok,
                'output' => (float) $row->output_per_mtok,
                'cache_read' => (float) $row->cache_read_per_mtok,
                'cache_write' => (float) $row->cache_write_per_mtok,
            ]])->all(),
            'tiers' => AiTier::all()->mapWithKeys(fn (AiTier $row): array => [$row->name => [
                'model' => $row->ai_model_id,
                'backups' => $row->backup_model_ids,
                'timeout' => $row->timeout,
            ]])->all(),
        ]);
    }
}
