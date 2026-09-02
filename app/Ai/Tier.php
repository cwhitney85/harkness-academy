<?php

namespace App\Ai;

use InvalidArgumentException;

/**
 * A capability tier (spec 2.1.1).
 *
 * Call sites name a tier; only config/ai.php names a model. `Session` is not a
 * tier of its own -- it marks a prompt that runs at whatever tier the session
 * it belongs to is already using (`lesson.section` in the 2.3.2 inventory).
 */
enum Tier: string
{
    case Cheap = 'cheap';
    case Mid = 'mid';
    case Top = 'top';
    case Session = 'session';
    case None = 'none';

    /**
     * The model this tier resolves to.
     *
     * @throws InvalidArgumentException if the tier does not name a model.
     */
    public function model(): string
    {
        $model = config("ai.tiers.{$this->value}.model");

        if (! is_string($model) || $model === '') {
            throw new InvalidArgumentException(
                "Tier [{$this->value}] does not resolve to a model. Only cheap, mid and top are billable tiers."
            );
        }

        return $model;
    }

    /**
     * The US dollars-per-million-token rates for this tier.
     *
     * @return array{input: float, output: float}
     */
    public function pricing(): array
    {
        $pricing = config("ai.tiers.{$this->value}.pricing");

        if (! is_array($pricing)) {
            throw new InvalidArgumentException("Tier [{$this->value}] has no pricing configured.");
        }

        return ['input' => (float) $pricing['input'], 'output' => (float) $pricing['output']];
    }

    /**
     * How long a call at this tier may take, in seconds.
     *
     * Beside the model because it is the same kind of fact about a tier: the
     * bigger the model and the more it is asked to write in one reply, the
     * longer it needs. laravel/ai's own default is 60 seconds for every call
     * it makes, which no `top` structured generation can meet.
     *
     * @throws InvalidArgumentException if the tier does not name one.
     */
    public function timeout(): int
    {
        $timeout = config("ai.tiers.{$this->value}.timeout");

        if (! is_int($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException("Tier [{$this->value}] has no timeout configured.");
        }

        return $timeout;
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
}
