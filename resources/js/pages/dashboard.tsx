import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';

/**
 * Screen S1 — the dashboard (spec 8.10.1, 8.10.3).
 *
 * No artboard was drawn for this screen, so nothing here is invented: it is
 * the column, the type ramp and the wine action of `ALesson`, and the card is
 * the bordered surface panel `courses/create` already uses for the questions
 * it asks. One card per course, and one move in each — the move being whatever
 * `NextAction` said, which is the flow map's resume table and the only thing
 * allowed to decide it (8.6).
 *
 * Three shelves, and the second is the point of the third: a course set aside
 * is not something the student is behind on, so it leaves the list of what is
 * underway rather than sitting in it greyed out (8.10.3, D7b).
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

type OpenCourse = CourseCard & { withdraw_url: string };

type SetAsideCourse = CourseCard & {
    set_aside_on: string | null;
    /** Present only when picking it back up would succeed. */
    resume_url: string | null;
    /** Present instead, and in the words the server chose (`ResumeRefusal`). */
    refusal: string | null;
};

export default function Dashboard({
    in_progress: inProgress,
    set_aside: setAside,
    completed,
    urls,
}: {
    in_progress: OpenCourse[];
    set_aside: SetAsideCourse[];
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
                            <Card key={course.id} course={course}>
                                <Button asChild size="action">
                                    <Link href={course.url}>Continue</Link>
                                </Button>
                                <SetAside course={course} />
                            </Card>
                        ))}
                    </Shelf>
                ) : (
                    <p className="mt-10 border-t border-hairline pt-6 text-muted-ink">
                        Nothing is underway. A course begins with two short
                        conversations — what you already know, and what you want
                        out of it — and a syllabus drawn up from them.
                    </p>
                )}

                {setAside.length > 0 && (
                    <Shelf label="Set aside">
                        {setAside.map((course) => (
                            <Card key={course.id} course={course}>
                                <PickBackUp course={course} />
                            </Card>
                        ))}
                    </Shelf>
                )}

                {completed.length > 0 && (
                    <Shelf label="Finished">
                        {completed.map((course) => (
                            <Card key={course.id} course={course}>
                                {course.module && (
                                    <Button
                                        asChild
                                        size="action"
                                        variant="outline"
                                    >
                                        <Link href={course.url}>
                                            What you know in {course.module}
                                        </Link>
                                    </Button>
                                )}
                            </Card>
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
 * One course: what it is, where it stands, and whatever its shelf offers.
 *
 * The standing line is the server's word for it (`CourseStatus::title`), never
 * a word this screen chose: three surfaces read that enum and they must not
 * disagree about what `syllabus_revision` is called. The action row comes in
 * as children, because what a card offers is a property of the shelf it is on
 * and not of the course.
 */
function Card({
    course,
    children,
}: {
    course: CourseCard;
    children: React.ReactNode;
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

            <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3">
                {children}
            </div>
        </article>
    );
}

/**
 * Stepping away, behind a confirmation (8.10.3).
 *
 * Reversible, and the wording says so — a student who reads "set aside" as
 * "delete" will never press it, and the one thing D7b is built to protect is
 * the progress that survives.
 */
function SetAside({ course }: { course: OpenCourse }) {
    const [asking, setAsking] = useState(false);

    return (
        <Dialog open={asking} onOpenChange={setAsking}>
            <DialogTrigger className="label flex min-h-11 cursor-pointer items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass">
                Set aside
            </DialogTrigger>
            <DialogContent className="border-hairline bg-surface">
                {/* Room for the dialog's own close control, which sits over
                    the top-right corner: a course title is as long as the
                    student's subject. */}
                <DialogHeader className="pr-8">
                    <DialogTitle className="display text-[26px] leading-tight">
                        Set {course.title} aside?
                    </DialogTitle>
                    <DialogDescription className="text-[17px] text-ink">
                        Nothing is lost. It keeps everything you have done and
                        waits where you left it, and picking it back up brings
                        you straight there. While it is set aside you can start
                        another course in the same subject.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="mt-2">
                    <Button
                        size="action"
                        variant="outline"
                        onClick={() => setAsking(false)}
                    >
                        Keep it open
                    </Button>
                    <Button
                        size="action"
                        onClick={() =>
                            router.post(
                                course.withdraw_url,
                                {},
                                {
                                    preserveScroll: true,
                                    onFinish: () => setAsking(false),
                                },
                            )
                        }
                    >
                        Set it aside
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Taking it back up — or the reason there is none.
 *
 * A course a later attempt has replaced says so rather than offering a button
 * that 422s (8.10.3): the refusal is `CourseTransitions`' own answer, asked
 * before the card was drawn, and in `ResumeRefusal`'s words.
 */
function PickBackUp({ course }: { course: SetAsideCourse }) {
    if (course.resume_url === null) {
        return <p className="text-muted-ink">{course.refusal}</p>;
    }

    return (
        <>
            <Button
                size="action"
                onClick={() =>
                    router.post(
                        course.resume_url as string,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Pick it back up
            </Button>
            {course.set_aside_on && (
                <span className="label">
                    Set aside on {course.set_aside_on}
                </span>
            )}
        </>
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
