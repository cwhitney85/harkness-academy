import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import LessonScreen from '@/pages/lessons/show';
import type { LessonScreen as Props } from '@/types/lesson';

/**
 * Spec 8.8.11 — a section whose stream failed is asked for again, never walked
 * past.
 *
 * Found on the first live course (trap T10 in docs/lessons.md): nginx cut the section stream,
 * the screen said so, and the only button on it advanced to the next section.
 * The rail disables an untaught section, so the blank one could never be
 * reached again — one press stranded it for the life of the lesson.
 *
 * The button is the retry. Restore the old three-branch `onClick` in show.tsx
 * and the second test below fails on the URL it requests, which is the whole
 * of its point.
 */

const requested: string[] = [];
let failNext = false;

vi.mock('@/lib/turn-stream', () => ({
    readTurnStream: (url: string) => {
        requested.push(url);

        return failNext
            ? Promise.reject(new Error('cut'))
            : new Promise<void>(() => {});
    },
}));

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
    router: { reload: vi.fn(), visit: vi.fn() },
    usePage: () => ({ props: { name: 'Harkness Academy' } }),
}));

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
    failNext = false;
});

/** A two-section lesson, resuming at `resumeAt`, with `taught` sections filed. */
function props(resumeAt: number, taught: number[] = []): Props {
    return {
        lesson: {
            id: 19,
            position: 1,
            title: 'What Aristotle was arguing against',
            objective: 'Place the Physics against its predecessors.',
            status: 'in_progress',
            section_index: resumeAt,
            sections_total: 2,
            completed_at: null,
        },
        resume_at: resumeAt,
        sections: [1, 2].map((index) => ({
            index,
            title: `Section ${index}`,
            concepts: [],
            taught: taught.includes(index),
            stream: `/lessons/19/sections/${index}/stream`,
        })),
        transcript: taught.map((index) => ({
            message_id: index,
            turn_id: `turn-${index}`,
            kind: 'section' as const,
            speaker: 'tutor' as const,
            section_index: index,
            content: `Section ${index} as it was taught.`,
        })),
        checkpoints: [],
        limits: { question_characters: 2000, highlight_characters: 500 },
        course: {
            title: 'Aristotle',
            lessons: [
                {
                    position: 1,
                    title: 'Lesson one',
                    completed: false,
                    url: null,
                },
            ],
        },
        urls: {
            question: '/lessons/19/questions',
            explanation: '/lessons/19/explanations',
            complete: '/lessons/19/complete',
            refresher: '/lessons/19/refreshers',
            homework: '/lessons/19/homework',
            exam: '/courses/3/exam',
        },
    };
}

test('a taught section offers Continue, which moves to the next one', () => {
    render(<LessonScreen {...props(1, [1])} />);

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

    expect(requested).toEqual(['/lessons/19/sections/2/stream']);
});

test('a section whose stream failed offers Try again, and asks for itself', async () => {
    failNext = true;

    render(<LessonScreen {...props(1)} />);

    // The section opened on arrival and the stream rejected.
    expect(requested).toEqual(['/lessons/19/sections/1/stream']);

    const retry = await screen.findByRole('button', { name: 'Try again' });

    fireEvent.click(retry);

    // Section 1 again — never section 2, which would strand section 1 behind
    // a rail entry that is disabled until it is taught.
    expect(requested).toEqual([
        '/lessons/19/sections/1/stream',
        '/lessons/19/sections/1/stream',
    ]);
});
