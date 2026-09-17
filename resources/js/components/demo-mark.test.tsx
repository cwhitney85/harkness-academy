import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import DemoMark from '@/components/demo-mark';

/** Spec 12.1.6 — the marker is a demo visitor's, and nobody else's. */

let plan: string | null = null;

vi.mock('@inertiajs/react', () => ({
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
    usePage: () => ({
        props: { auth: { user: plan === null ? null : { plan } } },
    }),
}));

afterEach(cleanup);

test('a demo visitor sees the marker, and it goes to signing up', () => {
    plan = 'demo';
    render(<DemoMark />);

    const link = screen.getByRole('link', { name: 'Demo — sign up' });
    expect(link.getAttribute('href')).toBe('/demo/account');
});

test('a full account sees no marker', () => {
    plan = 'standard';
    const { container } = render(<DemoMark />);

    expect(container.innerHTML).toBe('');
});
