import { Link } from '@inertiajs/react';

import Crest from '@/components/crest';
import Wordmark from '@/components/wordmark';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

/**
 * Screen S0 — the front door (spec 8.13), drawn as `Landing` and
 * `LandingPhone` on canvas page B · brand and settled in D46.
 *
 * Four things D46 chose, and this is where they are built: the argument
 * leads (L2), the wordmark stacks (W3), the motto is engraved off the mark
 * rather than off the headline (P2), and the whole page sits on `--door`
 * (G3) — a step below the ground a course is read on, because nobody is
 * inside a course yet.
 *
 * No shell frames it (8.13.3) and no bar or footer sits on it (8.13.4): the
 * mark is the page's one object rather than a header, so there is nothing
 * above the headline to repeat it.
 *
 * Nothing here reads `auth.user`, because a signed-in visitor is redirected
 * before this renders (8.13.2, 8.13.5).
 *
 * The phone lays the same page out as a column: a column has no right-hand
 * side to put an object on, so the mark moves above the argument and the two
 * buttons go full width. The DOM order is the phone's, and the desktop
 * reverses the row to put the mark back on the right.
 */
export default function Landing() {
    return (
        <div className="flex min-h-svh flex-col justify-center gap-[34px] bg-door px-5 py-12 lg:flex-row-reverse lg:items-center lg:gap-0 lg:px-0 lg:py-0">
            {/*
              * The crest, the wordmark and the motto as one object — the
              * page's only artwork, and the only place the motto is set at
              * all. The rules either side of it are what "engraved" means
              * (P2); on the phone the mark sits at the argument's left edge,
              * so the right-hand rule would run into nothing and only the
              * left one is drawn.
              */}
            <div className="flex items-center gap-4 lg:w-[492px] lg:flex-none lg:flex-col lg:items-center lg:gap-[34px] lg:pr-16">
                <Crest className="h-13 w-11 flex-none lg:h-[219px] lg:w-[186px]" />

                <div>
                    <Wordmark className="text-[19px] lg:text-center lg:text-[30px]" />

                    <div className="mt-[9px] flex items-center gap-[11px] lg:mt-5 lg:gap-4">
                        <span className="h-px w-[22px] bg-brass lg:w-9" />
                        <span className="text-[10.5px] tracking-[0.32em] text-brass uppercase lg:text-[12px] lg:tracking-[0.34em]">
                            Licet Facere
                        </span>
                        <span className="hidden h-px bg-brass lg:block lg:w-9" />
                    </div>
                </div>
            </div>

            <div className="lg:max-w-[840px] lg:flex-1 lg:pr-14 lg:pl-[108px]">
                <h1 className="display text-[52px] leading-[0.98] lg:text-[96px] lg:leading-[0.97]">
                    Enroll yourself.
                </h1>

                <p className="mt-6 text-[17px] leading-[1.58] lg:mt-[30px] lg:max-w-[604px] lg:text-[21px] lg:leading-[1.55]">
                    A tutor that interviews you, writes a syllabus you approve,
                    teaches it lesson by lesson, and examines you at the end.
                </p>

                <p className="mt-3.5 text-[15.5px] leading-[1.6] text-muted-ink lg:mt-[18px] lg:max-w-[604px] lg:text-[17px]">
                    You name the subject. It finds out what you already know
                    before it teaches you anything
                    <span className="hidden lg:inline">
                        , and it does not hand you the certificate until you
                        have sat the exam
                    </span>
                    .
                </p>

                <div className="mt-[30px] flex flex-col gap-2.5 lg:mt-10 lg:flex-row lg:items-center lg:gap-4">
                    <Button asChild size="action" className="w-full lg:w-auto">
                        <Link href={register()}>Enroll yourself</Link>
                    </Button>
                    <Button
                        asChild
                        size="action"
                        variant="outline"
                        className="w-full bg-transparent text-wine shadow-none lg:w-auto"
                    >
                        <Link href={login()}>Log in</Link>
                    </Button>
                </div>

                <p className="label mt-[18px] text-[10.5px] lg:mt-[22px] lg:text-[11px]">
                    Registration needs an invitation code
                </p>
            </div>
        </div>
    );
}
