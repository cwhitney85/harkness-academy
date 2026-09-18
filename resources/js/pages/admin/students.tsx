import { Head } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';

/**
 * The students an admin may view as (spec 13.4) — a stand-in until 17.1's
 * Users screen, where viewing-as starts (17.1.4).
 */

type Student = {
    id: number;
    name: string;
    email: string;
    plan: string;
    joined: string | null;
};

export default function AdminStudents({ students }: { students: Student[] }) {
    return (
        <AdminShell title="View as a student">
            <Head title="View as a student" />
            <p className="mb-[26px] text-muted-ink">
                Preview shows a student’s screens read-only.
            </p>

            <div className="overflow-x-auto">
                <table className="ledger">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.map((student) => (
                            <tr key={student.id}>
                                <td>{student.name}</td>
                                <td>{student.email}</td>
                                <td>{student.plan}</td>
                                <td className="whitespace-nowrap text-muted-ink">
                                    {student.joined}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminShell>
    );
}
