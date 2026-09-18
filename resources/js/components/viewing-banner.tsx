import { Form, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { exit, stepUp } from '@/routes/admin/view-as';

/**
 * The view-as banner (spec 13.4.4, ledger D60), drawn on the canvas as
 * `ViewAsBanner`: the first thing in the column of every student shell while
 * an admin is viewing, naming the student and the mode, with Exit.
 *
 * Preview is field green — looking, nothing is saved — and offers the step
 * up, which asks why before it does anything (13.4.2). Act-as is wine, the
 * colour of a real action, and carries the reason given.
 *
 * `auth.user` is the student while viewing. Renders nothing otherwise.
 */
export default function ViewingBanner() {
    const { viewingAs, auth } = usePage().props;
    const [steppingUp, setSteppingUp] = useState(false);

    if (!viewingAs) {
        return null;
    }

    const preview = viewingAs.mode === 'preview';

    return (
        <div
            role="status"
            className={[
                'flex flex-none flex-wrap items-center gap-x-3.5 gap-y-1 px-5 py-1.5 md:px-10',
                preview ? 'bg-field-green text-ground' : 'bg-wine text-ground',
            ].join(' ')}
        >
            <span className="label text-gold">
                {preview ? 'Previewing' : 'Acting as'}
            </span>
            <span className="text-[15.5px]">{auth.user.name}</span>

            {preview && !steppingUp && (
                <span className="text-[15px] opacity-80">
                    Read-only — nothing you press is saved
                </span>
            )}
            {!preview && viewingAs.reason && (
                <em className="text-[15px] opacity-80">“{viewingAs.reason}”</em>
            )}

            <span className="grow" />

            {preview &&
                (steppingUp ? (
                    <Form
                        {...stepUp.form()}
                        className="flex flex-wrap items-center gap-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    name="reason"
                                    required
                                    autoFocus
                                    aria-label="Why you are acting as this student"
                                    placeholder="Why? — e.g. reproducing a bug they reported"
                                    className="min-h-11 w-[min(360px,80vw)] border border-field-green bg-rail-green/40 px-2.5 text-[15px] text-ground placeholder:text-ground/60 focus-visible:outline-2 focus-visible:outline-brass"
                                />
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="label min-h-11 bg-gold px-3 text-rail-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                >
                                    Act as them
                                </button>
                                {errors.reason && (
                                    <span className="text-[14px] text-gold">
                                        {errors.reason}
                                    </span>
                                )}
                            </>
                        )}
                    </Form>
                ) : (
                    <button
                        type="button"
                        onClick={() => setSteppingUp(true)}
                        className="label min-h-11 border border-gold px-2.5 text-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Step up to act as
                    </button>
                ))}

            <Link
                href={exit()}
                as="button"
                className="label flex min-h-11 items-center text-inherit underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
            >
                Exit
            </Link>
        </div>
    );
}
