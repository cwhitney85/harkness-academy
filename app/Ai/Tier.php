<?php

namespace App\Ai;

use InvalidArgumentException;

/**
 * A capability tier (spec 2.1.1).
 *
 * Call sites name a tier; the tier names a model in the catalog (spec 14.1.2,
 * ledger D52). `Session` is not a tier of its own -- it marks a prompt that
 * runs on the model its session started on (`lesson.section` in the 2.3.2
 * inventory, spec 14.1.6).
 *
 * @phpstan-import-type ModelRow from Catalog
 */
enum Tier: string
{
    case Cheap = 'cheap';
    case Mid = 'mid';
    case Top = 'top';
    case Session = 'session';
    case None = 'none';

    /**
     * The model id of this tier's primary.
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    public function model(): string
    {
        return $this->primary()['model'];
    }

    /**
     * The catalog row of this tier's primary model.
     *
     * @return ModelRow
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    public function primary(): array
    {
        return Catalog::model($this->row()['model'])
            ?? throw new InvalidArgumentException("Tier [{$this->value}] names a model the catalog does not hold.");
    }

    /**
     * How long a call at this tier may take, in seconds.
     *
     * Beside the model because it is the same kind of fact about a tier: the
     * bigger the model and the more it is asked to write in one reply, the
     * longer it needs. laravel/ai's own default is 60 seconds for every call
     * it makes, which no `top` structured generation can meet.
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    public function timeout(): int
    {
        return $this->row()['timeout'];
    }

    /**
     * The catalog rows laravel/ai fails over through, keyed by provider, in
     * order: the primary -- or the model a session started on -- then the
     * backups.
     *
     * laravel/ai keys its failover list by provider, so it cannot fall back
     * from one model to another on the same provider; a backup whose provider
     * is already in the list is dropped (ledger D52's 2026-09-19 note).
     *
     * @return non-empty-array<string, ModelRow>
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    public function failover(?int $startedOn = null): array
    {
        $first = $startedOn === null ? $this->primary() : (Catalog::model($startedOn)
            ?? throw new InvalidArgumentException("The session's model [{$startedOn}] is not in the catalog."));

        $models = [$first['provider'] => $first];

        foreach ($this->row()['backups'] as $id) {
            $model = Catalog::model($id);

            if ($model !== null) {
                $models[$model['provider']] ??= $model;
            }
        }

        return $models;
    }

    /**
     * Whether this tier resolves to a model on its own.
     */
    public function isBillable(): bool
    {
        return in_array($this, [self::Cheap, self::Mid, self::Top], true);
    }

    /**
     * The tier a call actually runs at, resolving `session` against its session tier.
     */
    public function resolve(?Tier $sessionTier = null): Tier
    {
        if ($this !== self::Session) {
            return $this;
        }

        if (! $sessionTier instanceof Tier || ! $sessionTier->isBillable()) {
            throw new InvalidArgumentException(
                'A session-tier prompt must be called with the tier of the session it belongs to.'
            );
        }

        return $sessionTier;
    }

    /**
     * @return array{model: int, backups: list<int>, timeout: int}
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    private function row(): array
    {
        return Catalog::tier($this->value) ?? throw new InvalidArgumentException(
            "Tier [{$this->value}] does not resolve to a model. Only cheap, mid and top are billable tiers."
        );
    }
}
