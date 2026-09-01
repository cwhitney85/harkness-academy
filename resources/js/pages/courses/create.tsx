import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import PipelineShell from '@/components/pipeline-shell';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';

/**
 * Screen S2 — starting a course (spec 8.7.1, 8.7.6).
 *
 * One form, two doors, because 4.1 made picking a preset and typing a name the
 * same act: both resolve to the same slug, and a screen with two separate
 * flows would be describing a difference the engine does not have. The catalog
 * is offered first because it is a suggestion; the free-text field sits under
 * it under the same module, which is where the shared slug comes from.
 *
 * Skill level is asked here and never framed as a price (4.5.6): 101 is longer
 * than 301, and the copy says so rather than letting "Introductory" read as
 * "less".
 */

type Subtopic = { id: number; slug: string; title: string };
type Module = { slug: string; title: string; subtopics: Subtopic[] };
type Level = { value: number; title: string };

/** D7b's question, asked before a second attempt is created (8.7.6). */
type Resumable = {
    course_id: number;
    subtopic_title: string;
    level: string;
    attempt: number;
    set_aside_on: string | null;
    resume_url: string;
};

/** The subtopic's one open slot is taken — the double tap and the second tab. */
type Occupied = {
    course_id: number;
    subtopic_title: string;
    level: string;
    continue_url: string;
};

/**
 * What each level means, in pedagogy. Never in money: the plan decides
 * economics and skill level decides nothing about it (spec 4.5.6).
 */
const LEVEL_NOTES: Record<number, string> = {
    101: 'Starting from nothing. The longest of the three — beginners need the most scaffolding.',
    201: 'You know the ground and want to be fluent on it.',
    301: 'You are confident, and want the parts that are actually hard.',
};

