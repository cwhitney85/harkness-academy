import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import CreateCourse from '@/pages/courses/create';

/**
 * Spec 16.4.2 — with two attempts set aside, the return prompt asks which to
 * continue, or to start from scratch (ledger D64).
 *
 * Each attempt's button posts to that attempt's own resume URL; starting over
 * resubmits the form with `start_over`, and the number it promises is the one
 * the server sent, not one worked out from whichever attempt is shown.
 */

const post = vi.fn();
const formPost = vi.fn();
let submitted: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: { href: string; children?: ReactNode }) => (
        <a href={href}>{children}</a>
    ),
    router: { post: (...args: unknown[]) => post(...args) },
    useForm: (initial: Record<string, unknown>) => {
        let transform = (data: Record<string, unknown>) => data;

        return {
            data: initial,
            processing: false,
            errors: {},
            setData: () => {},
            transform: (fn: typeof transform) => (transform = fn),
            post: (...args: unknown[]) => {
                submitted = transform(initial);
                formPost(...args);
            },
        };
    },
    usePage: () => ({
        props: { name: 'Harkness Academy', auth: { user: null } },
    }),
}));

vi.hoisted(() => {
    window.matchMedia = ((query: string) => ({
        matches: false,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    })) as unknown as typeof window.matchMedia;
});

afterEach(() => {
    cleanup();
    post.mockClear();
    formPost.mockClear();
    submitted = {};
});

const TWO_SET_ASIDE = {
    subtopic_title: 'Algebra',
    level: 'Introductory',
    next_attempt: 3,
    attempts: [
        {
            course_id: 12,
            attempt: 2,
            standing: 'A syllabus waiting for you',
            set_aside_on: 'Mon, Sep 21, 2026',
            resume_url: '/courses/12/resume',
        },
        {
            course_id: 7,
            attempt: 1,
            standing: 'Being taught',
            set_aside_on: 'Tue, Sep 1, 2026',
            resume_url: '/courses/7/resume',
        },
    ],
};

function renderWith(resumable: typeof TWO_SET_ASIDE) {
    render(
        <CreateCourse
            modules={[]}
            levels={[]}
            resumable={resumable}
            occupied={null}
            refused={null}
        />,
    );
}

test('two attempts set aside: asks which to continue, and each resumes its own course', () => {
    renderWith(TWO_SET_ASIDE);

    expect(screen.getByText(/which would you like to continue/i)).toBeTruthy();
    expect(
        screen.getByText(/Being taught · set aside on Tue, Sep 1/),
    ).toBeTruthy();
    expect(screen.queryByText('Pick up where I left off')).toBeNull();

    fireEvent.click(screen.getByText('Continue attempt 1'));
    expect(post).toHaveBeenLastCalledWith(
        '/courses/7/resume',
        {},
        expect.anything(),
    );

    fireEvent.click(screen.getByText('Continue attempt 2'));
    expect(post).toHaveBeenLastCalledWith(
        '/courses/12/resume',
        {},
        expect.anything(),
    );
});

test('two attempts set aside: starting from scratch is still offered, as the next attempt', () => {
    renderWith(TWO_SET_ASIDE);

    expect(screen.getByText(/the new one is attempt 3/)).toBeTruthy();

    fireEvent.click(screen.getByText('Start from scratch'));

    expect(formPost).toHaveBeenCalledOnce();
    expect(submitted.start_over).toBe(true);
    expect(post).not.toHaveBeenCalled();
});

test('one attempt set aside: the question is the one it always was', () => {
    renderWith({
        ...TWO_SET_ASIDE,
        next_attempt: 2,
        attempts: [TWO_SET_ASIDE.attempts[1]],
    });

    expect(
        screen.getByText(/pick up where you left off, or start from scratch/i),
    ).toBeTruthy();

    fireEvent.click(screen.getByText('Pick up where I left off'));
    expect(post).toHaveBeenLastCalledWith(
        '/courses/7/resume',
        {},
        expect.anything(),
    );
});
