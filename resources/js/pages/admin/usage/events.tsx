import { Head, Link, router } from '@inertiajs/react';

import { Filter, Pager } from '@/components/admin-controls';
import type { Page } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import UsageViews from '@/components/usage-views';
import type { Range } from '@/components/usage-views';
import { money } from '@/lib/utils';
import { show as course } from '@/routes/admin/courses';
import { events } from '@/routes/admin/usage';
import { show as user } from '@/routes/admin/users';

/**
 * Admin → Usage → Events (spec 17.3.4): every call, newest first, each
 * linking to its student, its course and the lesson on the course's page.
 */

type Event = {
    id: number;
    when: string;
    user: { id: number; name: string } | null;
    course: number | null;
    lesson: number | null;
    feature: string;
    prompt: string;
    model: string;
    input: number;
    output: number;
    latency: number;
    cost: number;
    outcome: string | null;
    acting: string | null;
};

type Filters = {
    feature: string | null;
    model: string | null;
    outcome: string | null;
};

const link = 'underline-offset-4 hover:underline';

export default function UsageEvents({
    range,
    events: page,
    filters,
    options,
}: {
    range: Range;
    events: Page<Event>;
    filters: Filters;
    options: { features: string[]; models: string[] };
}) {
    const filter = (key: keyof Filters, value: string) =>
        router.get(
            events({
                query: {
                    ...filters,
                    from: range.from,
                    to: range.to,
                    acting: range.acting ? 1 : null,
                    [key]: value === '' ? null : value,
                },
            }),
            {},
            { preserveScroll: true },
        );

    return (
        <AdminShell current="usage" title="Usage">
            <Head title="Events" />
            <UsageViews current="events" range={range} filters={filters} />

            <div className="mb-7 flex flex-wrap items-end gap-[18px]">
                <Filter
                    label="Feature"
                    value={filters.feature ?? ''}
                    empty="Every feature"
                    options={options.features.map((f) => [f, f])}
                    onChange={(value) => filter('feature', value)}
                />
                <Filter
                    label="Model"
                    value={filters.model ?? ''}
                    empty="Every model"
                    options={options.models.map((m) => [m, m])}
                    onChange={(value) => filter('model', value)}
                />
                <Filter
                    label="Outcome"
                    value={filters.outcome ?? ''}
                    empty="Any outcome"
                    options={[
                        ['ok', 'ok'],
                        ['failed', 'failed'],
                        ['failed_over', 'failed_over'],
                    ]}
                    onChange={(value) => filter('outcome', value)}
                />
            </div>

            {page.data.length === 0 ? (
                <p className="text-muted-ink">No calls match.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger min-w-max">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Feature</th>
                                <th>Model</th>
                                <th className="text-right">In</th>
                                <th className="text-right">Out</th>
                                <th className="text-right">Latency</th>
                                <th className="text-right">Cost</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((event) => (
                                <tr key={event.id}>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {event.when}
                                    </td>
                                    <td>
                                        {event.user ? (
                                            <Link
                                                href={user(event.user.id)}
                                                className={link}
                                            >
                                                {event.user.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                        {event.acting && (
                                            <span className="block text-[13px] text-wine">
                                                acting: {event.acting}
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {event.course ? (
                                            <>
                                                <Link
                                                    href={course(event.course)}
                                                    className={link}
                                                >
                                                    #{event.course}
                                                </Link>
                                                {event.lesson && (
                                                    <Link
                                                        href={`${course(event.course).url}#lesson-${event.lesson}`}
                                                        className={`${link} ml-2 text-muted-ink`}
                                                    >
                                                        lesson {event.lesson}
                                                    </Link>
                                                )}
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td>
                                        {event.feature}
                                        <span className="block text-[13px] text-muted-ink">
                                            {event.prompt}
                                        </span>
                                    </td>
                                    <td>{event.model}</td>
                                    <td className="text-right tabular-nums">
                                        {event.input}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {event.output}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {event.latency} ms
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(event.cost)}
                                    </td>
                                    <td>{event.outcome}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pager page={page} newer="Newer" older="Older" />
        </AdminShell>
    );
}
