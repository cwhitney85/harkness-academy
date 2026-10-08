import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/google/connect';
import { request } from '@/routes/password';

type Props = {
    email: string;
};

/**
 * Connect Google (spec 18.3.3, ledger D73) — Google's email already has an
 * account here, so its password is asked for once before the two are joined.
 */
export default function ConnectGoogle({ email }: Props) {
    return (
        <>
            <Head title="Connect Google" />
            <p className="mb-6 text-center text-sm text-muted-foreground">
                {email} already has an account. Enter its password once to sign
                in with Google from now on.
            </p>
            <Form {...store.form()} resetOnError={['password']}>
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Password</Label>
                                <TextLink
                                    href={request()}
                                    className="ml-auto text-sm"
                                    tabIndex={3}
                                >
                                    Forgot your password?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="current-password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            className="w-full"
                            tabIndex={2}
                            disabled={processing}
                            data-test="connect-google-button"
                        >
                            {processing && <Spinner />}
                            Connect Google
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConnectGoogle.layout = {
    title: 'Connect Google',
    description: 'Sign in with Google from now on.',
};
