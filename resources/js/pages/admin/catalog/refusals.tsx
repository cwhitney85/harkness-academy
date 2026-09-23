import { Head, Link } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';
import CatalogViews from '@/components/catalog-views';

/**
 * Admin → Catalog → Refusals (spec 16.1.6): every name the scope check
 * turned away (4.9.7), newest first. The model's reason is shown here and
 * nowhere a student can see it (4.9.6).
 */

type Refusal = {
    id: number;
    when: string;
    typed: string;
    module: string;
    verdict: string;
    suggested: string | null;
    reason: string;
    student: { name: string; email: string; url: string };
};

export default function CatalogRefusals({ refusals }: { refusals: Refusal[] }) {
    return (
        <AdminShell current="catalog" title="Catalog">
            <Head title="Refusals" />
            <CatalogViews current="refusals" />

            <p className="mb-[26px] max-w-[62ch] text-muted-ink">
                Names the scope check turned away before a course existed. The
                reason is the model’s own sentence; the student was shown the
                app’s words instead.
            </p>

            {refusals.length === 0 ? (
                <p className="text-muted-ink">Nothing has been refused.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger min-w-max">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Typed</th>
                                <th>Under</th>
                                <th>Verdict</th>
                                <th>Suggested</th>
                                <th>Reason</th>
                                <th>Student</th>
                            </tr>
                        </thead>
                        <tbody>
                            {refusals.map((refusal) => (
                                <tr key={refusal.id}>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {refusal.when}
                                    </td>
                                    <td>{refusal.typed}</td>
                                    <td>{refusal.module}</td>
                                    <td className="text-[13px] tracking-[0.08em]">
                                        {refusal.verdict}
                                    </td>
                                    <td>{refusal.suggested ?? '—'}</td>
                                    <td className="max-w-[46ch] whitespace-normal">
                                        {refusal.reason}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        <Link
                                            href={refusal.student.url}
                                            className="underline decoration-hairline underline-offset-4 hover:decoration-wine focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                        >
                                            {refusal.student.name}
                                        </Link>
                                        <span className="block text-[13px] text-muted-ink">
                                            {refusal.student.email}
                                        </span>
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
