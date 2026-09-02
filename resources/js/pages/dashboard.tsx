import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/**
 * Screen S1 — the dashboard (spec 8.10.1).
 *
 * No artboard was drawn for this screen, so nothing here is invented: it is
 * the column, the type ramp and the wine action of `ALesson`, and the card is
 * the bordered surface panel `courses/create` already uses for the questions
 * it asks. One card per course, and one move in each — the move being whatever
 * `NextAction` said, which is the flow map's resume table and the only thing
 * allowed to decide it (8.6).
 */

type CourseCard = {
    id: number;
    title: string;
    /** The catalog subject it sits under; null if that row has gone. */
    module: string | null;
    level: string;
    attempt: number;
    standing: string;
    detail: string | null;
    url: string;
};

export default function Dashboard({
    in_progress: inProgress,
    completed,
    urls,
}: {
    in_progress: CourseCard[];
    completed: CourseCard[];
    urls: { create: string };
}) {
    return (
        <>
            <Head title="Your courses" />

            <div className="measure py-8 sm:py-12">
                <h1 className="display text-[38px] leading-[1.07] sm:text-[43px]">
                    Your courses
                </h1>
                <p className="mt-4">
                    Everything you have underway, and the next thing to do in
                    each.
                </p>

                <div className="mt-8">
                    <Button asChild size="action">
                        <Link href={urls.create}>Start a course</Link>
                    </Button>
                </div>

                {inProgress.length > 0 ? (
                    <Shelf label="In progress">
                        {inProgress.map((course) => (
                            <Card key={course.id} course={course} />
                        ))}
                    </Shelf>
                ) : (
                    <p className="mt-10 border-t border-hairline pt-6 text-muted-ink">
                        Nothing is underway. A course begins with two short
                        conversations — what you already know, and what you want
                        out of it — and a syllabus drawn up from them.
                    </p>
                )}

                {completed.length > 0 && (
                    <Shelf label="Finished">
                        {completed.map((course) => (
                            <Card key={course.id} course={course} finished />
                        ))}
                    </Shelf>
                )}
            </div>
        </>
    );
}

/** A titled list of cards: a label, a brass rule, and what it introduces. */
function Shelf({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <section className="mt-10">
            <h2 className="label border-b border-brass/50 pb-1.5 text-brass">
                {label}
            </h2>
            <div className="mt-4 flex flex-col gap-4">{children}</div>
        </section>
    );
}

/**
 * One course, and the one move in it.
 *
 * The standing line is the server's word for where the course stands
 * (`CourseStatus::title`), never a word this screen chose: three surfaces read
 * that enum and they must not disagree about what `syllabus_revision` is
 * called.
 */
function Card({
    course,
    finished = false,
}: {
    course: CourseCard;
    /**
     * A finished course's move is not into the course — it is out to what the
     * module now knows about the student (S12). Where a card leads is still
     * `NextAction`'s answer; what changes is only what the button is called.
     */
    finished?: boolean;
}) {
    return (
        <article
            id={`course-${course.id}`}
            className="border border-hairline bg-surface px-5 py-5 sm:px-6"
        >
            <p className="label text-brass">
                {course.level}
                {course.attempt > 1 && ` · attempt ${course.attempt}`}
            </p>
            <h3 className="display mt-1.5 text-[26px] leading-tight">
                {course.title}
            </h3>
            <p className="mt-1 text-muted-ink">
                {course.standing}
                {course.detail && ` · ${course.detail}`}
            </p>

            <div className="mt-5">
                {finished ? (
                    course.module && (
                        <Button asChild size="action" variant="outline">
                            <Link href={course.url}>
                                What you know in {course.module}
                            </Link>
                        </Button>
                    )
                ) : (
                    <Button asChild size="action">
                        <Link href={course.url}>Continue</Link>
                    </Button>
                )}
            </div>
        </article>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Your courses',
            href: dashboard(),
        },
    ],
};
