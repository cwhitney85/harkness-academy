import { Head, router } from '@inertiajs/react';

import { Filter, Pager } from '@/components/admin-controls';
import type { Page } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import { log } from '@/routes/admin';

/**
 * Admin → Log (spec 13.2.3): every admin write, newest first, filtered by
 * actor, action and subject. As drawn on `AdminLog`.
 */

type Row = {
    id: number;
    when: string;
    actor: string;
    action: string;
    subject: { type: string; name: string } | null;
    reason: string | null;
};

type Filters = {
    actor: number | null;
    action: string | null;
    subject: string | null;
};

/** `App\Models\User` reads as `User`. */
const basename = (type: string) => type.split('\\').pop() ?? type;

export default function AdminLog({
    actions,
    filters,
    options,
}: {
    actions: Page<Row>;
    filters: Filters;
    options: {
        actors: { id: number; name: string }[];
        actions: string[];
        subjects: string[];
    };
}) {
    const filter = (key: keyof Filters, value: string) =>
        router.get(
            log({ query: { ...filters, [key]: value === '' ? null : value } }),
            {},
            { preserveScroll: true },
        );

    return (
        <AdminShell current="log" title="Log">
            <Head title="Log" />
            <p className="mb-[26px] text-muted-ink">
                Every admin write, newest first. Nothing here can be edited or
                removed.
            </p>

            <div className="mb-7 flex flex-wrap items-end gap-[18px]">
                <Filter
                    label="Actor"
                    value={filters.actor?.toString() ?? ''}
                    empty="Everyone"
                    options={options.actors.map((actor) => [
                        actor.id.toString(),
                        actor.name,
                    ])}
                    onChange={(value) => filter('actor', value)}
                />
                <Filter
                    label="Action"
                    value={filters.action ?? ''}
                    empty="Every action"
                    options={options.actions.map((action) => [action, action])}
                    onChange={(value) => filter('action', value)}
                />
                <Filter
                    label="Subject"
                    value={filters.subject ?? ''}
                    empty="Everything"
                    options={options.subjects.map((type) => [
                        type,
                        basename(type),
                    ])}
                    onChange={(value) => filter('subject', value)}
                />
            </div>

            {actions.data.length === 0 ? (
                <p className="text-muted-ink">No actions match.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Subject</th>
                                <th className="w-[34%]">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            {actions.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {row.when}
                                    </td>
                                    <td>{row.actor}</td>
                                    <td className="text-[13px] tracking-[0.08em]">
                                        {row.action}
                                    </td>
                                    <td>
                                        {row.subject
                                            ? `${basename(row.subject.type)} · ${row.subject.name}`
                                            : '—'}
                                    </td>
                                    <td>
                                        {row.reason ? (
                                            <em>{row.reason}</em>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pager page={actions} newer="Newer" older="Older" />
        </AdminShell>
    );
}
