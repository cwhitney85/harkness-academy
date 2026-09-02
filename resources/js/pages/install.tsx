import { Head } from '@inertiajs/react';

/**
 * Spec 7.1's install-instructions page.
 *
 * It exists because iOS offers no install prompt and never will: Safari's only
 * install path is a menu item most people have never scrolled to. Android's
 * browser does prompt, but the same student reads both halves of one page, so
 * the two steps for Android are here rather than left to be guessed at.
 *
 * No artboard was drawn for it, so nothing here is invented — the column, the
 * display ramp and the brass label on a brass rule the dashboard already sets
 * (8.3, 8.10.1). No install button: `beforeinstallprompt` needs a fetch
 * handler, which needs a service worker, which spec 7.3 refuses.
 */
export default function Install() {
    return (
        <>
            <Head title="Put OpenTutor on your phone" />

            <div className="measure py-8 sm:py-12">
                <h1 className="display text-[38px] leading-[1.07] sm:text-[43px]">
                    Put OpenTutor on your phone
                </h1>
                <p className="mt-4">
                    Added to your home screen, OpenTutor opens as its own app —
                    no address bar, no tabs, just the lesson. It is the same
                    account and the same courses; nothing is downloaded and
                    nothing works differently.
                </p>

                <Steps label="On iPhone or iPad">
                    <li>
                        Open OpenTutor in Safari. Other browsers cannot do this
                        on iOS.
                    </li>
                    <li>
                        Tap the Share button — the square with an arrow coming
                        out of it, in the bar at the bottom of the screen.
                    </li>
                    <li>
                        Scroll down the list and tap{' '}
                        <Item>Add to Home Screen</Item>.
                    </li>
                    <li>
                        Tap <Item>Add</Item>. The crest appears on your home
                        screen.
                    </li>
                </Steps>

                <Steps label="On Android">
                    <li>Open OpenTutor in Chrome.</li>
                    <li>
                        Tap the three-dot menu at the top right and choose{' '}
                        <Item>Install app</Item>, or{' '}
                        <Item>Add to Home screen</Item> if that is what it says.
                    </li>
                    <li>
                        Confirm with <Item>Install</Item>.
                    </li>
                </Steps>

                <p className="mt-10 border-t border-hairline pt-6 text-muted-ink">
                    To remove it, press and hold the icon and delete it as you
                    would any app. Your courses stay where they are.
                </p>
            </div>
        </>
    );
}

/** A titled list of numbered steps: the dashboard's shelf, holding an `ol`. */
function Steps({
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
            <ol className="mt-4 flex list-decimal flex-col gap-3 pl-6 marker:text-muted-ink">
                {children}
            </ol>
        </section>
    );
}

/** A word the student is looking for on their own screen, set as one. */
function Item({ children }: { children: React.ReactNode }) {
    return <span className="text-ink italic">{children}</span>;
}
