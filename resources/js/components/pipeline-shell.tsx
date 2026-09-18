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
 * The frame the four screens before a course is teachable are set in —
 * spec 8.7, direction D21.
 *
 * `StudyShell` frames studying: a rail of lessons, a paper column, an
 * explainer. Two of those three have nothing to hold here — a course being
 * created has no lessons and an interview has nothing to explain — but the
 * **rail is the app's frame on every settled desktop artboard**, so it stays,
 * carrying the four steps in the same anatomy the lesson rail carries lessons:
 * a brass numeral, the current step on a full-bleed wine band, the ones ahead
 * dimmed. Reducing it to a horizontal bar at every width would apply 8.5.2's
 * ≤767px collapse where the design does not ask for it, and would make four of
 * the twelve screens look like a different product from the other eight.
 *
 * There is no artboard for these screens, so nothing here is invented: the
 * rail, the column header and the action strip are all read off `ALesson`.
 *
 * Pre:  `step` is one of the four the pipeline has, in the order a student
 *       walks them. The shell owns the list and the count, so no screen
 *       formats an "of 4" of its own.
 * Post: the column is the shell's own scroll container, never the document —
 *       the property `StudyShell` holds, and for the same reason: Radix locks
 *       the body while a sheet is open.
 * Invariant: every control the shell draws clears 44px on its short side
 *       (8.5.4).
 *
 * A page set in this shell returns no layout from the switch in `app.tsx`.
 */

/** The four steps, in the order `CourseStatus` walks them. */
const STEPS = [
    { id: 'start', title: 'Start' },
    { id: 'knowledge', title: 'What you know' },
    { id: 'goals', title: 'What you want' },
    { id: 'syllabus', title: 'Your syllabus' },
] as const;

export type PipelineStep = (typeof STEPS)[number]['id'];

export default function PipelineShell({
    step,
    course,
    children,
    footer,
}: {
    step: PipelineStep;
    /**
     * The course this is a step of — the brass line under the crest. Absent on
     * S2, where the whole point is that no course exists yet.
     */
    course?: string;
    children: ReactNode;
    /**
     * The strip below the column: whatever this step's action is. Fixed, so a
     * long transcript never scrolls the Done button off the screen (8.7.2).
     */
    footer?: ReactNode;
}) {
    const phone = useIsMobile();
    const index = STEPS.findIndex((candidate) => candidate.id === step);
    const subtitle = course ?? 'A new course';

    return (
        <div className="flex h-dvh w-full overflow-hidden bg-ground text-ink">
            {!phone && (
                <aside className="flex w-[252px] flex-none flex-col gap-6 overflow-y-auto bg-rail-green px-[22px] py-6 text-ground/90">
                    <RailHead subtitle={subtitle} />
                    <StepList current={index} />
                </aside>
            )}

            <main className="flex min-w-0 grow flex-col">
                <ViewingBanner />
                {phone ? (
                    <PhoneBar current={index} subtitle={subtitle} />
                ) : (
                    <ColumnHeader current={index} />
                )}

                <div className="grow overflow-y-auto">{children}</div>

                {footer && (
                    <div className="flex-none border-t border-hairline bg-surface py-3 md:bg-transparent md:pt-4 md:pb-5">
                        <div className="measure">{footer}</div>
                    </div>
                )}
            </main>
        </div>
    );
}

/**
 * The four steps as the lesson rail draws its lessons: brass numeral, the
 * current one on a wine band bled to the rail's edges, the rest dimmed.
 */
function StepList({ current }: { current: number }) {
    return (
        <div className="flex flex-col gap-2.5">
            {/* Gold on a field-green rule, as the lesson rail heads its
                sections. The artboards draw that rule at a value between the
                two greens; field green is the nearest token and is already
                what `--sidebar-border` resolves to, so the rail keeps to the
                ten colours 8.3.1 allows. */}
            <div className="label border-b border-field-green pb-1.5 text-gold">
                Setting up
            </div>
            {STEPS.map((step, index) => (
                <div
                    key={step.id}
                    className={[
                        'flex items-baseline gap-3',
                        index === current
                            ? '-mx-[22px] bg-wine px-[22px] py-2.5 text-ground'
                            : index < current
                              ? 'text-ground/70'
                              : 'text-ground/55',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'display w-[18px] flex-none text-right',
                            index === current ? 'text-gold' : 'text-brass',
                        ].join(' ')}
                    >
                        {index + 1}
                    </span>
                    <span className="grow text-[15.5px]">{step.title}</span>
                    {index < current && (
                        <span className="flex-none text-brass">✓</span>
                    )}
                </div>
            ))}
        </div>
    );
}

/** Where the student is, at the head of the paper column (as `ALesson`). */
function ColumnHeader({ current }: { current: number }) {
    return (
        <div className="flex flex-none items-center gap-4 border-b border-hairline px-10 pt-[22px] pb-3.5">
            <span className="label">
                Step {current + 1} of {STEPS.length}
            </span>
            <span className="grow" />
            <span className="label">{STEPS[current].title}</span>
            <Ticks current={current} />
        </div>
    );
}

/**
 * The rail at ≤767px (8.5.2): a bar carrying the step the student is on, which
 * is the one thing the rail held that they cannot read off the page in front
 * of them. The list opens as a sheet from the left.
 */
function PhoneBar({
    current,
    subtitle,
}: {
    current: number;
    subtitle: string;
}) {
    return (
        <div className="flex flex-none items-center gap-3 bg-rail-green px-2.5 py-2.5 text-ground">
            <Sheet>
                <SheetTrigger className={railControl}>
                    <Menu className="size-[22px] text-gold" strokeWidth={1.9} />
                    <span className="sr-only">The steps of setting up</span>
                </SheetTrigger>
                <SheetContent
                    side="left"
                    className="w-[86%] max-w-[320px] border-r-0 bg-rail-green p-0 text-ground/90"
                >
                    <SheetTitle className="sr-only">
                        The steps of setting up
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        The four steps between starting a course and its first
                        lesson.
                    </SheetDescription>
                    <div className="flex h-full flex-col gap-6 overflow-y-auto px-[22px] py-6">
                        <RailHead subtitle={subtitle} />
                        <StepList current={current} />
                    </div>
                </SheetContent>
            </Sheet>

            <div className="min-w-0 grow">
                <div className="truncate text-[15px] leading-tight">
                    {STEPS[current].title}
                </div>
                <div className="label mt-0.5 text-[10px] text-brass">
                    Step {current + 1} of {STEPS.length}
                </div>
            </div>

            <DemoMark onRail compact />
            <Ticks current={current} onRail />
        </div>
    );
}

/**
 * The step rhythm, drawn rather than counted — `StudyShell`'s section ticks,
 * read against the ground on the column header and against rail green on the
 * bar, where wine disappears and the brass pair carries it (8.3.4).
 */
function Ticks({
    current,
    onRail = false,
}: {
    current: number;
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
            {STEPS.map((step, index) => (
                <span
                    key={step.id}
                    className={[
                        onRail ? 'h-[3px] w-3.5' : 'h-1 w-[26px]',
                        index < current
                            ? done
                            : index === current
                              ? here
                              : ahead,
                    ].join(' ')}
                />
            ))}
        </div>
    );
}
