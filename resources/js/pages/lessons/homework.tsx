import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';

import AssessmentShell from '@/components/assessment-shell';
import type { RailRow } from '@/components/assessment-shell';
import Worksheet from '@/components/worksheet';
import type { HomeworkScreen } from '@/types';

/**
 * Screen S8 — the homework, on the photocopy sheet (spec 8.9.1, D22).
 *
 * The one surface practice is set on, and the reason the sheet is a
 * photocopied object rather than a panel: a quiz drawn on the paper column is
 * what made this read as corporate training (8.4.1).
 *
 * Two states. `pending` is the completion chain still writing it behind the
 * completion the student has already been shown (5.4.5) — an ordinary state,
 * not a missing page, so the screen waits rather than apologising. `ready` is
 * the sheet.
 *
 * Nothing here decides whether the next lesson is open: the chain unlocked it
 * and the server says so. Homework gates nothing (6.2).
 */
export default function HomeworkPage({
    lesson,
    course,
    student,
    date,
    state,
    homework,
    urls,
}: HomeworkScreen) {
    const rows: RailRow[] = [
        ...lesson.sections.map((title) => ({ label: title })),
        { label: 'Homework', current: true },
    ];

    return (
        <AssessmentShell
            course={course.title}
            heading={`Lesson ${lesson.position}`}
            title="Homework"
            rows={rows}
            note={
                homework ? (
                    <>
                        {homework.questions.length} questions.
                        <br />
                        Answers are kept as you go.
                    </>
                ) : undefined
            }
        >
            <Head title={`Homework — ${lesson.title}`} />

            {state === 'pending' || homework === null ? (
                <Pending lesson={lesson} url={urls.lesson} />
            ) : (
                <div className="py-7 sm:py-8">
                    <Worksheet
                        course={course.title}
                        scope={`Lesson ${lesson.position} · Homework`}
                        student={student}
                        date={date}
                        directions={`Circle the letter of the best answer. ${homework.scope}.`}
                        questions={homework.questions}
                        reading={homework.suggested_reading}
                        progress={homework.progress}
                        answersUrl={urls.answers}
                    >
                        <Onward urls={urls} />
                    </Worksheet>
                </div>
            )}
        </AssessmentShell>
    );
}

/**
 * The chain is still running (5.4.5).
 *
 * It waits on the same URL rather than sending the student anywhere, because
 * the homework is arriving and a page that told them it did not exist would be
 * saying something untrue. The reload is what the flow map means by "S8 waits".
 */
function Pending({
    lesson,
    url,
}: {
    lesson: HomeworkScreen['lesson'];
    url: string;
}) {
    useEffect(() => {
        const timer = window.setInterval(() => router.reload(), 5000);

        return () => window.clearInterval(timer);
    }, []);

    return (
        <div className="measure py-12 sm:py-16">
            <p className="label mb-3 text-wine">Lesson {lesson.position}</p>
            <h1 className="display text-[32px] leading-[1.1] sm:text-[40px]">
                Your homework is being written
            </h1>
            <p className="mt-5 text-muted-ink">
                It is set from what you were actually taught, so it is written
                once the lesson has been summarized. This page will show it as
                soon as it is ready.
            </p>
            <Link
                href={url}
                className="label mt-8 flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
            >
                Back to the lesson
            </Link>
        </div>
    );
}

/** S8's "done" edge: the next lesson, or the exam when none remain (8.6). */
function Onward({ urls }: { urls: HomeworkScreen['urls'] }) {
    return (
        <Link
            href={urls.next ?? urls.exam}
            className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
        >
            {urls.next ? 'The next lesson' : 'The final exam'}
        </Link>
    );
}
