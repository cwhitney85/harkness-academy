<?php

namespace App\Lessons\Streaming;

/**
 * Something went wrong mid-stream (spec 5.2.5).
 *
 * `retryable` is the whole value of this event: it tells the client whether
 * reopening the stream is worth doing. A provider hiccup is; a lesson the
 * student is not allowed to open is not, and retrying it forever is how a
 * phone burns its battery on a closed door.
 */
final readonly class StreamError implements StreamEvent
{
    public function __construct(
        public string $code,
        public bool $retryable,
    ) {}

    /**
     * The stream died on the way to the provider; reopening may well work.
     */
    public static function retryable(string $code): self
    {
        return new self($code, retryable: true);
    }

    /**
     * The request itself is wrong; reopening it will fail the same way.
     */
    public static function fatal(string $code): self
    {
        return new self($code, retryable: false);
    }

    public function name(): string
    {
        return 'stream.error';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['code' => $this->code, 'retryable' => $this->retryable];
    }
}
