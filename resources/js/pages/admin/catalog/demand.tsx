import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import AdminShell from '@/components/admin-shell';
import CatalogViews from '@/components/catalog-views';
import { promote } from '@/routes/admin/catalog';

/**
 * Admin → Catalog → Demand (spec 16.1.5, ledger D59): the subtopics students
 * have typed that the picker does not offer, most-typed first. Promote lists
 * one, optionally under a better title; its slug, and so every existing
 * course's memory path, stays as it is.
 */

type Row = {
    id: number;
    title: string;
    slug: string;
    module: string;
    courses: number;
    students: number;
    first_seen: string | null;
    last_seen: string | null;
};

export default function CatalogDemand({ subtopics }: { subtopics: Row[] }) {
    const [promoting, setPromoting] = useState<number | null>(null);

    return (
        <AdminShell current="catalog" title="Catalog">
            <Head title="Demand" />
            <CatalogViews current="demand" />

            <p className="mb-[26px] max-w-[62ch] text-muted-ink">
                Subtopics students typed that the picker does not offer. Promote
                one and it is offered to everyone, and the scope check counts it
                as a starting point. Promotion is logged.
            </p>

            {subtopics.length === 0 ? (
                <p className="text-muted-ink">
                    Nothing is waiting to be listed.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger min-w-max">
                        <thead>
                            <tr>
                                <th>Subtopic</th>
                                <th>Module</th>
                                <th className="text-right">Courses</th>
                                <th className="text-right">Students</th>
                                <th>First seen</th>
                                <th>Last seen</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {subtopics.map((row) =>
                                promoting === row.id ? (
                                    <Promotion
                                        key={row.id}
                                        row={row}
                                        onDone={() => setPromoting(null)}
                                    />
                                ) : (
                                    <tr key={row.id}>
                                        <td>
                                            {row.title}
                                            <span className="block text-[13px] text-muted-ink">
                                                {row.slug}
                                            </span>
                                        </td>
                                        <td>{row.module}</td>
                                        <td className="text-right">
                                            {row.courses}
                                        </td>
                                        <td className="text-right">
                                            {row.students}
                                        </td>
                                        <td className="whitespace-nowrap">
                                            {row.first_seen ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap">
                                            {row.last_seen ?? '—'}
                                        </td>
                                        <td className="text-right">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setPromoting(row.id)
                                                }
                                                className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                            >
                                                Promote
                                            </button>
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminShell>
    );
}

/**
 * The open row: the title the picker will show, starting from the one the
 * student typed. Mounted only while its row is open, so the field starts from
 * that row's title.
 */
function Promotion({ row, onDone }: { row: Row; onDone: () => void }) {
    const form = useForm({ title: row.title });

    return (
        <tr>
            <td colSpan={7}>
                <div className="flex flex-wrap items-end gap-x-5 gap-y-3">
                    <label className="flex flex-col gap-[5px]">
                        <span className="label">
                            Title in the picker, under {row.module}
                        </span>
                        <input
                            type="text"
                            value={form.data.title}
                            maxLength={120}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            aria-invalid={form.errors.title ? true : undefined}
                            className="min-h-11 w-[280px] border border-hairline bg-surface px-2 focus-visible:outline-2 focus-visible:outline-brass"
                        />
                        {form.errors.title && (
                            <span className="text-[13px] text-wine">
                                {form.errors.title}
                            </span>
                        )}
                    </label>

                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() =>
                            form.post(promote(row.id).url, {
                                preserveScroll: true,
                                onSuccess: onDone,
                            })
                        }
                        className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        List it
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
