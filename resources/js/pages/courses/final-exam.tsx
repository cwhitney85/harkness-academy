import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import AssessmentShell from '@/components/assessment-shell';
import type { RailRow } from '@/components/assessment-shell';
import BlueBook from '@/components/blue-book';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/api';
import type { FinalExamScreen, QuizAnswers, SittingResult } from '@/types';

/**
 * Screens S9 and S10 — the final exam, and the gate it holds (8.9.2, 8.9.3).
 *
 * Three states at one URL, and the server says which (8.6 draws S9 and S10 as
 * states of one screen, not as two places to navigate to): the book shut, the
 * book open, and what a sitting did to the course.
 *
 * **Nothing is disclosed until the book is handed in** (D20). There is no
 * per-question feedback here because there is no endpoint that could give any:
 * the exam is sat whole and marked once, and that is what makes a re-sit of
 * the same exam still measure something.
 */
export default function FinalExamPage({
    state,
    course,
    student,
    date,
    pass_mark,
    questions,
    resit,
    sitting,
    failure: reported,
    urls,
}: FinalExamScreen) {
    const [answers, setAnswers] = useState<QuizAnswers>({});
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState<string | null>(reported);

    const percent = Math.round(pass_mark * 100);

    const rows: RailRow[] = [
        ...course.lessons.map((lesson) => ({
            label: lesson.title,
            url: lesson.url,
        })),
        { label: 'Final examination', current: true },
    ];

    /**
     * Read the screen's next state off the server.
     *
     * The ticks do not travel with it. **A sitting is one filled-in book**: a
     * re-sit is a fresh one, and an Inertia reload leaves this component
     * mounted, so a book handed in with two answers on it would otherwise open
     * again with those two answers already ticked.
     */
    function refresh() {
        setAnswers({});
        router.reload();
    }

    /** Open the book, or open it again for a re-sit — the same exam either
     *  way, adopted rather than generated a second time (6.3). */
    function open() {
        setBusy(true);
        setFailure(null);

        postJson(urls.open, {})
            .then(refresh)
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setBusy(false));
    }

    /**
     * The book is being written on the queue (ledger D25).
     *
     * The same five-second reload the homework and the syllabus wait on, and
     * for the same reason: the exam is arriving, so the screen waits on its
     * own URL until the server says `open`.
     */
    useEffect(() => {
        if (state !== 'opening') {
            return;
        }

        const timer = window.setInterval(() => router.reload(), 5000);

        return () => window.clearInterval(timer);
    }, [state]);

    function handIn() {
        setBusy(true);
        setFailure(null);

        postJson(urls.sittings, { answers })
            // The result is the same URL in its `result` state, so the server
            // decides what the student now sees rather than this screen
            // remembering it.
            .then(refresh)
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setBusy(false));
    }

    function practise() {
        setBusy(true);
        setFailure(null);

        postJson<{ url: string }>(urls.refresher, {})
            .then((refresher) => router.visit(refresher.url))
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setBusy(false));
    }

    return (
        <AssessmentShell
            course={course.title}
            heading="Course"
            title="Final examination"
            rows={rows}
            note={<Standing state={state} sitting={sitting} />}
        >
            <Head title={`Final exam — ${course.title}`} />

            {state === 'result' && sitting ? (
                <Result
                    course={course.title}
                    sitting={sitting}
                    percent={percent}
                    busy={busy}
                    failure={failure}
                    onResit={open}
                    onPractise={practise}
                    dashboard={urls.dashboard}
                />
            ) : (
                <BlueBook
                    title={course.title}
                    terms={
                        // `opening` is a shut book too, so it reads the shut
                        // book's terms — what the exam will be — rather than
                        // the instructions for answering one it cannot see.
                        state === 'closed' || state === 'opening'
                            ? `Drawn from every lesson you have finished. The book is collected whole and marked once — no answer is shown until you hand it in. ${percent} per cent to pass, and a re-sit is the same examination.`
                            : 'Answer every question. The book is collected whole and marked once — no answer is disclosed until it is handed in. A re-sit is the same examination.'
                    }
                    student={student}
                    course={course.title}
                    date={date}
                    questions={questions}
                    answers={answers}
                    onAnswer={(questionId, optionId) =>
                        setAnswers((chosen) => ({
                            ...chosen,
                            [questionId]: optionId,
                        }))
                    }
                    open={state === 'open'}
                    // A re-sit opens straight to the leaf (8.4.4) — but not
                    // while the book is still being written, or the student
                    // would be handed an empty one.
                    resit={resit && state !== 'opening'}
                    // The prop already means "being written"; `opening` is
                    // that same fact, now arriving from the server rather than
                    // from this component's own click.
                    pending={busy || state === 'opening'}
                    onOpen={open}
                >
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
                        <Button
                            size="action"
                            onClick={handIn}
                            disabled={busy || Object.keys(answers).length === 0}
                        >
                            {busy ? 'Handing in…' : 'Hand in the book'}
                        </Button>
                        <span className="label">
                            {Object.keys(answers).length} of {questions.length}{' '}
                            answered
                        </span>
                        <span className="grow" />
                        {failure ? (
                            <span className="label text-wine" role="alert">
                                {failure}
                            </span>
                        ) : (
                            <span className="label">
                                Nothing is marked until you hand it in
                            </span>
                        )}
                    </div>
                </BlueBook>
            )}
        </AssessmentShell>
    );
}

