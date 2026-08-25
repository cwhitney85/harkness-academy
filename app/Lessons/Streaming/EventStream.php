<?php

namespace App\Lessons\Streaming;

use Closure;

/**
 * The SSE wire format (spec 5.2.5, 5.2.6).
 *
 * Every frame the product streams goes through `send`, which is where
 * StreamVocabulary::assertKnown runs -- so "no undocumented events" is a
 * property of the transport rather than a rule each controller remembers.
 *
 * The sink is a closure rather than an echo so the same object serves a real
 * `response()->stream()` and a test that collects frames into a string. The
 * class knows the format; it does not know where the bytes go.
 *
 * Heartbeats are SSE comments (`: heartbeat`). Comments are ignored by every
 * EventSource implementation but still traverse the proxies and load balancers
 * that close a connection which has said nothing for a minute -- which is the
 * only reason 5.2.6 asks for them.
 */
final class EventStream
{
    /** @var Closure(): float */
    private readonly Closure $clock;

    private float $lastWriteAt;

    /**
     * @param  Closure(string): void  $sink  receives one encoded frame at a time
     * @param  (Closure(): float)|null  $clock  seconds; injectable so the heartbeat
     *                                          interval is testable without a test
     *                                          that actually waits 15 seconds
     */
    public function __construct(
        private readonly Closure $sink,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);

        $this->lastWriteAt = ($this->clock)();
    }

    /**
     * Write one event.
     *
     * Post: the frame has reached the sink and the heartbeat clock is reset --
     * a stream that is saying things does not also need to say nothing.
     *
     * @throws \InvalidArgumentException if the event is not in the vocabulary.
     */
    public function send(StreamEvent $event): void
    {
        StreamVocabulary::assertKnown($event);

        $this->write(self::frame($event));
    }

    /**
     * Write a heartbeat if the connection has been silent long enough.
     *
     * Called from inside the delta loop and around the calls that can stall
     * -- the wait for the first token, and the checkpoint generation that
     * follows `turn.end`.
     */
    public function heartbeatIfDue(): void
    {
        if (($this->clock)() - $this->lastWriteAt >= StreamVocabulary::HEARTBEAT_SECONDS) {
            $this->write(": heartbeat\n\n");
        }
    }

    /**
     * One event as an SSE frame.
     */
    public static function frame(StreamEvent $event): string
    {
        $data = json_encode($event->data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return 'event: '.$event->name()."\n".'data: '.$data."\n\n";
    }

    /**
     * Read a recorded stream back into events.
     *
     * The counterpart of `frame()`, and what 5.2.5's "recorded transcript
     * validates" is checked with: a test streams a real lesson section, parses
     * the bytes back, and asserts every name is in the vocabulary and every
     * payload has the fields 5.2.5 declares.
     *
     * @return list<array{name: string, data: array<string, mixed>}>
     */
    public static function parse(string $body): array
    {
        $events = [];

        foreach (preg_split('/\n\n/', $body, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $frame) {
            $name = null;
            $data = null;

            foreach (explode("\n", $frame) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $name = substr($line, 7);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                }
            }

            // Comment frames -- heartbeats -- carry no event name and are not
            // part of the vocabulary.
            if ($name === null || $data === null) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);

            $events[] = ['name' => $name, 'data' => $decoded];
        }

        return $events;
    }

    private function write(string $frame): void
    {
        ($this->sink)($frame);

        $this->lastWriteAt = ($this->clock)();
    }
}
