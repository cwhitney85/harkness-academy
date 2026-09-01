import { Menu, X } from 'lucide-react';
import type { ReactNode } from 'react';

import Crest from '@/components/crest';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import type { StudyPosition } from '@/types';

/**
 * A 44px hit box (8.5.4) and a focus ring in the palette. The browser's own
 * ring is white, which is not a colour this design has (8.3.1), and both of
 * the shell's controls sit on a green field where it would be the brightest
 * thing on the screen.
 */
const control =
    'flex size-11 flex-none items-center justify-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass';

/**
 * The frame every studying screen is set in — spec 8.5, direction D21.
 *
 * Three fields at three distinct values, which is the whole anatomy of the
 * chosen direction: the **rail** (rail green, deepest, chrome, recedes), the
 * **paper column** (ground, the reading), and the **explainer** (field green,
 * mid, content, comes forward). The two greens are separated by value on
 * purpose, so they read as roles rather than as a matched pair (8.5.1).
 *
 * It is named for what it frames rather than for the app, because the starter
 * kit's `AppShell` already frames the settings and auth screens and this
 * replaces none of them.
 *
 * At ≤767px neither side field has a side to sit on. The rail collapses to a
 * bar carrying the lesson title and the section count, and opens as a sheet
 * from the left (8.5.2); the explainer rises as a sheet over dimmed prose
 * (8.5.3). Both are Radix Dialog by way of `ui/sheet`, which is where the
 * focus trap and the Escape handling come from — D24 keeps that primitive
 * precisely so this screen does not reimplement them.
 *
 * Pre:  `lesson` and `section` are 1-based and in range; the shell renders
 *       both the counts and the ticks from them, so nothing else formats an
 *       "of 4" of its own.
 * Post: the column is its own scroll container, never the document. That is
 *       what makes 8.5.3's last clause true: Radix locks the body while a
 *       sheet is open, and the body is not what the student was scrolling.
 * Invariant: every control the shell draws is at least 44px on its short
 *       side (8.5.4). The glyphs stay at the artboards' sizes; it is the hit
 *       box that grows around them.
 *
 * A page set in this shell owes one thing back: it must return no layout from
 * the switch in `app.tsx`. The shell is the whole viewport, and nesting it
 * inside the starter kit's `AppLayout` puts a second, unrelated sidebar beside
 * the rail.
 */
export default function StudyShell({
    course,
    title,
    lesson,
    section,
    rail,
    explainer,
    explainerOpen = false,
    onExplainerClose,
    ask,
    children,
}: {
    /** The course this lesson belongs to — the brass line under the crest. */
    course: string;
    /**
     * The lesson's title. The desktop rail does not repeat it (its own list
     * marks which lesson is current); the phone bar carries it, because at
     * ≤767px the bar is the only rail there is.
     */
    title: string;
    lesson: StudyPosition;
    section: StudyPosition;
    /** The rail's body: the course's lessons, and this lesson's sections. */
    rail: ReactNode;
    /**
     * What the explainer field shows. Undefined means this screen has no
     * explainer at all and the paper column takes the width back.
     */
    explainer?: ReactNode;
    explainerOpen?: boolean;
    /**
     * Called when the student closes the explainer — the X, Escape, or the
     * dimmed prose behind the phone sheet. It is the only direction it moves
     * on its own; opening is the screen's business.
     */
    onExplainerClose?: () => void;
    /** The strip below the column: the question box of spec 5.5. */
    ask?: ReactNode;
    /** The paper column. */
    children: ReactNode;
}) {
    const phone = useIsMobile();

    return (
        <div className="flex h-dvh w-full overflow-hidden bg-ground text-ink">
            {!phone && (
                <aside className="flex w-[252px] flex-none flex-col gap-6 overflow-y-auto bg-rail-green px-[22px] py-6 text-ground/90">
                    <RailHead course={course} />
                    {rail}
                </aside>
            )}

            <main className="flex min-w-0 grow flex-col">
                {phone ? (
                    <PhoneBar
                        course={course}
                        title={title}
                        lesson={lesson}
                        section={section}
                        rail={rail}
                    />
                ) : (
                    <ColumnHeader lesson={lesson} section={section} />
                )}

                <div className="grow overflow-y-auto">{children}</div>

                {ask && (
                    <div className="flex min-h-16 flex-none items-center border-t border-hairline bg-surface py-3 md:min-h-0 md:bg-transparent md:pt-4 md:pb-5">
                        <div className="measure">{ask}</div>
                    </div>
                )}
            </main>

            {!phone && explainer && explainerOpen && (
                <aside className="flex w-[342px] flex-none flex-col gap-4 overflow-y-auto bg-field-green px-6 py-[26px] text-ground">
                    <ExplainerHead onClose={onExplainerClose} />
                    <div className="grow">{explainer}</div>
                </aside>
            )}

            {phone && explainer && (
                <Sheet
                    open={explainerOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            onExplainerClose?.();
                        }
                    }}
                >
                    <SheetContent
                        side="bottom"
                        showClose={false}
                        className="flex h-[60dvh] flex-col gap-3.5 border-t-0 bg-field-green px-5 pt-3 pb-6 text-ground"
                    >
                        <SheetTitle className="sr-only">
                            Explain this
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            The tutor's explanation of the passage you
                            highlighted.
                        </SheetDescription>
                        {/* The grab handle: the sheet says which way it came
                            from before anyone has to try. */}
                        <div className="mx-auto h-1 w-11 flex-none bg-ground/35" />
                        <ExplainerHead onClose={onExplainerClose} />
                        <div className="grow overflow-y-auto">{explainer}</div>
                    </SheetContent>
                </Sheet>
            )}
        </div>
    );
}

