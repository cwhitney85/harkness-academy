import { Head, Link, router } from '@inertiajs/react';

import { Filter, Pager } from '@/components/admin-controls';
import type { Page } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import { money } from '@/lib/utils';
import { index, show } from '@/routes/admin/courses';
import { show as user } from '@/routes/admin/users';

/**
 * Admin → Courses (spec 17.2.1): every course, removed ones included and
 * marked, newest first, filtered by standing, level and module.
 */

type Course = {
    id: number;
    student: { id: number; name: string };
    module: string;
    title: string;
    level: number;
    attempt: number;
    standing: string;
    removed: boolean;
    cost: number;
    created: string;
};

type Filters = {
    standing: string | null;
    level: number | null;
    module: string | null;
};

export default function AdminCourses({
    courses,
    filters,
    options,
}: {
    courses: Page<Course>;
    filters: Filters;
    options: { standings: string[]; levels: number[]; modules: string[] };
}) {
    const filter = (key: keyof Filters, value: string) =>
        router.get(
            index({
                query: { ...filters, [key]: value === '' ? null : value },
            }),
            {},
            { preserveScroll: true },
        );

    return (
        <AdminShell current="courses" title="Courses">
            <Head title="Courses" />
            <p className="mb-[26px] text-muted-ink">
                Every course, removed ones included. Cost leaves out calls an
                admin made acting as the student.
            </p>

            <div className="mb-7 flex flex-wrap items-end gap-[18px]">
                <Filter
                    label="Standing"
                    value={filters.standing ?? ''}
                    empty="Any standing"
                    options={options.standings.map((s) => [s, s])}
                    onChange={(value) => filter('standing', value)}
                />
                <Filter
                    label="Level"
                    value={filters.level?.toString() ?? ''}
                    empty="Any level"
                    options={options.levels.map((level) => [
                        level.toString(),
                        level.toString(),
                    ])}
                    onChange={(value) => filter('level', value)}
                />
                <Filter
                    label="Module"
                    value={filters.module ?? ''}
                    empty="Any module"
                    options={options.modules.map((m) => [m, m])}
                    onChange={(value) => filter('module', value)}
                />
            </div>

            {courses.data.length === 0 ? (
                <p className="text-muted-ink">No courses match.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="ledger">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Student</th>
                                <th>Level</th>
                                <th className="text-right">Attempt</th>
                                <th>Standing</th>
                                <th className="text-right">Cost</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            {courses.data.map((course) => (
                                <tr key={course.id}>
                                    <td>
                                        <Link
                                            href={show(course.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {course.title}
                                        </Link>
                                        <span className="block text-[13px] text-muted-ink">
                                            {course.module}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap">
                                        <Link
                                            href={user(course.student.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {course.student.name}
                                        </Link>
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
                                    <td className="text-right tabular-nums">
                                        {money.format(course.cost)}
                                    </td>
                                    <td className="whitespace-nowrap text-muted-ink">
                                        {course.created}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pager page={courses} />
        </AdminShell>
    );
}
