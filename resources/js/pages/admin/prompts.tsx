import { Head, Link } from '@inertiajs/react';

import AdminShell from '@/components/admin-shell';
import { money } from '@/lib/utils';
import { show } from '@/routes/admin/prompts';

/**
 * Admin → Prompts (spec 15.2.1): the 2.3.2 inventory, what version of each is
 * running, where that version came from, and what it has cost. Set in the
 * admin shell's `.ledger` language, as drawn on `AdminLog`.
 *
 * The text itself is edited one prompt at a time, on `admin/prompt`.
 */

type PromptRow = {
    key: string;
    output: string;
    version: number;
    source: string;
    tier: string;
    calls: number;
    cost: number;
};

export default function AdminPrompts({
    prompts,
    days,
}: {
    prompts: PromptRow[];
    days: number;
}) {
    return (
        <AdminShell current="prompts" title="Prompts">
            <Head title="Prompts" />
            <p className="mb-[26px] max-w-[62ch] text-muted-ink">
                Every prompt the app can send, with what it served over the last{' '}
                {days} days. The words that run are the active version’s, edited
                here — a prompt file seeds version 1 and then archives what is
                running, and a deploy never changes a prompt’s text.
            </p>

            <div className="overflow-x-auto">
                <table className="ledger min-w-max">
                    <thead>
                        <tr>
                            <th>Prompt</th>
                            <th>Active</th>
                            <th>Source</th>
                            <th>Tier</th>
                            <th className="text-right">Calls</th>
                            <th className="text-right">Cost</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {prompts.map((prompt) => (
                            <tr key={prompt.key}>
                                <td className="whitespace-nowrap">
                                    {prompt.key}
                                    <span className="block text-[13px] text-muted-ink">
                                        {prompt.output}
                                    </span>
                                </td>
                                <td className="whitespace-nowrap">
                                    v{prompt.version}
                                </td>
                                <td>
                                    {prompt.source === 'file'
                                        ? 'file'
                                        : 'edited'}
                                </td>
                                <td className="text-[13px] tracking-[0.08em]">
                                    {prompt.tier}
                                </td>
                                <td className="text-right">{prompt.calls}</td>
                                <td className="text-right whitespace-nowrap">
                                    {money.format(prompt.cost)}
                                </td>
                                <td className="text-right">
                                    <Link
                                        href={show(prompt.key)}
                                        className="label inline-flex min-h-11 items-center border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminShell>
    );
}
