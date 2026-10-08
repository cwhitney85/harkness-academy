import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { terms } from '@/routes';
import { store } from '@/routes/google/sign-up';

type Props = {
    name: string;
    email: string;
    /** A demo keeping its course, rather than a newcomer (18.5.1). */
    keeping: boolean;
};

/**
 * Finish signing up (spec 18.3.4, ledger D73) — what Google cannot supply:
 * the invitation code and the 18-or-older attestation. The name is Google's
 * and can be changed; the email is Google's and cannot, because the server
 * reads it from the session, not from this form (18.2.4).
 */
export default function FinishSigningUp({ name, email, keeping }: Props) {
    return (
        <>
            <Head title="Finish signing up" />
            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="invitation_code">
                                Invitation code
                            </Label>
                            <Input
                                id="invitation_code"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="off"
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                name="invitation_code"
                                placeholder="The code you were sent"
                            />
                            <InputError message={errors.invitation_code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                type="text"
                                required
                                tabIndex={2}
                                autoComplete="name"
                                name="name"
                                defaultValue={name}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                readOnly
                                disabled
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="tos_accepted"
                                    name="tos_accepted"
                                    tabIndex={3}
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor="tos_accepted"
                                    className="text-sm leading-snug font-normal"
                                >
                                    I accept the{' '}
                                    <TextLink href={terms()} tabIndex={4}>
                                        Terms of Service
                                    </TextLink>
                                    .
                                </Label>
                            </div>
                            <InputError message={errors.tos_accepted} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            tabIndex={5}
                            data-test="finish-signing-up-button"
                        >
                            {processing && <Spinner />}
                            {keeping ? 'Keep my course' : 'Create account'}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

FinishSigningUp.layout = {
    title: 'Finish signing up',
    description: 'Google has told us who you are. Two things it cannot.',
};
