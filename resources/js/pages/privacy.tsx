import { Head, Link } from '@inertiajs/react';
import { terms } from '@/routes';

/**
 * The privacy policy (spec 18.2.5). Google will not publish a sign-in app
 * without one.
 */
export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy" />
            <div className="mx-auto flex min-h-screen max-w-2xl flex-col gap-6 p-6 text-sm leading-relaxed lg:p-8">
                <h1 className="text-2xl font-semibold">Privacy Policy</h1>

                <p>
                    Harkness Academy keeps your name, your email address and
                    your courses, and uses them only to teach you and to run the
                    service. If you sign in with Google, we receive only your
                    name and email address.
                </p>

                <p>
                    Lessons are written by an AI model, which is sent what you
                    write in a course. We do not sell your information, show
                    advertising, or track you.
                </p>

                <Link
                    href={terms()}
                    className="text-muted-foreground underline underline-offset-4"
                >
                    Terms of Service
                </Link>
            </div>
        </>
    );
}
