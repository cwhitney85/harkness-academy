import { Head, Link, router, useForm } from '@inertiajs/react';

import { Fact } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import { prompts } from '@/routes/admin';
import { activate, show, update } from '@/routes/admin/prompts';

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

type Version = {
    number: number;
    tier: string;
    source: string;
    author: string | null;
    saved_at: string;
    active: boolean;
};

type Diff = {
    from: number;
    to: number;
    lines: { marker: 'keep' | 'add' | 'remove'; text: string }[];
};

type PromptDetail = {
    key: string;
    output: string;
    vars: string[];
    version: number;
    source: string;
    tier: string;
    body: string;
};

const saved = new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function AdminPrompt({
    prompt,
    tiers,
    versions,
    diff,
}: {
    prompt: PromptDetail;
    tiers: string[];
    versions: Version[];
    diff: Diff | null;
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

            <h2 className="label mt-11 mb-3">History</h2>
            <div className="overflow-x-auto">
                <table className="ledger min-w-max">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Tier</th>
                            <th>Saved</th>
                            <th>By</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {versions.map((version) => (
                            <tr key={version.number}>
                                <td className="whitespace-nowrap">
                                    v{version.number}
                                    {version.active && (
                                        <span className="label ml-2 text-wine">
                                            running
                                        </span>
                                    )}
                                </td>
                                <td className="text-[13px] tracking-[0.08em]">
                                    {version.tier}
                                </td>
                                <td className="whitespace-nowrap text-muted-ink">
                                    {saved.format(new Date(version.saved_at))}
                                </td>
                                <td>
                                    {version.source === 'file'
                                        ? 'seeded from file'
                                        : (version.author ?? '—')}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    {diff && diff.to !== version.number && (
                                        <Link
                                            href={show(prompt.key, {
                                                query: {
                                                    from: version.number,
                                                    to: diff.to,
                                                },
                                            })}
                                            preserveScroll
                                            className="label mr-3 inline-flex min-h-11 items-center text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                        >
                                            Compare
                                        </Link>
                                    )}
                                    {!version.active && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    activate([
                                                        prompt.key,
                                                        version.number,
                                                    ]).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                        >
                                            Run this
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {diff && (
                <>
                    <h2 className="label mt-11 mb-3">
                        v{diff.from} → v{diff.to}
                    </h2>
                    <pre className="overflow-x-auto border border-hairline bg-surface p-3 font-mono text-[13.5px] leading-[1.6]">
                        {diff.lines.map((line, index) => (
                            <div
                                key={index}
                                className={
                                    line.marker === 'add'
                                        ? 'bg-brass/15'
                                        : line.marker === 'remove'
                                          ? 'text-muted-ink line-through decoration-wine/50'
                                          : undefined
                                }
                            >
                                <span aria-hidden className="text-muted-ink">
                                    {line.marker === 'add'
                                        ? '+ '
                                        : line.marker === 'remove'
                                          ? '- '
                                          : '  '}
                                </span>
                                {line.text || '\u00a0'}
                            </div>
                        ))}
                    </pre>
                </>
            )}
        </AdminShell>
    );
}
