import { Head, Link, useForm } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';
import { prompts } from '@/routes/admin';
import { update } from '@/routes/admin/prompts';

/**
 * One prompt (spec 15.2.2, 15.2.3): its contract, and the body and tier that
 * a save turns into a new version.
 *
 * The contract — the declared output and variables — comes from the prompt
 * file and is shown rather than offered, because the calling code depends on
 * it (ledger D65). `tiers` is empty for a prompt whose file declares `session`
 * or `none`, which are not tiers a prompt chooses for itself, and holds only
 * `cheap` and `mid` for one that streams inside a request (15.2.3).
 */

type PromptDetail = {
    key: string;
    output: string;
    vars: string[];
    version: number;
    source: string;
    tier: string;
    body: string;
};

export default function AdminPrompt({
    prompt,
    tiers,
}: {
    prompt: PromptDetail;
    tiers: string[];
}) {
    const form = useForm({ body: prompt.body, tier: prompt.tier });

    const dirty =
        form.data.body !== prompt.body || form.data.tier !== prompt.tier;

    return (
        <AdminShell current="prompts" title={prompt.key}>
            <Head title={prompt.key} />

            <Link
                href={prompts()}
                className="label mb-6 inline-flex min-h-11 items-center text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
            >
                ← All prompts
            </Link>

            <dl className="mb-8 flex flex-wrap gap-x-10 gap-y-3">
                <Fact term="Active">
                    v{prompt.version} ·{' '}
                    {prompt.source === 'file' ? 'from file' : 'edited'}
                </Fact>
                <Fact term="Output">{prompt.output}</Fact>
                <Fact term="Variables">
                    {prompt.vars.length === 0 ? '—' : prompt.vars.join(' · ')}
                </Fact>
            </dl>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update(prompt.key).url, {
                        preserveScroll: true,
                    });
                }}
            >
                <label className="mb-1.5 block">
                    <span className="label">Body</span>
                </label>
                <textarea
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    rows={24}
                    spellCheck={false}
                    aria-invalid={form.errors.body ? true : undefined}
                    aria-label="Body"
                    className="w-full border border-hairline bg-surface p-3 font-mono text-[13.5px] leading-[1.6] focus-visible:outline-2 focus-visible:outline-brass"
                />
                {form.errors.body && (
                    <p className="mt-1.5 max-w-[62ch] text-[13px] text-wine">
                        {form.errors.body}
                    </p>
                )}

                <div className="mt-6 flex flex-wrap items-end gap-x-6 gap-y-3">
                    {tiers.length > 0 ? (
                        <label className="flex flex-col gap-[5px]">
                            <span className="label">Tier</span>
                            <select
                                value={form.data.tier}
                                onChange={(event) =>
                                    form.setData('tier', event.target.value)
                                }
                                aria-invalid={
                                    form.errors.tier ? true : undefined
                                }
                                className="min-h-11 border border-hairline bg-surface px-2 focus-visible:outline-2 focus-visible:outline-brass"
                            >
                                {tiers.map((tier) => (
                                    <option key={tier} value={tier}>
                                        {tier}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : (
                        <p className="max-w-[46ch] text-[13px] text-muted-ink">
                            This prompt runs at{' '}
                            <span className="tracking-[0.08em]">
                                {prompt.tier}
                            </span>
                            , which is not a tier it chooses for itself — its
                            file declares it.
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing || !dirty}
                        className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass disabled:border-hairline disabled:text-muted-ink disabled:hover:bg-transparent disabled:hover:text-muted-ink"
                    >
                        Save as v{prompt.version + 1}
                    </button>
                    {form.errors.tier && (
                        <span className="max-w-[46ch] text-[13px] text-wine">
                            {form.errors.tier}
                        </span>
                    )}
                </div>
            </form>
        </AdminShell>
    );
}

function Fact({
    term,
    children,
}: {
    term: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="label">{term}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
