<?php

namespace App\Lessons\Streaming;

/**
 * The section's checkpoint is available (spec 5.2.5).
 *
 * The payload is the stripped form of 5.2.3 -- no `correct_option_id`, no
 * `explanation` -- and this class takes it already stripped rather than
 * stripping it here. Stripping is a property of the checkpoint document, so
 * `Checkpoint::clientPayload()` owns it; a transport that could also emit the
 * unstripped form would be a second place to get it wrong.
 *
 * The id travels with it because `POST .../checkpoints/{checkpoint}/answers`
 * is addressed by it: a checkpoint a client learned about from this event and
 * could not then answer would be a question with no way to answer it (spec
 * 8.8.3, Colin, 2026-09-01). It is not part of the answer key and discloses
 * nothing -- the same id is already on every checkpoint a resume restores.
 */
final readonly class CheckpointReady implements StreamEvent
{
    /**
     * @param  array<string, mixed>  $payload  already stripped per 5.2.3
     */
    public function __construct(public int $checkpointId, public array $payload) {}

    public function name(): string
    {
        return 'checkpoint.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['checkpoint_id' => $this->checkpointId, ...$this->payload];
    }
}
