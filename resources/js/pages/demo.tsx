import { Form, Head } from '@inertiajs/react';

import Crest from '@/components/crest';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import Wordmark from '@/components/wordmark';
import { store } from '@/routes/demo';

/**
 * Screen S13 — the demo's welcome page (spec 12.1).
 *
 * The link a recruiter or an investor is sent. It says what the next half hour
 * is, then offers one button; the account is made by pressing it, not by
 * opening the link, so a chat app unfurling the URL makes nothing.
 *
 * Set as the landing page is (8.13, D46): on `--door`, no shell, the mark
 * above the argument as the phone lays S0 out. It is a front door too — nobody
 * is inside a course yet.
 */
export default function Demo() {
    return (
        <div className="flex min-h-svh flex-col justify-center bg-door px-5 py-12">
            <Head title="Try a course" />

            <div className="mx-auto w-full max-w-[640px]">
                <div className="flex items-center gap-4">
                    <Crest className="h-13 w-11 flex-none" />
                    <Wordmark className="text-[19px]" />
                </div>

                <h1 className="display mt-[34px] text-[52px] leading-[0.98] lg:text-[72px]">
                    Try a course.
                </h1>

                <p className="mt-6 text-[17px] leading-[1.58] lg:text-[19px]">
                    Name any subject. The tutor asks what you already know and
                    what you want from it, writes a one-lesson syllabus for you
                    to approve, teaches the lesson, and sets homework on it.
                </p>

                <p className="mt-3.5 text-[15.5px] leading-[1.6] text-muted-ink lg:text-[17px]">
                    The lesson takes twenty to thirty minutes. Nothing to fill
                    in first. When you finish, you can keep what you built; that
                    needs an invitation code.
                </p>

                <Form {...store.form()} className="mt-[30px] lg:mt-10">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="action"
                            disabled={processing}
                            className="w-full lg:w-auto"
                        >
                            {processing && <Spinner />}
                            Start the demo
                        </Button>
                    )}
                </Form>
            </div>
        </div>
    );
}
