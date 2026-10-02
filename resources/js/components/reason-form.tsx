import { Form } from '@inertiajs/react';

import type { RouteFormDefinition } from '@/wayfinder';

/**
 * A removal or a restore, asked from the row it acts on (spec 16.5.1), or an
 * account's restore (17.1.6). Each needs a reason, which the log keeps, and
 * says what it will do beside it. A restore that is refused says why here
 * (16.2.6), and the form stays open.
 */
export default function ReasonForm({
    form,
    verb,
    consequence,
    onDone,
}: {
    form: RouteFormDefinition<'post'>;
    verb: string;
    consequence: string;
    onDone: () => void;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex flex-col gap-3"
        >
            {({ errors, processing }) => (
                <>
                    <p className="max-w-[62ch]">{consequence}</p>
                    <div className="flex flex-wrap items-end gap-x-5 gap-y-3">
                        <label className="flex flex-col gap-[5px]">
                            <span className="label">Why</span>
                            <input
                                name="reason"
                                required
                                autoFocus
                                maxLength={500}
                                aria-invalid={errors.reason ? true : undefined}
                                className="min-h-11 w-[min(360px,80vw)] border border-hairline bg-surface px-2 focus-visible:outline-2 focus-visible:outline-brass"
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={processing}
                            className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                        >
                            {verb}
                        </button>
                        <button
                            type="button"
                            onClick={onDone}
                            className="label min-h-11 text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                        >
                            Cancel
                        </button>
                    </div>
                    {(errors.reason ?? errors.restore) && (
                        <p role="alert" className="text-[14px] text-wine">
                            {errors.reason ?? errors.restore}
                        </p>
                    )}
                </>
            )}
        </Form>
    );
}