export default function CreateCourse({
    modules,
    levels,
    resumable,
    occupied,
}: {
    modules: Module[];
    levels: Level[];
    resumable: Resumable | null;
    occupied: Occupied | null;
}) {
    const [module, setModule] = useState<Module | null>(null);

    const form = useForm({
        source: 'catalog',
        module_slug: '',
        subtopic_id: null as number | null,
        name: '',
        level: 101,
        start_over: false as boolean,
    });

    const chosen = module?.subtopics.find(
        (subtopic) => subtopic.id === form.data.subtopic_id,
    );

    const ready =
        module !== null &&
        (form.data.subtopic_id !== null || form.data.name.trim() !== '');

    function submit(startOver = false) {
        form.transform((data) => ({
            ...data,
            source: data.subtopic_id === null ? 'free_text' : 'catalog',
            start_over: startOver,
        }));

        form.post('/courses', { preserveScroll: true });
    }

    return (
        <PipelineShell step="start">
            <Head title="Start a course" />

            <div className="measure py-8 sm:py-12">
                <h1 className="display text-[38px] leading-[1.07] sm:text-[43px]">
                    Start a course
                </h1>
                <p className="mt-4 mb-9">
                    Pick a subject and say how deep you want to go. Two short
                    conversations follow — what you already know, and what you
                    want out of this — and a syllabus is drawn up from them for
                    you to approve.
                </p>

                {resumable && (
                    <CarryOnOrStartOver
                        course={resumable}
                        onStartOver={() => submit(true)}
                        disabled={form.processing}
                    />
                )}

                {occupied && <AlreadyOpen course={occupied} />}

                <Field label="Choose a subject">
                    <div className="flex flex-wrap gap-2">
                        {modules.map((candidate) => (
                            <button
                                key={candidate.slug}
                                type="button"
                                onClick={() => {
                                    setModule(candidate);
                                    form.setData((data) => ({
                                        ...data,
                                        module_slug: candidate.slug,
                                        subtopic_id: null,
                                        name: '',
                                    }));
                                }}
                                className={[
                                    'flex h-11 items-center border px-4 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                                    candidate.slug === module?.slug
                                        ? 'border-wine bg-wine text-surface'
                                        : 'border-hairline bg-surface hover:border-brass',
                                ].join(' ')}
                            >
                                {candidate.title}
                            </button>
                        ))}
                    </div>
                </Field>

                {module && (
                    <>
                        <Field label={`In ${module.title}`}>
                            <ul className="border-t border-hairline">
                                {module.subtopics.map((subtopic) => (
                                    <li key={subtopic.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                form.setData((data) => ({
                                                    ...data,
                                                    subtopic_id: subtopic.id,
                                                    name: '',
                                                }))
                                            }
                                            className={[
                                                'flex min-h-11 w-full items-center gap-3 border-b border-hairline px-3 text-left text-[17px] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass',
                                                subtopic.id === chosen?.id
                                                    ? 'bg-surface'
                                                    : 'hover:bg-surface',
                                            ].join(' ')}
                                        >
                                            <span
                                                className={[
                                                    'h-3.5 w-[3px] flex-none',
                                                    subtopic.id === chosen?.id
                                                        ? 'bg-wine'
                                                        : 'bg-transparent',
                                                ].join(' ')}
                                            />
                                            {subtopic.title}
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            <label className="mt-5 block">
                                <span className="label">
                                    Or name it yourself
                                </span>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    maxLength={120}
                                    placeholder={`Anything else under ${module.title}…`}
                                    onChange={(event) =>
                                        form.setData((data) => ({
                                            ...data,
                                            name: event.target.value,
                                            subtopic_id: null,
                                        }))
                                    }
                                    className="mt-2 h-11 w-full border border-hairline bg-surface px-3.5 text-[17px] placeholder:text-muted-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass"
                                />
                                <InputError message={form.errors.name} />
                            </label>
                        </Field>

                        <Field label="How deep">
                            <div className="flex flex-col gap-2">
                                {levels.map((level) => (
                                    <button
                                        key={level.value}
                                        type="button"
                                        onClick={() =>
                                            form.setData('level', level.value)
                                        }
                                        className={[
                                            'flex min-h-11 w-full flex-col items-start gap-0.5 border px-4 py-2.5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                                            level.value === form.data.level
                                                ? 'border-wine bg-surface'
                                                : 'border-hairline hover:border-brass',
                                        ].join(' ')}
                                    >
                                        <span className="text-[17px]">
                                            {level.title}{' '}
                                            <span className="text-muted-ink">
                                                · {level.value}
                                            </span>
                                        </span>
                                        <span className="text-[15px] leading-snug text-muted-ink">
                                            {LEVEL_NOTES[level.value]}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </>
                )}

                <InputError message={form.errors.subtopic_id} />
                <InputError message={form.errors.module_slug} />

                <div className="mt-10 flex items-center gap-5">
                    <Button
                        size="action"
                        disabled={!ready || form.processing}
                        onClick={() => submit(false)}
                    >
                        Begin
                    </Button>
                    <span className="label">
                        {chosen
                            ? `${chosen.title} · ${form.data.level}`
                            : form.data.name.trim()
                              ? `${form.data.name.trim()} · ${form.data.level}`
                              : 'Pick a subject to begin'}
                    </span>
                </div>
            </div>
        </PipelineShell>
    );
}

/** A label, a rule, and whatever it introduces. */
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <section className="mt-8">
            <h2 className="label border-b border-brass/50 pb-1.5 text-brass">
                {label}
            </h2>
            <div className="mt-4">{children}</div>
        </section>
    );
}

/**
 * D7b's question, in D7b's words (spec 8.7.6).
 *
 * Asked instead of creating a second course, because a second attempt cannot
 * be merged back into the one it replaced — and starting over is deliberately
 * still on offer, because sometimes that is what the student wants.
 */
function CarryOnOrStartOver({
    course,
    onStartOver,
    disabled,
}: {
    course: Resumable;
    onStartOver: () => void;
    disabled: boolean;
}) {
    return (
        <aside className="mb-8 border border-t-[3px] border-hairline border-t-wine bg-surface px-5 py-5 sm:px-6">
            <h2 className="label text-wine">You have been here before</h2>
            <p className="mt-3">
                You set aside {course.subtopic_title} at the {course.level}{' '}
                level
                {course.set_aside_on ? ` on ${course.set_aside_on}` : ''}. Pick
                up where you left off, or start from scratch?
            </p>
            <div className="mt-5 flex flex-wrap items-center gap-3">
                <Button
                    size="action"
                    disabled={disabled}
                    onClick={() =>
                        router.post(
                            course.resume_url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Pick up where I left off
                </Button>
                <Button
                    size="action"
                    variant="outline"
                    disabled={disabled}
                    onClick={onStartOver}
                >
                    Start from scratch
                </Button>
            </div>
            <p className="label mt-4">
                Starting over keeps the old course; it becomes attempt{' '}
                {course.attempt + 1}.
            </p>
        </aside>
    );
}

/** The subtopic's one open slot is already taken (8.6). */
function AlreadyOpen({ course }: { course: Occupied }) {
    return (
        <aside className="mb-8 border border-t-[3px] border-hairline border-t-brass bg-surface px-5 py-5 sm:px-6">
            <h2 className="label text-brass">Already underway</h2>
            <p className="mt-3">
                {course.subtopic_title} at the {course.level} level is already
                open. One course at a time in a subject, so that both are not
                writing down what you know at once.
            </p>
            <div className="mt-5">
                <Button
                    size="action"
                    onClick={() => router.visit(course.continue_url)}
                >
                    Carry on with it
                </Button>
            </div>
        </aside>
    );
}
