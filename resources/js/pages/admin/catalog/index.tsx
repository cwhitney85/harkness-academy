import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

import AdminShell from '@/components/admin-shell';
import CatalogViews from '@/components/catalog-views';
import ReasonForm from '@/components/reason-form';
import { retitle } from '@/routes/admin/catalog';
import { remove as removeModule } from '@/routes/admin/catalog/modules';
import { remove as removeSubtopic } from '@/routes/admin/catalog/subtopics';

/**
 * Admin → Catalog → Modules (spec 16.5, ledger D57): every module and
 * subtopic not removed, the ones students typed included. Remove asks why and
 * says what it will hide, which is spec 16's table; Removed brings it back.
 * Retitle changes what the picker shows and leaves the slug, and so every
 * course's documents, where they are (16.5.2).
 */

type Subtopic = {
    id: number;
    title: string;
    slug: string;
    listed: boolean;
    courses: number;
};

type Module = { id: number; title: string; subtopics: Subtopic[] };

/** The one row whose form is open, if any. */
type Open = { form: 'module' | 'remove' | 'retitle'; id: number } | null;

const BUTTON =
    'label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass';

function courses(n: number) {
    return n === 1 ? '1 course' : `${n} courses`;
}

export default function CatalogModules({ modules }: { modules: Module[] }) {
    const [open, setOpen] = useState<Open>(null);
    const close = () => setOpen(null);
    const isOpen = (form: NonNullable<Open>['form'], id: number) =>
        open?.form === form && open.id === id;

    return (
        <AdminShell current="catalog" title="Catalog">
            <Head title="Modules" />
            <CatalogViews current="modules" />

            <p className="mb-[26px] max-w-[62ch] text-muted-ink">
                Every module and subtopic, the ones students typed included.
                Removing one hides it from everyone, with its courses and their
                documents; Removed brings it back. Both ask why, and both are
                logged.
            </p>

            {modules.map((module) => {
                const total = module.subtopics.reduce(
                    (sum, subtopic) => sum + subtopic.courses,
                    0,
                );

                return (
                    <section key={module.id} className="mb-[38px]">
                        <div className="mb-3 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                            <h2 className="display text-[26px] leading-tight">
                                {module.title}
                            </h2>
                            <span className="text-[13px] text-muted-ink">
                                {module.subtopics.length} subtopics ·{' '}
                                {courses(total)}
                            </span>
                            <span className="grow" />
                            {!isOpen('module', module.id) && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setOpen({
                                            form: 'module',
                                            id: module.id,
                                        })
                                    }
                                    className={BUTTON}
                                >
                                    Remove module
                                </button>
                            )}
                        </div>

                        {isOpen('module', module.id) && (
                            <div className="mb-4">
                                <ReasonForm
                                    form={removeModule.form(module.id)}
                                    verb="Remove module"
                                    consequence={`Hides ${module.title} and its ${module.subtopics.length} subtopics${total > 0 ? `, their ${courses(total)} and those courses’ documents,` : ''} and each subtopic’s working knowledge.`}
                                    onDone={close}
                                />
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="ledger min-w-max">
                                <thead>
                                    <tr>
                                        <th>Subtopic</th>
                                        <th className="text-right">Courses</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {module.subtopics.map((subtopic) => (
                                        <tr key={subtopic.id}>
                                            {isOpen('remove', subtopic.id) ? (
                                                <td colSpan={3}>
                                                    <ReasonForm
                                                        form={removeSubtopic.form(
                                                            subtopic.id,
                                                        )}
                                                        verb="Remove"
                                                        consequence={`Hides ${subtopic.title}${subtopic.courses > 0 ? `, its ${courses(subtopic.courses)} and their documents,` : ''} and its working knowledge. Typing its name is refused until it is restored.`}
                                                        onDone={close}
                                                    />
                                                </td>
                                            ) : isOpen(
                                                  'retitle',
                                                  subtopic.id,
                                              ) ? (
                                                <td colSpan={3}>
                                                    <Retitle
                                                        subtopic={subtopic}
                                                        onDone={close}
                                                    />
                                                </td>
                                            ) : (
                                                <>
                                                    <td>
                                                        {subtopic.title}
                                                        <span className="block text-[13px] text-muted-ink">
                                                            {subtopic.slug}
                                                            {!subtopic.listed &&
                                                                ' · typed by a student, not in the picker'}
                                                        </span>
                                                    </td>
                                                    <td className="text-right">
                                                        {subtopic.courses}
                                                    </td>
                                                    <td className="text-right whitespace-nowrap">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setOpen({
                                                                    form: 'retitle',
                                                                    id: subtopic.id,
                                                                })
                                                            }
                                                            className="label mr-3 min-h-11 px-2 text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                                                        >
                                                            Retitle
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setOpen({
                                                                    form: 'remove',
                                                                    id: subtopic.id,
                                                                })
                                                            }
                                                            className={BUTTON}
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                );
            })}
        </AdminShell>
    );
}

/**
 * The title the picker shows, starting from the current one. Mounted only
 * while its row is open, so the field starts from that row's title.
 */
function Retitle({
    subtopic,
    onDone,
}: {
    subtopic: Subtopic;
    onDone: () => void;
}) {
    return (
        <Form
            {...retitle.form(subtopic.id)}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex flex-wrap items-end gap-x-5 gap-y-3"
        >
            {({ errors, processing }) => (
                <>
                    <label className="flex flex-col gap-[5px]">
                        <span className="label">
                            Title in the picker · the slug stays {subtopic.slug}
                        </span>
                        <input
                            name="title"
                            defaultValue={subtopic.title}
                            required
                            autoFocus
                            maxLength={120}
                            aria-invalid={errors.title ? true : undefined}
                            className="min-h-11 w-[280px] border border-hairline bg-surface px-2 focus-visible:outline-2 focus-visible:outline-brass"
                        />
                        {errors.title && (
                            <span className="text-[13px] text-wine">
                                {errors.title}
                            </span>
                        )}
                    </label>
                    <button
                        type="submit"
                        disabled={processing}
                        className={BUTTON}
                    >
                        Retitle
                    </button>
                    <button
                        type="button"
                        onClick={onDone}
                        className="label min-h-11 text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Cancel
                    </button>
                </>
            )}
        </Form>
    );
}
