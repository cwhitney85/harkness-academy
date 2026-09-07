import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import PipelineShell from '@/components/pipeline-shell';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';

/**
 * Screen S5 — the syllabus (spec 8.7.4, 8.7.5, 4.5.4).
 *
 * The one screen in the pipeline that is a document rather than a
 * conversation, so it is set as one: the course's title in display, the
 * overview as prose in the measure, then the lessons as a numbered list under
 * a brass rule. That is the layout the rejected "Foolscap" syllabus artboard
 * established; only its palette was rejected, and none of it is used here.
 *
 * **The level-mismatch banner never blocks approval** (4.5.7). It is a region
 * of this screen, drawn from a field of the syllabus, and nothing about
 * approving reads it — a course whose goals wandered outside its subtopic is
 * still a course the student asked for.
 *
 * Revising is bounded and the screen says by how much, because running out is
 * a thing that happens to a student mid-thought and "approve is the only move
 * left" needs a reason attached to it.
 *
 * **`final_exam_concepts` is the exam's emphasis, not its scope** (8.7.7).
 * The panel said "the final exam draws on" over that list, which was true
 * while 6.3.1 gated questions on it and stopped being true on 2026-09-04:
 * the exam is sat on every lesson, and a student reading the old wording
 * would revise for a third of what they are asked. The count beside it is
 * the controller's, computed from the lesson count (6.3.2) -- the screen
 * states no number of its own.
 */

type Lesson = {
    position: number;
    title: string;
    objective: string;
    sections: { title: string; concepts: string[] }[];
    homework_focus: string;
    estimated_minutes: number;
};

type Syllabus = {
    id: number;
    generation: number;
    feedback: string | null;
    course_title: string;
    overview: string;
    lessons: Lesson[];
    final_exam_concepts: string[];
    subtopic_mismatch: string | null;
};

type Props = {
    course: { id: number; title: string; level: string };
    syllabus: Syllabus | null;
    revisions: { used: number; allowed: number; remaining: number };
    exam: { questions: number; pass_mark: number };
    urls: { generate: string; revise: string; approve: string };
    /** A generation is out on the queue and has not landed (ledger D25). */
    generating: boolean;
    /** Why the last attempt at this screen's own POSTs was refused, if it was. */
    failure: string | null;
};

/** I, II, III… — the artboards number lessons the way a printed course does. */
const NUMERALS = [
    'I',
    'II',
    'III',
    'IV',
    'V',
    'VI',
    'VII',
    'VIII',
    'IX',
    'X',
    'XI',
    'XII',
];

