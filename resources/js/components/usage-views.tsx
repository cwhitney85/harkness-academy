import { router } from '@inertiajs/react';

import { Tabs } from '@/components/admin-controls';
import { events, overview, performance } from '@/routes/admin/usage';

/**
 * The views under Admin → Usage (spec 17.3), and the date range and act-as
 * toggle they share (17.3.3). Switching view keeps the range; changing the
 * range keeps the view's own filters.
 */

export type Range = { from: string; to: string; acting: boolean };

const ROUTES = { overview, performance, events };

export default function UsageViews({
    current,
    range,
    filters = {},
}: {
    current: keyof typeof ROUTES;
    range: Range;
    filters?: Record<string, string | number | null>;
}) {
    const query = (r: Range) => ({
        from: r.from,
        to: r.to,
        acting: r.acting ? 1 : null,
    });

    const change = (next: Partial<Range>) =>
        router.get(
            ROUTES[current]({
                query: { ...filters, ...query({ ...range, ...next }) },
            }),
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Tabs
                current={current}
                views={[
                    {
                        id: 'overview',
                        title: 'Overview',
                        href: overview({ query: query(range) }),
                    },
                    {
                        id: 'performance',
                        title: 'Performance',
                        href: performance({ query: query(range) }),
                    },
                    {
                        id: 'events',
                        title: 'Events',
                        href: events({ query: query(range) }),
                    },
                ]}
            />

            <div className="mb-7 flex flex-wrap items-end gap-[18px]">
                {(['from', 'to'] as const).map((key) => (
                    <label key={key} className="flex flex-col gap-[5px]">
                        <span className="label">{key}</span>
                        <input
                            type="date"
                            value={range[key]}
                            onChange={(event) =>
                                event.target.value &&
                                change({ [key]: event.target.value })
                            }
                            className="min-h-11 border border-hairline bg-surface px-3 focus-visible:outline-2 focus-visible:outline-brass"
                        />
                    </label>
                ))}
                <label className="flex min-h-11 items-center gap-2">
                    <input
                        type="checkbox"
                        checked={range.acting}
                        onChange={(event) =>
                            change({ acting: event.target.checked })
                        }
                        className="size-4 accent-wine"
                    />
                    <span>Count calls an admin made acting as a student</span>
                </label>
            </div>
        </>
    );
}
