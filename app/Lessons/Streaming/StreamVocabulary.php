<?php

namespace App\Lessons\Streaming;

use InvalidArgumentException;

/**
 * The complete set of events Harkness Academy streams (spec 5.2.5).
 *
 * 5.2.5's done-condition is "recorded transcript validates; no undocumented
 * events". This list is what "documented" means, and `assertKnown` is what
 * makes an undocumented event impossible to send rather than merely
 * discouraged: every frame goes through `EventStream`, which checks here.
 *
 * Adding a name is a spec change. If a sixth event is genuinely needed, it
 * belongs in 5.2.5 first and here second.
 */
final class StreamVocabulary
{
    /** @var list<string> */
    public const NAMES = [
        'turn.start',
        'turn.delta',
        'turn.end',
        'checkpoint.ready',
        'stream.error',
    ];

    /**
     * How often a heartbeat comment goes out when nothing else is (5.2.6).
     *
     * SSE comments, not events -- a heartbeat is a fact about the connection,
     * not about the lesson, and giving it an event name would put a sixth
     * entry in a vocabulary that has exactly five.
     */
    public const HEARTBEAT_SECONDS = 15;

    /**
     * @throws InvalidArgumentException if the event is not in the vocabulary.
     */
    public static function assertKnown(StreamEvent $event): void
    {
        if (! in_array($event->name(), self::NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Event [%s] is not in the 5.2.5 vocabulary [%s]. Amend the spec before the code.',
                $event->name(),
                implode(', ', self::NAMES),
            ));
        }
    }
}