export default function SyllabusScreen({
    course,
    syllabus,
    revisions,
    exam,
    urls,
    generating,
    failure,
}: Props) {
    const [asking, setAsking] = useState(false);

    const generate = useForm({});
    const approve = useForm({});
    const revise = useForm({ feedback: '' });

    const heading = course.title;

    if (generating) {
        return (
            <PipelineShell step="syllabus" course={heading}>
                <Head title="Your syllabus" />
                <Drawing revising={syllabus !== null} />
            </PipelineShell>
        );
    }

    if (!syllabus) {
        return (
            <PipelineShell step="syllabus" course={heading}>
                <Head title="Your syllabus" />
                <div className="measure py-8 sm:py-12">
                    <h1 className="display text-[38px] leading-[1.07] sm:text-[43px]">
                        Ready to draw up your course
                    </h1>
                    <p className="mt-4">
                        Both interviews are written up. The next step turns them
                        into a syllabus — a course title, an overview, and a
                        lesson at a time with what each one is for.
                    </p>
                    {failure && (
                        <p className="mt-4 text-[15px] text-wine">{failure}</p>
                    )}
                    <div className="mt-8">
                        <Button
                            size="action"
                            disabled={generate.processing}
                            onClick={() =>
                                generate.post(urls.generate, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {generate.processing
                                ? 'Drawing it up…'
                                : 'Draw up my syllabus'}
                        </Button>
                    </div>
                </div>
            </PipelineShell>
        );
    }

    const minutes = syllabus.lessons.reduce(
        (total, lesson) => total + lesson.estimated_minutes,
        0,
    );

    return (
        <PipelineShell
            step="syllabus"
            course={heading}
            footer={
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <Button
                        size="action"
                        disabled={approve.processing}
                        onClick={() =>
                            approve.post(urls.approve, { preserveScroll: true })
                        }
                    >
                        Approve this syllabus
                    </Button>
                    <button
                        type="button"
                        onClick={() => setAsking((open) => !open)}
                        className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        {asking ? 'Never mind' : 'Or ask for changes'}
                    </button>
                    <span className="label sm:ml-auto">
                        Draft {syllabus.generation} ·{' '}
                        {revisions.remaining === 0
                            ? 'no revisions left'
                            : `${revisions.remaining} of ${revisions.allowed} revisions left`}
                    </span>
                </div>
            }
        >
            <Head title="Your syllabus" />

            <div className="measure py-8 sm:py-12">
                <p className="label text-brass">
                    Draft syllabus · awaiting your approval
                </p>
                <h1 className="display mt-2 text-[38px] leading-[1.07] sm:text-[43px]">
                    {syllabus.course_title}
                </h1>
                <p className="label mt-3">
                    {syllabus.lessons.length} lessons · about{' '}
                    {Math.round((minutes / 60) * 10) / 10} hours ·{' '}
                    {course.level} level
                </p>

                {syllabus.subtopic_mismatch && (
                    <Mismatch note={syllabus.subtopic_mismatch} />
                )}

                {failure && (
                    <p className="mt-6 text-[15px] text-wine">{failure}</p>
                )}

                <p className="mt-7 whitespace-pre-wrap">{syllabus.overview}</p>

                <h2 className="label mt-10 border-b border-brass/50 pb-1.5 text-brass">
                    The lessons
                </h2>

                <ol className="mt-1">
                    {syllabus.lessons.map((lesson) => (
                        <li
                            key={lesson.position}
                            className="flex gap-4 border-b border-hairline py-5 sm:gap-6"
                        >
                            <span className="display w-8 flex-none text-right text-[22px] leading-snug text-brass">
                                {NUMERALS[lesson.position - 1] ??
                                    lesson.position}
                            </span>
                            <div className="min-w-0 grow">
                                <h3 className="text-[19px] leading-snug">
                                    {lesson.title}
                                </h3>
                                <p className="mt-1 text-[16px] leading-snug text-muted-ink">
                                    {lesson.objective}
                                </p>
                                <p className="label mt-2 text-wine">
                                    {lesson.sections
                                        .flatMap((section) => section.concepts)
                                        .join(' · ')}
                                </p>
                            </div>
                            <span className="label flex-none pt-1">
                                {lesson.estimated_minutes} min
                            </span>
                        </li>
                    ))}
                </ol>

                <section className="mt-9 border border-t-[3px] border-hairline border-t-brass bg-surface px-5 py-5 sm:px-6">
                    <h2 className="label text-brass">The final exam</h2>
                    <p className="mt-3 text-[16px] text-muted-ink">
                        {exam.questions} questions, drawn from every lesson
                        above. {Math.round(exam.pass_mark * 100)} per cent to
                        pass.
                    </p>
                    <h3 className="label mt-4 text-brass">
                        The weight falls on
                    </h3>
                    <p className="label mt-2 text-wine">
                        {syllabus.final_exam_concepts.join(' · ')}
                    </p>
                </section>

                {asking && (
                    <section className="mt-8">
                        <h2 className="label border-b border-brass/50 pb-1.5 text-brass">
                            Ask for changes
                        </h2>
                        {revisions.remaining === 0 ? (
                            <p className="mt-4">
                                You have used all {revisions.allowed} revisions.
                                Approve what you have — the course is yours to
                                walk, and nothing here is a promise about what
                                the tutor may cover along the way.
                            </p>
                        ) : (
                            <>
                                <p className="mt-4 text-muted-ink">
                                    Say what should be different. It is rebuilt
                                    against your notes and both interviews, and
                                    what you have now stays until the new one
                                    lands.
                                </p>
                                <textarea
                                    value={revise.data.feedback}
                                    rows={3}
                                    maxLength={2000}
                                    placeholder="Fewer lessons, and more drilling on the second one — I do not need the last one at all…"
                                    onChange={(event) =>
                                        revise.setData(
                                            'feedback',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-3 w-full resize-y border border-hairline bg-surface px-3.5 py-2.5 text-[17px] placeholder:text-muted-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass"
                                />
                                <InputError message={revise.errors.feedback} />
                                <div className="mt-4">
                                    <Button
                                        size="action"
                                        variant="outline"
                                        disabled={
                                            revise.processing ||
                                            revise.data.feedback.trim() === ''
                                        }
                                        onClick={() =>
                                            revise.post(urls.revise, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {revise.processing
                                            ? 'Rebuilding…'
                                            : 'Request revisions'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </section>
                )}

                {syllabus.feedback && (
                    <p className="label mt-8">
                        This draft was built from: “{syllabus.feedback}”
                    </p>
                )}
            </div>
        </PipelineShell>
    );
}

/**
 * Spec 8.7.5 / 4.5.7 — the level-mismatch note.
 *
 * Non-blocking by construction: it is a paragraph, it carries no control, and
 * approve is not conditioned on it anywhere. The wine top rule is the same
 * marking the checkpoint uses for "read this before you go on" (D21), which is
 * as loud as a note that changes nothing should be.
 */
function Mismatch({ note }: { note: string }) {
    return (
        <aside className="mt-7 border border-t-[3px] border-hairline border-t-wine bg-surface px-5 py-5 sm:px-6">
            <h2 className="label text-wine">A note on scope</h2>
            <p className="mt-3">{note}</p>
            <p className="label mt-3">
                This does not stop you — approve it if it is what you wanted.
            </p>
        </aside>
    );
}

/**
 * The generation is out on the queue (ledger D25).
 *
 * It waits on the same URL rather than sending the student anywhere, which is
 * `lessons/homework`'s Pending exactly — the same five-second reload, because
 * the same thing is true: what they asked for is arriving, and a screen saying
 * it does not exist would be saying something untrue.
 *
 * A revision keeps the previous syllabus behind this, so the copy says so: the
 * one thing a student sending work back needs to know is that they have not
 * lost what they had.
 */
function Drawing({ revising }: { revising: boolean }) {
    useEffect(() => {
        const timer = window.setInterval(() => router.reload(), 5000);

        return () => window.clearInterval(timer);
    }, []);

    return (
        <div className="measure py-12 sm:py-16">
            <p className="label mb-3 text-wine">
                {revising ? 'Revision' : 'Syllabus'}
            </p>
            <h1 className="display text-[32px] leading-[1.1] sm:text-[40px]">
                Your syllabus is being drawn up
            </h1>
            <p className="mt-5 text-muted-ink">
                It is written from both interviews in one pass, which takes a
                minute or two. This page will show it as soon as it is ready —
                you can leave and come back.
            </p>
            {revising && (
                <p className="mt-4 text-muted-ink">
                    Your previous syllabus is untouched until this one arrives.
                </p>
            )}
        </div>
    );
}
