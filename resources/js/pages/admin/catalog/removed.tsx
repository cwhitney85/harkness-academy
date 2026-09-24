import { Head } from '@inertiajs/react';
import { useState } from 'react';

import AdminShell from '@/components/admin-shell';
import CatalogViews from '@/components/catalog-views';
import ReasonForm from '@/components/reason-form';
import { restore } from '@/routes/admin/catalog';

/**
 * Admin → Catalog → Removed (spec 16.5.1, ledger D57): every removal still
 * in effect, newest first, a course's included. Restore brings back exactly
 * what the removal hid, and is refused -- saying why on the row -- while a
 * parent is removed or another course holds the subtopic (16.2.6).
 */

type Row = {
    id: number;
    kind: 'module' | 'subtopic' | 'course';
    title: string;
    context: string | null;
    when: string;
    by: string;
    reason: string;
    courses: number;
};

export default function CatalogRemoved({ removals }: { removals: Row[] }) {
    const [restoring, setRestoring] = useState<number | null>(null);

    return (
        <AdminShell current="catalog" title="Catalog">
            <Head title="Removed" />
            <CatalogViews current="removed" />

            <p className="mb-[26px] max-w-[62ch] text-muted-ink">
                What is removed, and why. Restoring one brings back exactly what
                it hid; something removed on its own before stays removed.
                Restoring asks why, and is logged.
            </p>

            {removals.length === 0 ? (
                <p className="text-muted-ink">Nothing is removed.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger min-w-max">
                        <thead>
                            <tr>
                                <th>Removed</th>
                                <th>When</th>
                                <th>By</th>
                                <th>Why</th>
                                <th className="text-right">Courses hidden</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {removals.map((row) => (
                                <tr key={row.id}>
                                    {restoring === row.id ? (
                                        <td colSpan={6}>
                                            <ReasonForm
                                                form={restore.form(row.id)}
                                                verb="Restore"
                                                consequence={`Brings back the ${row.kind} ${row.title}${row.courses > 0 && row.kind !== 'course' ? ` and the ${row.courses} courses it hid` : ''}.`}
                                                onDone={() =>
                                                    setRestoring(null)
                                                }
                                            />
                                        </td>
                                    ) : (
                                        <>
                                            <td>
                                                <span className="label mr-2 text-muted-ink">
                                                    {row.kind}
                                                </span>
                                                {row.title}
                                                {row.context && (
                                                    <span className="block text-[13px] text-muted-ink">
                                                        {row.context}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {row.when}
                                            </td>
                                            <td>{row.by}</td>
                                            <td className="max-w-[36ch] whitespace-normal">
                                                {row.reason}
                                            </td>
                                            <td className="text-right">
                                                {row.courses}
                                            </td>
                                            <td className="text-right">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setRestoring(row.id)
                                                    }
                                                    className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                                >
                                                    Restore
                                                </button>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminShell>
    );
}
