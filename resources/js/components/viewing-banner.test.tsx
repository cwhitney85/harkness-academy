import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import type { ReactNode } from 'react';

import AssessmentShell from '@/components/assessment-shell';
import PipelineShell from '@/components/pipeline-shell';
import StudyShell from '@/components/study-shell';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';

/**
 * Spec 13.4.4 — while an admin views as a student, a banner naming the
 * student and the mode, with Exit, stands in every student shell.
 */

let viewingAs: { mode: 'preview' | 'act_as'; reason: string | null } | null =
    null;

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        as,
        ...rest
    }: {
        href: string | { url: string };
        as?: string;
        children?: ReactNode;
    }) => (
        <a
            href={typeof href === 'string' ? href : href.url}
            data-as={as}
            {...rest}
        >
            {children}
        </a>
    ),
    Form: ({ children }: { children: ReactNode }) => <form>{children}</form>,
    usePage: () => ({
        url: '/dashboard',
        props: {
            name: 'Harkness Academy',
            sidebarOpen: true,
            auth: { user: { name: 'Maria Alvarez', plan: 'standard' } },
            viewingAs,
        },
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

afterEach(cleanup);

const position = { number: 1, total: 2 };

const shells: [string, () => ReactNode][] = [
    [
        'StudyShell',
        () => (
            <StudyShell
                course="Algebra"
                title="Variables"
                lesson={position}
                section={position}
                rail={null}
            >
                <p>page</p>
            </StudyShell>
        ),
    ],
    [
        'PipelineShell',
        () => (
            <PipelineShell step="knowledge" course="Algebra">
                <p>page</p>
            </PipelineShell>
        ),
    ],
    [
        'AssessmentShell',
        () => (
            <AssessmentShell
                course="Algebra"
                heading="Homework"
                title="Variables"
                rows={[]}
            >
                <p>page</p>
            </AssessmentShell>
        ),
    ],
    [
        'AppLayout',
        // Wrapped as app.tsx wraps every page.
        () => (
            <TooltipProvider>
                <AppSidebarLayout>
                    <p>page</p>
                </AppSidebarLayout>
            </TooltipProvider>
        ),
    ],
];

test.each(shells)('%s carries the preview banner', (_, shell) => {
    viewingAs = { mode: 'preview', reason: null };
    render(shell());

    const banner = screen.getByRole('status');
    expect(banner.textContent).toContain('Previewing');
    expect(banner.textContent).toContain('Maria Alvarez');
    expect(screen.getByText('Exit').getAttribute('href')).toBe(
        '/admin/view-as',
    );
    expect(screen.getByText('Step up to act as')).toBeTruthy();
});

test.each(shells)('%s carries the act-as banner and its reason', (_, shell) => {
    viewingAs = { mode: 'act_as', reason: 'Reproducing a bug' };
    render(shell());

    const banner = screen.getByRole('status');
    expect(banner.textContent).toContain('Acting as');
    expect(banner.textContent).toContain('Reproducing a bug');
    expect(screen.queryByText('Step up to act as')).toBeNull();
});

test.each(shells)('%s carries no banner for a student', (_, shell) => {
    viewingAs = null;
    render(shell());

    expect(screen.queryByText('Exit')).toBeNull();
});
