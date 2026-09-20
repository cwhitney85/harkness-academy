import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import AdminShell from '@/components/admin-shell';
import { update } from '@/routes/admin/models';

/**
 * Admin → Models (spec 14.4): the catalog with what each model has served and
 * cost, the tiers that route calls to it, and the one thing editable here —
 * a model's prices (14.4.2). Set in the admin shell's language, `.ledger`
 * tables as drawn on `AdminLog`.
 */

type Prices = {
    input_per_mtok: number;
    output_per_mtok: number;
    cache_read_per_mtok: number;
    cache_write_per_mtok: number;
};

type Model = {
    id: number;
    name: string;
    provider: string;
    model: string;
    active: boolean;
    prices: Prices;
    calls: number;
    cost: number;
};

type TierRow = {
    name: string;
    model: string;
    backups: string[];
    timeout: number;
};

const PRICE_COLUMNS: [keyof Prices, string][] = [
    ['input_per_mtok', 'Input'],
    ['output_per_mtok', 'Output'],
    ['cache_read_per_mtok', 'Cache read'],
    ['cache_write_per_mtok', 'Cache write'],
];

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

export default function AdminModels({
    models,
    tiers,
    days,
}: {
    models: Model[];
    tiers: TierRow[];
    days: number;
}) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <AdminShell current="models" title="Models">
            <Head title="Models" />
            <p className="mb-[26px] text-muted-ink">
                Every model a call can reach, priced per million tokens, with
                what it served over the last {days} days. A price edit is logged
                and reaches later calls only — a call’s cost is stamped when its
                row is written.
            </p>

            <h2 className="label mb-3">Tiers</h2>
            <div className="mb-9 overflow-x-auto">
                <table className="ledger">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Model</th>
                            <th>Backups</th>
                            <th>Timeout</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tiers.map((tier) => (
                            <tr key={tier.name}>
                                <td className="text-[13px] tracking-[0.08em]">
                                    {tier.name}
                                </td>
                                <td>{tier.model}</td>
                                <td>
                                    {tier.backups.length === 0
                                        ? '—'
                                        : tier.backups.join(' · ')}
                                </td>
                                <td className="whitespace-nowrap text-muted-ink">
                                    {tier.timeout}s
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="label mb-3">Catalog</h2>
            <div className="overflow-x-auto">
                {/* `min-w-max` so opening the editor scrolls the table rather
                    than squeezing every other row's name into a column. */}
                <table className="ledger min-w-max">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Provider</th>
                            {PRICE_COLUMNS.map(([key, title]) => (
                                <th key={key} className="text-right">
                                    {title}
                                </th>
                            ))}
                            <th className="text-right">Calls</th>
                            <th className="text-right">Cost</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {models.map((model) =>
                            editing === model.id ? (
                                <PriceEditor
                                    key={model.id}
                                    model={model}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <tr key={model.id}>
                                    <td className="whitespace-nowrap">
                                        {model.name}
                                        <span className="block text-[13px] text-muted-ink">
                                            {model.model}
                                            {!model.active && ' · inactive'}
                                        </span>
                                    </td>
                                    <td>{model.provider}</td>
                                    {PRICE_COLUMNS.map(([key]) => (
                                        <td
                                            key={key}
                                            className="text-right whitespace-nowrap"
                                        >
                                            {money.format(model.prices[key])}
                                        </td>
                                    ))}
                                    <td className="text-right">
                                        {model.calls}
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        {money.format(model.cost)}
                                    </td>
                                    <td className="text-right">
                                        <button
                                            type="button"
                                            onClick={() => setEditing(model.id)}
                                            className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                        >
                                            Prices
                                        </button>
                                    </td>
                                </tr>
                            ),
                        )}
                    </tbody>
                </table>
            </div>
        </AdminShell>
    );
}

/**
 * The open row: the four prices as fields, saved in one PUT. Mounted only
 * while its row is being edited, so its form starts from that model's prices.
 *
 * It spans every column rather than sitting in the price columns, because a
 * field is wider than the figure it replaces — in the grid it pushed Save off
 * the right edge and squeezed every other row.
 */
function PriceEditor({ model, onDone }: { model: Model; onDone: () => void }) {
    const form = useForm<Prices>(model.prices);

    return (
        <tr>
            <td colSpan={9}>
                <div className="flex flex-wrap items-end gap-x-5 gap-y-3">
                    <span className="min-h-11 self-center whitespace-nowrap">
                        {model.name}
                    </span>

                    {PRICE_COLUMNS.map(([key, title]) => (
                        <label key={key} className="flex flex-col gap-[5px]">
                            <span className="label">{title}</span>
                            <input
                                type="number"
                                step="0.0001"
                                min="0"
                                value={form.data[key]}
                                onChange={(event) =>
                                    form.setData(
                                        key,
                                        event.target.valueAsNumber,
                                    )
                                }
                                aria-invalid={
                                    form.errors[key] ? true : undefined
                                }
                                className="min-h-11 w-[108px] border border-hairline bg-surface px-2 text-right focus-visible:outline-2 focus-visible:outline-brass"
                            />
                            {form.errors[key] && (
                                <span className="max-w-[108px] text-[13px] text-wine">
                                    {form.errors[key]}
                                </span>
                            )}
                        </label>
                    ))}

                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() =>
                            form.put(update(model.id).url, {
                                preserveScroll: true,
                                onSuccess: onDone,
                            })
                        }
                        className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={onDone}
                        className="label min-h-11 text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Cancel
                    </button>
                </div>
            </td>
        </tr>
    );
}
