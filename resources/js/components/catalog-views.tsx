import { Link } from '@inertiajs/react';

import { demand, index, refusals, removed } from '@/routes/admin/catalog';

/**
 * The views under Admin → Catalog (spec 16.5, 16.1.5, 16.1.6).
 */

const VIEWS = [
    { id: 'modules', title: 'Modules', href: index() },
    { id: 'removed', title: 'Removed', href: removed() },
    { id: 'demand', title: 'Demand', href: demand() },
    { id: 'refusals', title: 'Refusals', href: refusals() },
] as const;

export default function CatalogViews({
    current,
}: {
    current: (typeof VIEWS)[number]['id'];
}) {
    return (
        <nav className="mb-[26px] flex gap-6 border-b border-hairline">
            {VIEWS.map((view) => (
                <Link
                    key={view.id}
                    href={view.href}
                    aria-current={view.id === current ? 'page' : undefined}
                    className={[
                        'label -mb-px flex min-h-11 items-center border-b-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                        view.id === current
                            ? 'border-wine text-wine'
                            : 'border-transparent text-muted-ink hover:text-ink',
                    ].join(' ')}
                >
                    {view.title}
                </Link>
            ))}
        </nav>
    );
}