/**
 * S10 — the sitting, and the gate (8.9.3).
 *
 * No artboard draws this, so nothing is invented: it is the desk the book was
 * lying on, the display face the closed book's title was set in, wine for the
 * one action and gold for the rule — the language 8.3 settled, composed.
 *
 * A failure is not an ending (ledger D7b). What the gate holds back is the
 * course's completion; what it hands over is the concepts that let the student
 * down and their own lessons to go and read again.
 */
function Result({
    course,
    sitting,
    percent,
    busy,
    failure,
    onResit,
    onPractise,
    dashboard,
}: {
    course: string;
    sitting: SittingResult;
    percent: number;
    busy: boolean;
    failure: string | null;
    onResit: () => void;
    onPractise: () => void;
    dashboard: string;
}) {
    const scored = Math.round(sitting.score * 100);

    return (
        <div className="flex min-h-full flex-col items-center justify-center bg-desk px-5 py-12">
            <div className="measure text-center">
                <p className="label text-wine">
                    {sitting.sitting === 1
                        ? 'First sitting'
                        : `Sitting ${sitting.sitting}`}
                </p>
                <h1 className="display mt-3 text-[32px] leading-[1.12] sm:text-[42px]">
                    {course}
                </h1>

                <p className="display mt-7 text-[56px] leading-none">
                    {sitting.correct} of {sitting.total}
                </p>
                <div className="mx-auto mt-5 h-px w-24 bg-gold" />

                <p className="mt-5">
                    {sitting.passed
                        ? `You passed. ${scored} per cent, against the ${percent} per cent this exam asks for.`
                        : `${scored} per cent. This exam asks for ${percent} per cent, so the course stays open and you can sit it again.`}
                </p>

                {!sitting.passed && sitting.concepts_missed.length > 0 && (
                    <div className="mt-7">
                        <p className="label mb-2">What let you down</p>
                        <p className="text-muted-ink">
                            {sitting.concepts_missed
                                .map((concept) => concept.replaceAll('-', ' '))
                                .join(' · ')}
                        </p>
                    </div>
                )}

                <div className="mt-9 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                    {sitting.passed ? (
                        <Button asChild size="action">
                            <Link href={dashboard}>Back to your courses</Link>
                        </Button>
                    ) : (
                        <Button size="action" onClick={onResit} disabled={busy}>
                            {busy ? 'Opening…' : 'Sit it again'}
                        </Button>
                    )}

                    <button
                        type="button"
                        onClick={onPractise}
                        disabled={busy}
                        className="label flex min-h-11 cursor-pointer items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass disabled:opacity-60"
                    >
                        Practise this subtopic
                    </button>
                </div>

                {failure && (
                    <p className="label mt-5 text-wine" role="alert">
                        {failure}
                    </p>
                )}
            </div>
        </div>
    );
}

/** The foot of the rail: where the student stands, in the artboard's words. */
function Standing({
    state,
    sitting,
}: Pick<FinalExamScreen, 'state' | 'sitting'>) {
    if (state === 'closed') {
        return <>Not yet attempted.</>;
    }

    if (state === 'opening') {
        return <>Being written.</>;
    }

    if (state === 'open') {
        return (
            <>
                In progress.
                <br />
                Nothing is marked until you hand the book in.
            </>
        );
    }

    return sitting?.passed ? (
        <>Passed at sitting {sitting.sitting}.</>
    ) : (
        <>
            Sitting {sitting?.sitting} did not pass.
            <br />
            The course is still yours.
        </>
    );
}
