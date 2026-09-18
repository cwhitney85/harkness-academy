import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';

import DemoMark from '@/components/demo-mark';
import { railControl, RailHead } from '@/components/rail';
import ViewingBanner from '@/components/viewing-banner';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

/**
 * The frame the screens a student is *asked* on are set in — spec 8.9, D21.
 *
 * `StudyShell` frames being taught and `PipelineShell` frames setting a course
 * up; this frames being examined. It is the rail the assessment artboards draw
 * (`QuizPhotocopy`, `ExamClosed`, `ExamBlueBook`) and nothing else: no column
 * header, because the sheet and the book both print their own heading, and no
 * explainer, because nothing here is being explained until it has been
 * answered.
 *
 * The rail's body is the same anatomy all three shells share — what is behind
 * the student with a brass tick, what they are on as a full-bleed wine band —
 * so it is stated as rows here rather than drawn again on each of the three
 * screens that use it.
 *
 * Pre:  exactly one row is `current`. The wine band is where you are, and two
 *       of them would say the student is in two places.
 * Post: the column is the shell's own scroll container, never the document —
 *       the property the other two shells hold, and for the same reason: Radix
 *       locks the body while a sheet is open (8.5.3).
 * Invariant: every control the shell draws clears 44px on its short side
 *       (8.5.4).
 *
 * A page set in this shell returns no layout from the switch in `app.tsx`.
 */

export type RailRow = {
    label: string;
    /** Present when the student may go back to it; the rail draws the link
     *  from the URL rather than from a second rule of its own. */
    url?: string | null;
    /** The one row on the wine band: where the student is now. */
    current?: boolean;
};

export default function AssessmentShell({
    course,
    heading,
    title,
    rows,
    note,
    children,
}: {
    /** The course this is an assessment of — the brass line under the crest. */
    course: string;
    /** What the rail's list is a list of: 'Lesson 2', 'Course'. */
    heading: string;
    /** The phone bar's line, and what the student is being asked to do. */
    title: string;
    rows: RailRow[];
    /** The foot of the rail: how long this is, or where the student stands. */
    note?: ReactNode;
    children: ReactNode;
}) {
    const phone = useIsMobile();

    return (
        <div className="flex h-dvh w-full overflow-hidden bg-ground text-ink">
            {!phone && (
                <aside className="flex w-[252px] flex-none flex-col gap-6 overflow-y-auto bg-rail-green px-[22px] py-6 text-ground/90">
                    <RailHead subtitle={course} />
                    <RailList heading={heading} rows={rows} note={note} />
                </aside>
            )}

            <main className="flex min-w-0 grow flex-col">
                <ViewingBanner />
                {phone && (
                    <PhoneBar
                        course={course}
                        heading={heading}
                        title={title}
                        rows={rows}
                        note={note}
                    />
                )}

                <div className="grow overflow-y-auto">{children}</div>
            </main>
        </div>
    );
}

/**
 * The rail's body: a gold heading on a field-green rule, the rows behind the
 * student ticked in brass, and the one they are on bled to the rail's edges in
 * wine — the anatomy `ALesson` established and every settled artboard repeats.
 */
function RailList({
    heading,
    rows,
    note,
}: {
    heading: string;
    rows: RailRow[];
    note?: ReactNode;
}) {
    return (
        <>
            <div className="flex flex-col gap-2.5">
                <div className="label border-b border-field-green pb-1.5 text-gold">
                    {heading}
                </div>
                {rows.map((row, index) => (
                    <Row key={index} row={row} />
                ))}
            </div>

            {note && (
                <div className="label mt-auto border-t border-field-green pt-3.5 leading-relaxed text-ground/60">
                    {note}
                </div>
            )}
        </>
    );
}

function Row({ row }: { row: RailRow }) {
    const line = (
        <>
            <span
                aria-hidden
                className={row.current ? 'text-gold' : 'text-brass'}
            >
                {row.current ? '▸' : '✓'}
            </span>
            <span className="grow">{row.label}</span>
        </>
    );

    const shape = row.current
        ? '-mx-[22px] bg-wine px-[22px] py-2.5 text-ground'
        : 'text-ground/65';

    // A row the student can go back to is a link and clears 44px; one they
    // cannot is a line of type, and giving it a hit box would promise
    // something (8.5.4 sizes controls, not prose).
    return row.url ? (
        <Link
            href={row.url}
            className={`flex min-h-11 items-center gap-2.5 text-[15px] hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass ${shape}`}
        >
            {line}
        </Link>
    ) : (
        <div className={`flex items-center gap-2.5 text-[15px] ${shape}`}>
            {line}
        </div>
    );
}

/**
 * The rail at ≤767px (8.5.2): a bar carrying what the student is being asked
 * to do and which lesson it belongs to, with the list behind a left sheet.
 */
function PhoneBar({
    course,
    heading,
    title,
    rows,
    note,
}: {
    course: string;
    heading: string;
    title: string;
    rows: RailRow[];
    note?: ReactNode;
}) {
    return (
        <div className="flex flex-none items-center gap-3 bg-rail-green px-2.5 py-2.5 text-ground">
            <Sheet>
                <SheetTrigger className={railControl}>
                    <Menu className="size-[22px] text-gold" strokeWidth={1.9} />
                    <span className="sr-only">Where this sits</span>
                </SheetTrigger>
                <SheetContent
                    side="left"
                    className="w-[86%] max-w-[320px] border-r-0 bg-rail-green p-0 text-ground/90"
                >
                    <SheetTitle className="sr-only">Where this sits</SheetTitle>
                    <SheetDescription className="sr-only">
                        What this assessment covers, and what is behind it.
                    </SheetDescription>
                    <div className="flex h-full flex-col gap-6 overflow-y-auto px-[22px] py-6">
                        <RailHead subtitle={course} />
                        <RailList heading={heading} rows={rows} note={note} />
                    </div>
                </SheetContent>
            </Sheet>

            <div className="min-w-0 grow">
                <div className="truncate text-[15px] leading-tight">
                    {title}
                </div>
                <div className="label mt-0.5 truncate text-[10px] text-brass">
                    {heading}
                </div>
            </div>

            <DemoMark onRail compact />
        </div>
    );
}
