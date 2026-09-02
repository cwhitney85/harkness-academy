import { Head, Link } from '@inertiajs/react';

import AssessmentShell from '@/components/assessment-shell';
import Worksheet from '@/components/worksheet';
import type { RefresherScreen } from '@/types';

/**
 * Screen S11 — a refresher, at any of its three grains (spec 8.9.4).
 *
 * One screen and one material for all three, which is 8.4.2's done-condition
 * read from the other end: a lesson refresher, a subtopic refresher and a
 * module refresher differ in the scope printed at the head of the sheet and in
 * nothing else the student can see. A grain is decided by which door was used
 * (6.4) and is settled before this page is rendered.
 *
 * A refresher gates nothing and is marked nowhere: it is a warm-up, so getting
 * one wrong costs the student a reminder and nothing else.
 */
export default function RefresherPage({
    refresher,
    course,
    student,
    date,
    urls,
}: RefresherScreen) {
    return (
        <AssessmentShell
            course={course ?? 'Across your modules'}
            heading="Refresher"
            title={refresher.scope}
            rows={[{ label: refresher.scope, current: true }]}
            note={
                <>
                    {refresher.questions.length} questions.
                    <br />
                    Nothing here is marked or gated.
                </>
            }
        >
            <Head title={`Refresher — ${refresher.scope}`} />

            <div className="py-7 sm:py-8">
                <Worksheet
                    course={course ?? 'Across your modules'}
                    scope={`Refresher · ${refresher.scope}`}
                    student={student}
                    date={date}
                    questions={refresher.questions}
                    reading={refresher.suggested_reading}
                    progress={refresher.progress}
                    answersUrl={urls.answers}
                >
                    <Link
                        href={urls.dashboard}
                        className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Back to your courses
                    </Link>
                </Worksheet>
            </div>
        </AssessmentShell>
    );
}
