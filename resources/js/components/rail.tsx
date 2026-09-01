import Crest from '@/components/crest';

/**
 * The two pieces every rail in the app is built from — spec 8.5, D21.
 *
 * Extracted when `PipelineShell` turned out to need exactly what `StudyShell`
 * already drew at the head of its rail. The rail is the app's frame on every
 * settled desktop artboard, so a second shell drawing its own crest-and-
 * wordmark would be two places for one mark to drift.
 */

/**
 * A 44px hit box (8.5.4) and a focus ring in the palette. The browser's own
 * ring is white, which is not a colour this design has (8.3.1), and every
 * control that uses this sits on a green field where white would be the
 * brightest thing on the screen.
 */
export const railControl =
    'flex size-11 flex-none items-center justify-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass';

/** The crest, the app's name, and what this rail is a rail for, in brass. */
export function RailHead({ subtitle }: { subtitle: string }) {
    return (
        <div className="flex flex-none items-center gap-3">
            <Crest className="h-10 w-[34px] flex-none" />
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
