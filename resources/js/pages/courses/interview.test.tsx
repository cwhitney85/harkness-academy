import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import InterviewScreen from '@/pages/courses/interview';
import type { TurnEvent } from '@/types';

/**
 * Spec 4.7 — the knowledge → goals transition opens the goals stream.
 *
 * The transition is `router.reload()` following a redirect, and a reload
 * preserves state, so Inertia hands React the same key and the screen stays
 * mounted across the two URLs (ledger D28). What arrives is a new set of
 * props on a mounted component — which is exactly what `rerender` below is —
 * and what used to happen next was nothing at all: `opened` was still true,
 * the goals stream was never requested, and only a full page refresh cleared
 * the error the student was left looking at.
 *
 * Remove the `key` in interview.tsx and this test fails on its last line,
 * which is the whole of its point.
 */

const requested: string[] = [];

/** What the next stream does; by default it never ends. */
let stream: (onEvent: (event: TurnEvent) => void) => Promise<void> = () =>
    new Promise<void>(() => {});

vi.mock('@/lib/turn-stream', () => ({
    readTurnStream: (url: string, onEvent: (event: TurnEvent) => void) => {
        requested.push(url);

        return stream(onEvent);
    },
}));

const post = vi.fn();
const reload = vi.fn();

// `Link` is here because the shell's crest is one (8.5.7), not because this
// file tests it: an export the screen reaches for and the mock omits is a
// module-level throw, not a failed assertion. It resolves a wayfinder route
// definition the way Inertia's own Link does, so the anchor carries a URL.
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string | { url: string };
        children?: ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...rest}>
            {children}
        </a>
    ),
    router: {
        reload: (...args: unknown[]) => reload(...args),
        post: (...args: unknown[]) => post(...args),
    },
    // The rail head draws the wordmark from Inertia's shared props, which is
    // where `config('app.name')` reaches the client.
    usePage: () => ({ props: { name: 'Harkness Academy' } }),
}));

// Hoisted above the imports: jsdom has neither of these, the screen uses both
// -- the shell asks whether it is on a phone, the conversation keeps its foot
// in view -- and `use-mobile` asks at module scope, before any hook could run.
vi.hoisted(() => {
    window.matchMedia = ((query: string) => ({
        matches: false,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    })) as unknown as typeof window.matchMedia;
    Element.prototype.scrollIntoView = () => {};
});

afterEach(() => {
    cleanup();
    requested.length = 0;
    stream = () => new Promise<void>(() => {});
    post.mockClear();
    reload.mockClear();
});

type Turn = { id: number; speaker: 'student' | 'tutor'; content: string };

const ASKED: Turn[] = [
    { id: 1, speaker: 'tutor', content: 'What can you say already?' },
];

function props(
    kind: 'knowledge' | 'goals',
    turns: Turn[] = [],
    writeUp: { writing_up: boolean; write_up_failure: string | null } = {
        writing_up: false,
        write_up_failure: null,
    },
) {
    return {
        course: { id: 7, title: 'Beginner Spanish' },
        kind,
        turns,
        may_open: turns.length === 0,
        ...writeUp,
        limits: { answer_characters: 2000 },
        urls: {
            stream: `/courses/7/interviews/${kind}/stream`,
            answers: `/courses/7/interviews/${kind}/answers`,
            complete: `/courses/7/interviews/${kind}/complete`,
        },
    };
}

test('the goals stream is requested after a client-side visit from the knowledge URL', () => {
    const { rerender } = render(<InterviewScreen {...props('knowledge')} />);

    expect(requested).toEqual(['/courses/7/interviews/knowledge/stream']);

    rerender(<InterviewScreen {...props('goals')} />);

    expect(screen.getByText('What you want out of this')).toBeDefined();
    expect(requested).toEqual([
        '/courses/7/interviews/knowledge/stream',
        '/courses/7/interviews/goals/stream',
    ]);
});

/**
 * Spec 8.12.3 — Done is pressed once.
 *
 * The POST asks the queue for the write-up (11.4), and a second press while
 * the first is out asks again. The button disables and says what is being made
 * until the visit lands on `writing_up`, which the next test takes from there.
 */
test('Done disables itself and names the write-up, and cannot be pressed twice', () => {
    render(<InterviewScreen {...props('knowledge')} />);

    const done = screen.getByRole('button', { name: 'Done — write it up' });

    fireEvent.click(done);

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe(
        '/courses/7/interviews/knowledge/complete',
    );

    const working = screen.getByRole('button', {
        name: 'Writing up what you know…',
    });

    expect(working.hasAttribute('disabled')).toBe(true);

    fireEvent.click(working);

    expect(post).toHaveBeenCalledTimes(1);
});

/**
 * Spec 10 — a turn that did not end.
 *
 * Every arm below is the same sequence: the stream stops short of `turn.end`,
 * the screen reloads, and what the reload brings back decides the recovery.
 * `reloadBrings` plays the server's half in Inertia's order — the page is
 * swapped, then `onSuccess`, then `onFinish`.
 */

