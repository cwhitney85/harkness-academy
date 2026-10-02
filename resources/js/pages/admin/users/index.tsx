import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Filter, Pager, PreviewButton } from '@/components/admin-controls';
import type { Page } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import { money } from '@/lib/utils';
import { home } from '@/routes/admin';
import { show } from '@/routes/admin/users';

/**
 * Admin → Users (spec 17.1.1): every account with its plan, role, courses
 * and what it has cost, searchable and filtered by plan. Preview starts here
 * (17.1.4); an admin cannot be viewed as (13.4.7), nor can a deleted account.
 */

type User = {
    id: number;
    name: string;
    email: string;
    plan: string;
    role: string;
    joined: string | null;
    deleted: boolean;
    courses: number;
    cost: number;
};

type Filters = { search: string | null; plan: string | null };

export default function AdminUsers({
    users,
    filters,
    plans,
}: {
    users: Page<User>;
    filters: Filters;
    plans: string[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const filter = (next: Partial<Filters>) =>
        router.get(
            home({ query: { ...filters, ...next } }),
            {},
            { preserveScroll: true },
        );

    return (
        <AdminShell current="users" title="Users">
            <Head title="Users" />
            <p className="mb-[26px] text-muted-ink">
                Cost leaves out calls an admin made acting as the student.
                Preview shows a student’s screens read-only; from there you can
                step up to act as them, with a reason.
            </p>

            <div className="mb-7 flex flex-wrap items-end gap-[18px]">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        filter({ search: search.trim() || null });
                    }}
                    className="flex flex-col gap-[5px]"
                >
                    <label htmlFor="search" className="label">
                        Search
                    </label>
                    <input
                        id="search"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Name or email"
                        className="min-h-11 min-w-[240px] border border-hairline bg-surface px-3 focus-visible:outline-2 focus-visible:outline-brass"
                    />
                </form>
                <Filter
                    label="Plan"
                    value={filters.plan ?? ''}
                    empty="Every plan"
                    options={plans.map((plan) => [plan, plan])}
                    onChange={(plan) => filter({ plan: plan || null })}
                />
            </div>

            {users.data.length === 0 ? (
                <p className="text-muted-ink">No one matches.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th className="text-right">Courses</th>
                                <th className="text-right">Cost</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="whitespace-nowrap">
                                        <Link
                                            href={show(user.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {user.name}
                                        </Link>
                                        {user.deleted && (
                                            <span className="label ml-2 text-muted-ink">
                                                Deleted
                                            </span>
                                        )}
                                    </td>
                                    <td>{user.email}</td>
                                    <td>{user.plan}</td>
                                    <td>{user.role}</td>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {user.joined}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {user.courses}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(user.cost)}
                                    </td>
                                    <td className="text-right">
                                        {user.role === 'student' &&
                                            !user.deleted && (
                                                <PreviewButton id={user.id} />
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pager page={users} />
        </AdminShell>
    );
}
