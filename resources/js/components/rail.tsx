import { Link } from '@inertiajs/react';

import Crest from '@/components/crest';
import { dashboard } from '@/routes';

/**
 * The two pieces every rail in the app is built from — spec 8.5, D21.
 *
 * Extracted when `PipelineShell` turned out to need exactly what `StudyShell`
 * already drew at the head of its rail. The rail is the app's frame on every
 * settled desktop artboard, so a second shell drawing its own crest-and-
 * wordmark would be two places for one mark to drift.
 */

/**
 * The browser's own focus ring is white, which is not a colour this design has
 * (8.3.1), and every control that wears this sits on a green field where white
 * would be the brightest thing on the screen.
 */
const railFocus =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass';

/** A 44px hit box (8.5.4) and a focus ring in the palette. */
export const railControl = `flex size-11 flex-none items-center justify-center ${railFocus}`;

/**
 * The crest, the app's name, and what this rail is a rail for, in brass.
 *
 * The crest is the way home from every shell (8.5.7): the three course shells
 * carry no other link out of a course, so without it a student who wants the
 * dashboard has to type its URL. The mark is 34×40, under the touch minimum,
 * so the link pads out to 44×44 and cancels the padding with a matching
 * negative margin — the hit box grows, the artboard's spacing does not move.
 */
export function RailHead({ subtitle }: { subtitle: string }) {
    return (
        <div className="flex flex-none items-center gap-3">
            <Link
                href={dashboard()}
                className={`-mx-[5px] -my-0.5 flex flex-none items-center px-[5px] py-0.5 ${railFocus}`}
            >
                <Crest className="h-10 w-[34px] flex-none" />
            </Link>
            <div className="min-w-0">
                <div className="display text-2xl leading-none text-ground">
                    OpenTutor
                </div>
                <div className="label mt-1 truncate text-[10px] text-brass">
                    {subtitle}
                </div>
            </div>
        </div>
    );
}
