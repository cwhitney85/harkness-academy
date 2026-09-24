import { Head } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';
import UsageViews from '@/components/usage-views';
import type { Range } from '@/components/usage-views';
import { money } from '@/lib/utils';

/**
 * Admin → Usage → Performance (spec 17.3.2): how each task — each prompt —
 * does on each model that has served it.
 */

type Row = {
    task: string;
    model: string;
    tier: string;
    calls: number;
    median: number | null;
    p95: number | null;
    input: number | null;
    output: number | null;
    cache_share: number | null;
    cost_per_call: number;
    failure_rate: number;
    failover_rate: number;
};

const percent = new Intl.NumberFormat('en-US', {
    style: 'percent',
    maximumFractionDigits: 1,
});

const seconds = (ms: number | null) =>
    ms === null ? '—' : `${(ms / 1000).toFixed(1)} s`;

export default function UsagePerformance({
    range,
    rows,
}: {
    range: Range;
    rows: Row[];
}) {
    return (
        <AdminShell current="usage" title="Usage">
            <Head title="Performance" />
            <UsageViews current="performance" range={range} />

            <p className="mb-[26px] max-w-[70ch] text-muted-ink">
                Latency and tokens count calls that were answered. Input is
                uncached, cache-read and cache-write tokens together; cache
                share is the part read from cache.
            </p>

            {rows.length === 0 ? (
                <p className="text-muted-ink">No calls in this range.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger min-w-max">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Model</th>
                                <th className="text-right">Calls</th>
                                <th className="text-right">Median</th>
                                <th className="text-right">p95</th>
                                <th className="text-right">In</th>
                                <th className="text-right">Out</th>
                                <th className="text-right">Cached</th>
                                <th className="text-right">Per call</th>
                                <th className="text-right">Failed</th>
                                <th className="text-right">Failed over</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={`${row.task} ${row.model}`}>
                                    <td>
                                        {row.task}
                                        <span className="block text-[13px] text-muted-ink">
                                            {row.tier}
                                        </span>
                                    </td>
                                    <td>{row.model}</td>
                                    <td className="text-right tabular-nums">
                                        {row.calls}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {seconds(row.median)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {seconds(row.p95)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.input ?? '—'}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.output ?? '—'}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.cache_share === null
                                            ? '—'
                                            : percent.format(row.cache_share)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(row.cost_per_call)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {percent.format(row.failure_rate)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {percent.format(row.failover_rate)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminShell>
    );
}
