import { cleanup, render, screen } from '@testing-library/react';
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

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { reload: vi.fn(), post: vi.fn() },
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
