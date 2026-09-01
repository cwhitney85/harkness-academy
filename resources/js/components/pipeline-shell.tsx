import type { ReactNode } from 'react';

import Crest from '@/components/crest';

/**
 * The frame the four screens before a course is teachable are set in —
 * spec 8.7, direction D21.
 *
 * `StudyShell` frames studying: a rail of lessons, a paper column, an
 * explainer. None of that exists yet here — a course being created has no
 * lessons, and an interview has nothing to explain — so this is the same
 * language with the rail reduced to the bar it already becomes at ≤767px
 * (8.5.2). Nothing visual is invented: rail green above, ground below, brass
 * and gold for labels and rules, wine for the action.
 *
 * There is no mockup for these screens (the syllabus artboards on the canvas's
 * "First directions" page are in rejected palettes and were read for layout
 * only), so the rule is composition rather than a new idea.
 *
 * Pre:  `step` is one of the four the pipeline has, in the order a student
 *       walks them. The shell owns both the list and the count, so no screen
 *       formats an "of 4" of its own.
 * Post: the body is the shell's own scroll container, never the document —
 *       the same property `StudyShell` holds, and for the same reason.
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
    const index = STEPS.findIndex((candidate) => candidate.id === step);
    const current = STEPS[index];

    return (
        <div className="flex h-dvh w-full flex-col overflow-hidden bg-ground text-ink">
            <header className="flex flex-none items-center gap-3 bg-rail-green px-2.5 py-2.5 text-ground sm:px-5">
                <div className="flex flex-none items-center gap-3">
                    <Crest className="h-10 w-[34px] flex-none" />
                    <div className="min-w-0">
                        <div className="display text-2xl leading-none text-ground">
                            OpenTutor
                        </div>
                        <div className="label mt-1 truncate text-[10px] text-brass">
                            {course ?? 'A new course'}
                        </div>
                    </div>
                </div>

                <div className="grow" />

                <div className="flex flex-none items-center gap-3">
                    <span className="label hidden text-[10px] text-gold sm:inline">
                        Step {index + 1} of {STEPS.length} · {current.title}
                    </span>
                    <Ticks current={index} total={STEPS.length} />
                </div>
            </header>

            <div className="grow overflow-y-auto">{children}</div>

            {footer && (
                <div className="flex-none border-t border-hairline bg-surface py-3 md:bg-transparent md:pt-4 md:pb-5">
                    <div className="measure">{footer}</div>
                </div>
            )}
        </div>
    );
}

/**
 * The step rhythm, drawn rather than counted — the rail-bar ticks of
 * `StudyShell` read against the same field, with the same three states: behind
 * the student, where they are, and ahead of them. Wine disappears into rail
 * green, so the brass pair carries the reading (8.3.4).
 */
function Ticks({ current, total }: { current: number; total: number }) {
    return (
        <div className="flex flex-none items-center gap-[3px]">
            {Array.from({ length: total }, (_, index) => (
                <span
                    key={index}
                    className={[
                        'h-[3px] w-3.5',
                        index < current
                            ? 'bg-gold'
                            : index === current
                              ? 'bg-brass'
                              : 'bg-field-green',
                    ].join(' ')}
                />
            ))}
        </div>
    );
}
