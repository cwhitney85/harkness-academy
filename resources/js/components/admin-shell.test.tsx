import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import AdminShell from '@/components/admin-shell';

/**
 * Spec 13.3.2 — the admin nav holds exactly the screens that have landed.
 * Package 13 lands Log; each later package adds its entry here.
 */

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
        props: {
            name: 'Harkness Academy',
            auth: { user: { plan: 'standard' } },
        },
    }),
}));

afterEach(cleanup);

test('the nav shows exactly the landed screens', () => {
    render(
        <AdminShell current="log" title="Log">
            <p>body</p>
        </AdminShell>,
    );

    const nav = within(screen.getByRole('navigation'));

    expect(nav.getAllByRole('link').map((link) => link.textContent)).toEqual([
        'Log',
    ]);
    expect(
        nav.getByRole('link', { name: 'Log' }).getAttribute('aria-current'),
    ).toBe('page');
});
