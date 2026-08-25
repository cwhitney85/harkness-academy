<?php

namespace App\Lessons\Streaming;

/**
 * The turn is complete and persisted (spec 5.2.5, 5.2.6).
 *
 * `message_id` is the promise that the turn survived: the row exists by the
 * time this event is written, so a client that saw it can reconnect and be
 * replayed rather than re-taught (5.2.6).
 */
final readonly class TurnEnd implements StreamEvent
{
    public function __construct(
        public string $turnId,
        public int $messageId,
    ) {}

    public function name(): string
    {
        return 'turn.end';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['turn_id' => $this->turnId, 'message_id' => $this->messageId];
    }
}
