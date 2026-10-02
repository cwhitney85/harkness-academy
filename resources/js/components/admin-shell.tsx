import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { RailHead } from '@/components/rail';
import { home, log, models, prompts } from '@/routes/admin';
import { index as catalog } from '@/routes/admin/catalog';
import { index as courses } from '@/routes/admin/courses';
import { overview as usage } from '@/routes/admin/usage';

/**
 * The frame every admin screen is set in — the fourth shell (spec 13.3,
 * ledger D61), drawn on the canvas as `AdminLog`.
 *
 * It keeps the rail every other shell has and swaps the lesson list for the
 * admin's screens, the current one on the wine band a current lesson sits on.
 * The column is a working surface rather than a reading one: no measure, and
 * records set as a `.ledger`.
 *
 * Invariant: `NAV` holds exactly the screens that have landed (13.3.2). The
 * canvas draws Users, Courses, Catalog, Prompts, Models, Usage and Log; each
 * package adds its entry when its screen lands.
 *
 * A page set in this shell returns no layout from the switch in `app.tsx`.
 */

export const NAV = [
    { id: 'users', title: 'Users', href: home() },
    { id: 'courses', title: 'Courses', href: courses() },
    { id: 'catalog', title: 'Catalog', href: catalog() },
    { id: 'prompts', title: 'Prompts', href: prompts() },
    { id: 'models', title: 'Models', href: models() },
    { id: 'usage', title: 'Usage', href: usage() },
    { id: 'log', title: 'Log', href: log() },
] as const;

export type AdminScreen = (typeof NAV)[number]['id'];

export default function AdminShell({
    current,
    title,
    children,
}: {
    /** The nav entry this screen is. */
    current: AdminScreen;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh w-full flex-col bg-ground text-ink md:h-dvh md:flex-row md:overflow-hidden">
            <aside className="flex flex-none flex-col gap-6 bg-rail-green px-[22px] py-6 text-ground/90 md:w-[252px] md:overflow-y-auto">
                <RailHead subtitle="Administration" />

                <nav className="flex flex-col gap-2.5">
                    <div className="label border-b border-field-green pb-1.5 text-gold">
                        Admin
                    </div>
                    {NAV.map((entry) => (
                        <Link
                            key={entry.id}
                            href={entry.href}
                            aria-current={
                                entry.id === current ? 'page' : undefined
                            }
                            className={[
                                'flex min-h-11 items-center text-[15.5px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                                entry.id === current
                                    ? '-mx-[22px] bg-wine px-[22px] text-ground'
                                    : 'text-ground/70 hover:text-ground',
                            ].join(' ')}
                        >
                            {entry.title}
                        </Link>
                    ))}
                </nav>
            </aside>

            <main className="flex min-w-0 grow flex-col">
                <div className="flex flex-none items-baseline gap-4 border-b border-hairline px-5 pt-[22px] pb-3.5 md:px-10">
                    <span className="label">Administration</span>
                    <span className="grow" />
                    <span className="label">{title}</span>
                </div>

                <div className="grow overflow-y-auto px-5 pt-9 pb-12 md:px-10">
                    <h1 className="display mb-1.5 text-[43px] leading-[1.07]">
                        {title}
                    </h1>
                    {children}
                </div>
            </main>
        </div>
    );
}
