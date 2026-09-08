import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/api';
import { dashboard } from '@/routes';

/**
 * Screen S12 — the module rollup (spec 8.10.2).
 *
 * What the student knows under one subject, one line per subtopic, which is
 * `ModuleRollup`'s own shape: it is rendered on demand from the memory tree
 * and the courses table and never written, so this screen shows the rollup
 * rather than a copy of it (D8).
 *
 * No artboard exists for it either. It is the dashboard's card on the same
 * column, with the levels finished in brass where the card carries its level,
 * and the two refresher doors as the exam gate draws "Practise this subtopic"
 * — a `label` line in wine, not a second wine field competing with the action.
 */

type Course = {
    id: number;
    level: string;
    attempt: number;
    standing: string;
};

type Subtopic = {
    title: string;
    levels: string[];
    courses: Course[];
    refresher_url: string | null;
};

export default function ModuleRollup({
    module,
    subtopics,
    urls,
}: {
    module: { title: string };
    subtopics: Subtopic[];
    urls: { dashboard: string; refresher: string };
}) {
    /**
     * Which door was pressed, not merely that one was (8.12.1).
     *
     * Every door on this screen is barred while any of them is out, so a
     * boolean would have one subtopic's press announce itself on every other
     * subtopic and on the module button as well. The URL is what distinguishes
     * them, and it is already the argument each door carries.
     */
    const [writing, setWriting] = useState<string | null>(null);
    const [failure, setFailure] = useState<string | null>(null);

    /**
     * S12's two outgoing refresher edges (8.6). Both POST, both answer with
     * where the refresher is read, and we go there — the shape S7 and S10
     * already use for the other two grains.
     */
    function practise(url: string) {
        setWriting(url);
        setFailure(null);

        postJson<{ url: string }>(url, {})
            .then((refresher) => router.visit(refresher.url))
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setWriting(null));
    }

    return (
        <>
            <Head title={module.title} />

            <div className="measure py-8 sm:py-12">
                <p className="label text-brass">What you know</p>
                <h1 className="display mt-1.5 text-[38px] leading-[1.07] sm:text-[43px]">
                    {module.title}
                </h1>

                {subtopics.length === 0 ? (
                    <p className="mt-6 text-muted-ink">
                        Nothing is recorded under {module.title} yet. A subject
                        appears here once a course has asked you what you
                        already know about it.
                    </p>
                ) : (
                    <>
                        <p className="mt-4">
                            One line per subtopic, drawn from what your courses
                            recorded. Nothing here is a mark — it is what the
                            tutor knows about you.
                        </p>

                        <section className="mt-10">
                            <h2 className="label border-b border-brass/50 pb-1.5 text-brass">
                                Subtopics
                            </h2>
                            <div className="mt-4 flex flex-col gap-4">
                                {subtopics.map((subtopic) => (
                                    <SubtopicPanel
                                        key={subtopic.title}
                                        subtopic={subtopic}
                                        dashboard={urls.dashboard}
                                        writing={writing}
                                        onPractise={practise}
                                    />
                                ))}
                            </div>
                        </section>

                        <div className="mt-10 flex flex-wrap items-center gap-5 border-t border-hairline pt-6">
                            <Button
                                size="action"
                                disabled={writing !== null}
                                onClick={() => practise(urls.refresher)}
                            >
                                {writing === urls.refresher
                                    ? 'Writing your refresher…'
                                    : `Practise ${module.title}`}
                            </Button>
                            <span className="label">
                                Questions drawn from every subtopic above
                            </span>
                        </div>
                    </>
                )}

                {failure && (
                    <p className="label mt-5 text-wine" role="alert">
                        {failure}
                    </p>
                )}

                <p className="mt-10">
                    <Link
                        href={urls.dashboard}
                        className="label inline-flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Back to your courses
                    </Link>
                </p>
            </div>
        </>
    );
}

/**
 * One subtopic: what has been finished in it, the courses that got there, and
 * the door to practising it.
 *
 * A course links back to its card on the dashboard rather than into the course
 * itself — S12's edge is to S1's card (8.6), and where the card then leads is
 * the resume table's business, not this screen's.
 */
function SubtopicPanel({
    subtopic,
    dashboard: dashboardUrl,
    writing,
    onPractise,
}: {
    subtopic: Subtopic;
    dashboard: string;
    /** The door whose refresher is being written, if any (8.12.1). */
    writing: string | null;
    onPractise: (url: string) => void;
}) {
    return (
        <article className="border border-hairline bg-surface px-5 py-5 sm:px-6">
            <p className="label text-brass">
                {subtopic.levels.length > 0
                    ? `Completed: ${subtopic.levels.join(' · ')}`
                    : 'No course completed yet'}
            </p>
            <h3 className="display mt-1.5 text-[26px] leading-tight">
                {subtopic.title}
            </h3>

            {subtopic.courses.length > 0 && (
                <ul className="mt-3 border-t border-hairline">
                    {subtopic.courses.map((course) => (
                        <li key={course.id}>
                            <Link
                                href={`${dashboardUrl}#course-${course.id}`}
                                className="flex min-h-11 items-center gap-3 border-b border-hairline text-[15px] hover:text-wine focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass"
                            >
                                <span className="grow">
                                    {course.level}
                                    {course.attempt > 1 &&
                                        ` · attempt ${course.attempt}`}
                                </span>
                                <span className="text-muted-ink">
                                    {course.standing}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {subtopic.refresher_url && (
                <button
                    type="button"
                    disabled={writing !== null}
                    onClick={() => onPractise(subtopic.refresher_url as string)}
                    className="label mt-4 flex min-h-11 cursor-pointer items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass disabled:cursor-default disabled:no-underline disabled:opacity-60"
                >
                    {writing === subtopic.refresher_url
                        ? 'Writing your refresher…'
                        : `Practise ${subtopic.title}`}
                </button>
            )}
        </article>
    );
}

/**
 * The trail, which needs the module's own name and so reads the page's props
 * — Inertia resolves a `layout` function against them.
 */
ModuleRollup.layout = (props: { module: { title: string } }) => ({
    breadcrumbs: [
        { title: 'Your courses', href: dashboard() },
        { title: props.module.title, href: dashboard() },
    ],
});
