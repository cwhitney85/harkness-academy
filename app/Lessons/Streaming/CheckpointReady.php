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
 */
final readonly class CheckpointReady implements StreamEvent
{
    /**
     * @param  array<string, mixed>  $payload  already stripped per 5.2.3
     */
    public function __construct(public array $payload) {}

    public function name(): string
    {
        return 'checkpoint.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->payload;
    }
}
