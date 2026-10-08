import { Form, Head, usePage } from '@inertiajs/react';
import GoogleButton from '@/components/google-button';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordRequirements from '@/components/password-requirements';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { terms } from '@/routes';
import { store } from '@/routes/demo/account';

type Props = {
    passwordRules: string;
    passwordRequirements: string[];
};

/**
 * Screen S14 — signing up from the demo (spec 12.4).
 *
 * The register form, onto the account the demo already made: the same fields,
 * the same attestation and the same invitation code (12.4.3). The course stays
 * as it is and the lessons after the first open. Keep with Google is the
 * other way to the same row (18.5.1).
 */
export default function KeepCourse({
    passwordRules,
    passwordRequirements,
}: Props) {
    const { errors } = usePage().props;

    return (
        <>
            <Head title="Sign up" />
            <GoogleButton
                label="Keep with Google"
                error={errors.google}
                separator="Or with a password"
            />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
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
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                    aria-describedby="password-requirements"
                                />
                                <PasswordRequirements
                                    id="password-requirements"
                                    requirements={passwordRequirements}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="tos_accepted"
                                        name="tos_accepted"
                                        tabIndex={6}
                                        className="mt-0.5"
                                    />
                                    <Label
                                        htmlFor="tos_accepted"
                                        className="text-sm leading-snug font-normal"
                                    >
                                        I accept the{' '}
                                        <TextLink href={terms()} tabIndex={7}>
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
                                tabIndex={8}
                                data-test="keep-course-button"
                            >
                                {processing && <Spinner />}
                                Sign up
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

KeepCourse.layout = {
    title: 'Sign up to carry on',
    description:
        'Your course stays exactly as it is, and the rest of its lessons open.',
};
