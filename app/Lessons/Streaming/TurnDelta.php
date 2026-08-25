<?php

namespace App\Lessons\Streaming;

/**
 * A fragment of the turn's text (spec 5.2.5).
 *
 * Carries the turn id rather than relying on ordering, so a client that
 * reconnects mid-turn can discard deltas belonging to a turn it already has.
 */
final readonly class TurnDelta implements StreamEvent
{
    public function __construct(
        public string $turnId,
        public string $text,
    ) {}

    public function name(): string
    {
        return 'turn.delta';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['turn_id' => $this->turnId, 'text' => $this->text];
    }
}
