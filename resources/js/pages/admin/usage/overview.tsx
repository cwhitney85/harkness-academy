import { Head } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';
import UsageViews from '@/components/usage-views';
import type { Range } from '@/components/usage-views';
import { money } from '@/lib/utils';

/**
 * Admin → Usage → Overview (spec 17.3.1): what calls cost over the range, by
 * day, by feature and by the plan the student was on.
 */

type Row = { key: string; calls: number; cost: number };

export default function UsageOverview({
    range,
    total,
    days,
    features,
    plans,
}: {
    range: Range;
    total: number;
    days: Row[];
    features: Row[];
    plans: Row[];
}) {
    return (
        <AdminShell current="usage" title="Usage">
            <Head title="Usage" />
            <UsageViews current="overview" range={range} />

            <p className="display mb-9 text-[26px]">
                {money.format(total)}
                <span className="label ml-3 text-muted-ink">
                    {range.from} to {range.to}
                </span>
            </p>

            <div className="grid gap-x-10 gap-y-9 lg:grid-cols-3">
                <Breakdown title="By day" rows={days} />
                <Breakdown title="By feature" rows={features} />
                <Breakdown
                    title="By plan"
                    rows={plans}
                    note="Calls from before usage rows kept the plan are unrecorded."
                />
            </div>
        </AdminShell>
    );
}

function Breakdown({
    title,
    rows,
    note,
}: {
    title: string;
    rows: Row[];
    note?: string;
}) {
    return (
        <section>
            <h2 className="label mb-3">{title}</h2>
            {rows.length === 0 ? (
                <p className="text-muted-ink">No calls.</p>
            ) : (
                <table className="ledger">
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.key}>
                                <td>{row.key}</td>
                                <td className="text-right text-muted-ink tabular-nums">
                                    {row.calls}
                                </td>
                                <td className="text-right tabular-nums">
                                    {money.format(row.cost)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {note && <p className="mt-2 text-[14px] text-muted-ink">{note}</p>}
        </section>
    );
}
