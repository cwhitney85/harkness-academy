import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';

import InterviewScreen from '@/pages/courses/interview';

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

vi.mock('@/lib/turn-stream', () => ({
    readTurnStream: (url: string) => {
        requested.push(url);

        return new Promise<void>(() => {});
    },
}));

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { reload: vi.fn(), post: (...args: unknown[]) => post(...args) },
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
    post.mockClear();
});

function props(kind: 'knowledge' | 'goals') {
    return {
        course: { id: 7, title: 'Beginner Spanish' },
        kind,
        turns: [],
        may_open: true,
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
 * The write-up is a model call made inside this POST, so the press is followed
 * by a wait a student reads as nothing having happened. A second press while
 * the first is out spends a second call on the same transcript. The button
 * disables and says what is being made until the visit finishes.
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