function answer(text: string) {
    fireEvent.change(screen.getByPlaceholderText('Answer in your own words…'), {
        target: { value: text },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Send' }));
}

function composer(): string {
    return (
        screen.getByPlaceholderText(
            'Answer in your own words…',
        ) as HTMLTextAreaElement
    ).value;
}

function reply(text: string): (onEvent: (event: TurnEvent) => void) => void {
    return (onEvent) => {
        onEvent({
            name: 'turn.start',
            data: { turn_id: 't', kind: 'interview' },
        });
        onEvent({ name: 'turn.delta', data: { turn_id: 't', text } });
    };
}

/** `turns` is what the server holds; null is a reload that never arrived. */
async function reloadBrings(turns: Turn[] | null, swap?: () => void) {
    await waitFor(() => expect(reload).toHaveBeenCalledTimes(1));

    const visit = reload.mock.calls[0][0];

    act(() => {
        if (turns !== null) {
            swap?.();
            visit.onSuccess({ props: { turns } });
        }

        visit.onFinish();
    });
}

test('10.1, 10.3 — a failed answer reloads, and the answer is back in the box to send once', async () => {
    stream = () => Promise.reject(new Error('offline'));
    render(<InterviewScreen {...props('knowledge', ASKED)} />);

    answer('Hola, y nada más.');
    await reloadBrings(null);

    expect(composer()).toBe('Hola, y nada más.');
    expect(screen.getByText(/Your answer is back in the box/)).toBeDefined();

    stream = () => new Promise<void>(() => {});
    fireEvent.click(screen.getByRole('button', { name: 'Send' }));

    expect(requested).toEqual([
        '/courses/7/interviews/knowledge/answers',
        '/courses/7/interviews/knowledge/answers',
    ]);
});

test('10.1, 10.2 — an answer the course has moved past lands on its current screen, saying nothing about the tutor', async () => {
    stream = () => Promise.reject(new Error('Stream refused with 200.'));
    const { rerender } = render(
        <InterviewScreen {...props('knowledge', ASKED)} />,
    );

    answer('One more thing.');
    await reloadBrings([], () => {
        stream = () => new Promise<void>(() => {});
        rerender(<InterviewScreen {...props('goals')} />);
    });

    expect(screen.getByText('What you want out of this')).toBeDefined();
    expect(screen.queryByText(/tutor/i, { selector: '.text-wine' })).toBeNull();
    expect(composer()).toBe('');
});

test('10.4 — a reply cut mid-stream stays on screen when the server never wrote it', async () => {
    stream = async (onEvent) => {
        reply('Muy bien. Now tell me')(onEvent);

        throw new Error('cut');
    };
    render(<InterviewScreen {...props('knowledge', ASKED)} />);

    answer('Hola.');
    await reloadBrings(ASKED);

    expect(screen.getByText('Muy bien. Now tell me')).toBeDefined();
    expect(composer()).toBe('Hola.');
});

test('10.4, 10.5 — a reply written before its write-up failed is replaced by the server’s copy, and the copy names Done', async () => {
    stream = async (onEvent) => {
        reply('That is everything I needed.')(onEvent);
        onEvent({
            name: 'stream.error',
            data: { code: 'interview_stream_failed', retryable: true },
        });
    };
    const { rerender } = render(
        <InterviewScreen {...props('knowledge', ASKED)} />,
    );
    const written: Turn[] = [
        ...ASKED,
        { id: 2, speaker: 'student', content: 'Nothing more.' },
        { id: 3, speaker: 'tutor', content: 'That is everything I needed.' },
    ];

    answer('Nothing more.');
    await reloadBrings(written, () =>
        rerender(<InterviewScreen {...props('knowledge', written)} />),
    );

    // One copy of the reply: the server's, with the live fragment gone.
    expect(screen.getByText('That is everything I needed.')).toBeDefined();
    expect(screen.getByText(/press Done to try again/)).toBeDefined();
    expect(composer()).toBe('');
});

test('10.5 — an opening that never arrived offers Try again, and Send waits for a question', async () => {
    stream = () => Promise.reject(new Error('offline'));
    render(<InterviewScreen {...props('knowledge')} />);

    await reloadBrings([]);

    expect(screen.getByText('The tutor could not be reached.')).toBeDefined();
    expect(
        screen.getByRole('button', { name: 'Send' }).hasAttribute('disabled'),
    ).toBe(true);

    stream = () => new Promise<void>(() => {});
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

    expect(requested).toEqual([
        '/courses/7/interviews/knowledge/stream',
        '/courses/7/interviews/knowledge/stream',
    ]);
});

/**
 * Spec 11.6 — while the write-up is on the queue the screen says what is being
 * written, takes nothing more, and looks again until the course moves.
 */
test('11.6 — a write-up on the queue disables Done, the box and Send, and waits', () => {
    vi.useFakeTimers();

    try {
        render(
            <InterviewScreen
                {...props('knowledge', ASKED, {
                    writing_up: true,
                    write_up_failure: null,
                })}
            />,
        );

        for (const control of [
            screen.getByRole('button', { name: 'Writing up what you know…' }),
            screen.getByRole('button', { name: 'Send' }),
            screen.getByPlaceholderText('Answer in your own words…'),
        ]) {
            expect(control.hasAttribute('disabled')).toBe(true);
        }

        act(() => vi.advanceTimersByTime(5000));

        expect(reload).toHaveBeenCalledTimes(1);
    } finally {
        vi.useRealTimers();
    }
});

test('11.4, 10.5 — a write-up that failed on the queue says so, and Done is pressable again', () => {
    render(
        <InterviewScreen
            {...props('knowledge', ASKED, {
                writing_up: false,
                write_up_failure:
                    'That could not be written up just now. Nothing is lost — press Done to try again.',
            })}
        />,
    );

    expect(screen.getByText(/press Done to try again/)).toBeDefined();

    fireEvent.click(screen.getByRole('button', { name: 'Done — write it up' }));

    expect(post).toHaveBeenCalledTimes(1);
});
