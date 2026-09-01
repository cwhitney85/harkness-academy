import { csrfHeader } from '@/lib/api';
import type { TurnEvent } from '@/types';

/**
 * Read one streamed turn (spec 5.2.5, 5.2.6).
 *
 * `EventSource` cannot be used: half the streams in the product are POSTs (an
 * interview answer, a lesson question, a highlight), and EventSource speaks
 * only GET and carries no CSRF token. So this reads the same SSE bytes off
 * `fetch`, which does both.
 *
 * Pre:  `url` is one of the streaming endpoints; the caller has established
 *       that requesting it is allowed (the interview's re-open precondition is
 *       the one that matters — see spec 8.6).
 * Post: `onEvent` has been called once per frame, in order, before this
 *       resolves. Heartbeat comments are dropped, as they are not events.
 *
 * A non-2xx response never reaches the parser: the closed doors are HTTP
 * status codes by design, and only genuine mid-flight failures arrive as
 * `stream.error`. This throws on them so a caller does not have to distinguish
 * "refused" from "said nothing".
 */
export async function readTurnStream(
    url: string,
    onEvent: (event: TurnEvent) => void,
    request: { method?: 'GET' | 'POST'; body?: unknown } = {},
): Promise<void> {
    const method = request.method ?? 'GET';

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'text/event-stream',
            ...(request.body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...csrfHeader(),
        },
        body:
            request.body === undefined
                ? undefined
                : JSON.stringify(request.body),
    });

    if (!response.ok || !response.body) {
        throw new Error(`Stream refused with ${response.status}.`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        // Frames are separated by a blank line. Anything after the last one is
        // a partial frame and stays in the buffer until the rest arrives.
        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        for (const frame of frames) {
            const event = parseFrame(frame);

            if (event) {
                onEvent(event);
            }
        }
    }
}

/**
 * One SSE frame, or null for anything that is not an event.
 *
 * Heartbeats are comments (`: heartbeat`) — they carry no name and are ignored
 * here exactly as `EventStream::parse` ignores them on the server.
 */
function parseFrame(frame: string): TurnEvent | null {
    let name: string | null = null;
    let data: string | null = null;

    for (const line of frame.split('\n')) {
        if (line.startsWith('event: ')) {
            name = line.slice(7);
        } else if (line.startsWith('data: ')) {
            data = line.slice(6);
        }
    }

    if (name === null || data === null) {
        return null;
    }

    try {
        return { name, data: JSON.parse(data) } as TurnEvent;
    } catch {
        return null;
    }
}