/** The crest, the app's name, and the course under it in brass. */
function RailHead({ course }: { course: string }) {
    return (
        <div className="flex flex-none items-center gap-3">
            <Crest className="h-10 w-[34px] flex-none" />
            <div className="min-w-0">
                <div className="display text-2xl leading-none text-ground">
                    OpenTutor
                </div>
                <div className="label mt-1 truncate text-[10px] text-brass">
                    {course}
                </div>
            </div>
        </div>
    );
}

/** Where the student is, at the head of the paper column. */
function ColumnHeader({
    lesson,
    section,
}: {
    lesson: StudyPosition;
    section: StudyPosition;
}) {
    return (
        <div className="flex flex-none items-center gap-4 border-b border-hairline px-10 pt-[22px] pb-3.5">
            <span className="label">
                Lesson {lesson.number} of {lesson.total}
            </span>
            <span className="grow" />
            <span className="label">
                Section {section.number} of {section.total}
            </span>
            <Ticks section={section} />
        </div>
    );
}

/**
 * The rail at ≤767px (8.5.2). Title and section count survive the collapse,
 * because they are the two things the rail was carrying that the student
 * cannot reconstruct from the prose in front of them.
 */
function PhoneBar({
    course,
    title,
    lesson,
    section,
    rail,
}: {
    course: string;
    title: string;
    lesson: StudyPosition;
    section: StudyPosition;
    rail: ReactNode;
}) {
    return (
        <div className="flex flex-none items-center gap-3 bg-rail-green px-2.5 py-2.5 text-ground">
            <Sheet>
                <SheetTrigger className={control}>
                    <Menu className="size-[22px] text-gold" strokeWidth={1.9} />
                    <span className="sr-only">Lessons and sections</span>
                </SheetTrigger>
                <SheetContent
                    side="left"
                    className="w-[86%] max-w-[320px] border-r-0 bg-rail-green p-0 text-ground/90"
                >
                    <SheetTitle className="sr-only">
                        Lessons and sections
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        The course's lessons, and the sections of this one.
                    </SheetDescription>
                    <div className="flex h-full flex-col gap-6 overflow-y-auto px-[22px] py-6">
                        <RailHead course={course} />
                        {rail}
                    </div>
                </SheetContent>
            </Sheet>

            <div className="min-w-0 grow">
                <div className="truncate text-[15px] leading-tight">
                    {title}
                </div>
                <div className="label mt-0.5 text-[10px] text-brass">
                    Lesson {lesson.number} · Section {section.number} of{' '}
                    {section.total}
                </div>
            </div>

            <Ticks section={section} onRail />
        </div>
    );
}

/** 'Explain this', and the one way out of it. */
function ExplainerHead({ onClose }: { onClose?: () => void }) {
    return (
        <div className="flex flex-none items-center gap-2.5">
            <span className="label grow text-gold">Explain this</span>
            <button
                type="button"
                onClick={onClose}
                className={`-m-2.5 ${control}`}
            >
                <X className="size-[15px]" strokeWidth={2} />
                <span className="sr-only">Close the explainer</span>
            </button>
        </div>
    );
}

/**
 * The section rhythm, drawn rather than counted. One tick per section: behind
 * the student, where they are, and ahead of them.
 *
 * `onRail` is the same three states read against rail green instead of
 * against the paper — wine disappears into that field, and it is the only
 * colour 8.3.4 reserves, so on the bar the brass pair carries the reading.
 */
function Ticks({
    section,
    onRail = false,
}: {
    section: StudyPosition;
    onRail?: boolean;
}) {
    const done = onRail ? 'bg-gold' : 'bg-field-green';
    const here = onRail ? 'bg-brass' : 'bg-wine';
    const ahead = onRail ? 'bg-field-green' : 'bg-hairline';

    return (
        <div
            className={
                onRail
                    ? 'flex flex-none items-center gap-[3px]'
                    : 'flex flex-none items-center gap-[5px]'
            }
        >
            {Array.from({ length: section.total }, (_, index) => (
                <span
                    key={index}
                    className={[
                        onRail ? 'h-[3px] w-3.5' : 'h-1 w-[26px]',
                        index < section.number - 1
                            ? done
                            : index === section.number - 1
                              ? here
                              : ahead,
                    ].join(' ')}
                />
            ))}
        </div>
    );
}
