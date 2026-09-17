import { Link, usePage } from '@inertiajs/react';

import { create } from '@/routes/demo/account';

/**
 * The demo marker (spec 12.1.6): on every signed-in screen a demo visitor
 * sees, it says they are in the demo and links to signing up, which keeps what
 * they built.
 * Renders nothing for any other account.
 *
 * `onRail` for the green rail and the phone bars, where it is gold; otherwise
 * wine on paper. `compact` is the phone bar, which has room for the word and
 * not the sentence — the link's name still says where it goes.
 *
 * A 44px hit box whatever the text (8.5.4).
 */
export default function DemoMark({
    onRail = false,
    compact = false,
}: {
    onRail?: boolean;
    compact?: boolean;
}) {
    const { auth } = usePage().props;

    if (auth.user?.plan !== 'demo') {
        return null;
    }

    return (
        <Link
            href={create()}
            aria-label="Demo — sign up"
            className={[
                'label flex min-h-11 flex-none items-center gap-2 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                onRail ? 'text-gold' : 'text-wine',
            ].join(' ')}
        >
            <span
                className={[
                    'border px-1.5 py-0.5',
                    onRail ? 'border-gold' : 'border-wine',
                ].join(' ')}
            >
                Demo
            </span>
            {!compact && <span>Sign up</span>}
        </Link>
    );
}
