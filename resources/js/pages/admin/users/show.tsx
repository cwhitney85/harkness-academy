import { Form, Head, Link } from '@inertiajs/react';

import { Fact, PreviewButton } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import { money } from '@/lib/utils';
import { home } from '@/routes/admin';
import { plan as changePlan } from '@/routes/admin/users';

/**
 * One account (spec 17.1.2): its profile and plan, every course it has had,
 * what it cost by feature, and its memory tree read-only — each version naming
 * the course that wrote it and any admin who was acting as the student
 * (16.3.4). Nothing here edits a memory or the profile (17.1.5).
 */

type Course = {
    id: number;
    title: string;
    module: string;
    level: number;
    attempt: number;
    standing: string;
    removed: boolean;
    created: string;
    cost: number;
};

type Version = {
    version: number;
    course: string;
    admin: string | null;
    when: string;
    content: string;
};

type Document = { path: string; removed: boolean; versions: Version[] };

export default function AdminUser({
    user,
    plans,
    courses,
    cost,
    documents,
}: {
    user: {
        id: number;
        name: string;
        email: string;
        plan: string;
        role: string;
        joined: string | null;
        deleted: string | null;
    };
    plans: string[];
    courses: Course[];
    cost: {
        total: number;
        unattributed: number;
        features: { feature: string; calls: number; cost: number }[];
    };
    documents: Document[];
}) {
    return (
        <AdminShell current="users" title={user.name}>
            <Head title={user.name} />
            <Link
                href={home()}
                className="label mb-6 inline-flex min-h-11 items-center text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
            >
                ← All users
            </Link>

            <dl className="mb-8 flex flex-wrap gap-x-10 gap-y-3">
                <Fact term="Email">{user.email}</Fact>
                <Fact term="Role">{user.role}</Fact>
                <Fact term="Joined">{user.joined ?? '—'}</Fact>
                {user.deleted && (
                    <Fact term="Deleted">{user.deleted}, by the student</Fact>
                )}
                <Fact term="Cost">{money.format(cost.total)}</Fact>
            </dl>

            <div className="mb-11 flex flex-wrap items-end gap-x-5 gap-y-3">
                {user.deleted ? (
                    <Fact term="Plan">{user.plan}</Fact>
                ) : (
                    <Form
                        {...changePlan.form(user.id)}
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-x-5 gap-y-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <label className="flex flex-col gap-[5px]">
                                    <span className="label">Plan</span>
                                    <select
                                        key={user.plan}
                                        name="plan"
                                        defaultValue={user.plan}
                                        className="min-h-11 min-w-[160px] border border-hairline bg-surface px-3 focus-visible:outline-2 focus-visible:outline-brass"
                                    >
                                        {plans.map((plan) => (
                                            <option key={plan} value={plan}>
                                                {plan}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                >
                                    Change plan
                                </button>
                                {errors.plan && (
                                    <p
                                        role="alert"
                                        className="text-[14px] text-wine"
                                    >
                                        {errors.plan}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                )}
                {user.role === 'student' && !user.deleted && (
                    <PreviewButton id={user.id} />
                )}
            </div>

            <h2 className="label mb-3">Courses</h2>
            {courses.length === 0 ? (
                <p className="mb-11 text-muted-ink">No courses.</p>
            ) : (
                <div className="mb-11 overflow-x-auto">
                    <table className="ledger">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Level</th>
                                <th className="text-right">Attempt</th>
                                <th>Standing</th>
                                <th>Created</th>
                                <th className="text-right">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            {courses.map((course) => (
                                <tr key={course.id}>
                                    <td>
                                        {course.title}
                                        <span className="block text-[13px] text-muted-ink">
                                            {course.module}
                                        </span>
                                    </td>
                                    <td>{course.level}</td>
                                    <td className="text-right tabular-nums">
                                        {course.attempt}
                                    </td>
                                    <td>
                                        {course.standing}
                                        {course.removed && (
                                            <span className="label ml-2 text-wine">
                                                Removed
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {course.created}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(course.cost)}
                                    </td>
                                </tr>
                            ))}
                            {cost.unattributed > 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-ink">
                                        Not tied to a course — the subtopic
                                        check, and calls from before usage rows
                                        named their course
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(cost.unattributed)}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            <h2 className="label mb-3">Cost by feature</h2>
            <p className="mb-3 text-[14px] text-muted-ink">
                Leaves out calls an admin made acting as the student.
            </p>
            {cost.features.length === 0 ? (
                <p className="mb-11 text-muted-ink">No calls.</p>
            ) : (
                <div className="mb-11 overflow-x-auto">
                    <table className="ledger">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th className="text-right">Calls</th>
                                <th className="text-right">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cost.features.map((row) => (
                                <tr key={row.feature}>
                                    <td>{row.feature}</td>
                                    <td className="text-right tabular-nums">
                                        {row.calls}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {money.format(row.cost)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <h2 className="label mb-3">Memory</h2>
            {documents.length === 0 ? (
                <p className="text-muted-ink">No documents.</p>
            ) : (
                <div className="flex flex-col border-t border-hairline">
                    {documents.map((document) => (
                        <details
                            key={document.path}
                            className="border-b border-hairline py-2"
                        >
                            <summary className="flex min-h-11 cursor-pointer items-center gap-3 font-mono text-[13.5px]">
                                {document.path}
                                <span className="label text-muted-ink">
                                    {document.versions.length}{' '}
                                    {document.versions.length === 1
                                        ? 'version'
                                        : 'versions'}
                                </span>
                                {document.removed && (
                                    <span className="label text-wine">
                                        Removed
                                    </span>
                                )}
                            </summary>
                            {[...document.versions].reverse().map((version) => (
                                <details key={version.version} className="ml-4">
                                    <summary className="flex min-h-11 cursor-pointer flex-wrap items-center gap-x-3 text-[14px]">
                                        <span className="font-mono">
                                            v{version.version}
                                        </span>
                                        <span className="text-muted-ink">
                                            {version.when}
                                        </span>
                                        <span>{version.course}</span>
                                        {version.admin && (
                                            <span className="text-wine">
                                                acting: {version.admin}
                                            </span>
                                        )}
                                    </summary>
                                    <pre className="mb-3 overflow-x-auto border border-hairline bg-surface p-3 font-mono text-[13px] leading-[1.6] whitespace-pre-wrap">
                                        {version.content}
                                    </pre>
                                </details>
                            ))}
                        </details>
                    ))}
                </div>
            )}
        </AdminShell>
    );
}
