import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { viewAs } from '@/routes/admin/users';

/**
 * The controls the admin's screens share: a filter that narrows a list
 * through its query string, the pager a simple-paginated list turns with, and
 * the button viewing as a student starts from, and a fact set as a term and
 * its value.
 */

/** A Laravel `simplePaginate()` page as Inertia receives it. */
export type Page<Row> = {
    data: Row[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export function Pager({
    page,
    newer = 'Previous',
    older = 'Next',
}: {
    page: Page<unknown>;
    newer?: string;
    older?: string;
}) {
    return (
        <div className="mt-6 flex gap-6">
            {page.prev_page_url && (
                <Link href={page.prev_page_url} className="label">
                    {newer}
                </Link>
            )}
            {page.next_page_url && (
                <Link href={page.next_page_url} className="label">
                    {older}
                </Link>
            )}
        </div>
    );
}

export function Filter({
    label,
    value,
    empty,
    options,
    onChange,
}: {
    label: string;
    value: string;
    empty: string;
    options: [string, string][];
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex flex-col gap-[5px]">
            <span className="label">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-11 min-w-[190px] border border-hairline bg-surface px-3 focus-visible:outline-2 focus-visible:outline-brass"
            >
                <option value="">{empty}</option>
                {options.map(([key, text]) => (
                    <option key={key} value={key}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
}

/** Starts a read-only view of the student (13.4.2). */
export function PreviewButton({ id }: { id: number }) {
    return (
        <Link
            href={viewAs(id)}
            as="button"
            className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
        >
            Preview
        </Link>
    );
}

/** One term and its value, in a `<dl>`. */
export function Fact({
    term,
    children,
}: {
    term: string;
    children: ReactNode;
}) {
    return (
        <div>
            <dt className="label">{term}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
