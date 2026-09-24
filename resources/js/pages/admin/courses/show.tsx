import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Fact } from '@/components/admin-controls';
import AdminShell from '@/components/admin-shell';
import ReasonForm from '@/components/reason-form';
import { money } from '@/lib/utils';
import { restore } from '@/routes/admin/catalog';
import { index, remove } from '@/routes/admin/courses';
import { show as user } from '@/routes/admin/users';

/**
 * One course taken apart (spec 17.2.2): what the student asked for and knows,
 * every syllabus drawn, every lesson with what was said in it, every quiz with
 * its key and sittings, the generation requests, and what it all cost. Remove
 * and Restore are here too (17.2.3).
 */

type Removal = {
    id: number;
    own: boolean;
    what: string;
    reason: string | null;
    when: string;
};

type Syllabus = {
    generation: number;
    feedback: string | null;
    approved: string | null;
    created: string;
    syllabus: {
        course_title?: string;
        overview?: string;
        lessons?: { position: number; title: string; objective: string }[];
        final_exam_concepts?: string[];
    };
};

type Message = {
    id: number;
    kind: string;
    speaker: string;
    section: number | null;
    content: string;
};

type Lesson = {
    id: number;
    position: number;
    title: string | null;
    status: string;
    completed: string | null;
    messages: Message[];
};

type Quiz = {
    id: number;
    kind: string;
    lesson: number | null;
    section: number | null;
    questions: {
        id: string;
        prompt: string;
        options: { id: string; text: string }[];
        correct: string | null;
        explanation: string | null;
        concept: string | null;
    }[];
    sittings: {
        sitting: number;
        correct: number;
        total: number;
        answers: Record<string, string>;
    }[];
    open: { sitting: number; ticked: number }[];
};

type Generation = {
    id: number;
    kind: string;
    state: string;
    requested: string;
    failed: string | null;
    failure: string | null;
};

type Usage = {
    id: number;
    when: string;
    feature: string;
    prompt: string;
    model: string;
    lesson: number | null;
    input: number;
    cached: number;
    output: number;
    latency: number;
    cost: number;
    outcome: string | null;
    acting: string | null;
};

export default function AdminCourse({
    course,
    criteria,
    working_knowledge,
    syllabi,
    lessons,
    quizzes,
    generations,
    cost,
    usage,
}: {
    course: {
        id: number;
        student: { id: number; name: string };
        module: string;
        title: string;
        level: number;
        attempt: number;
        standing: string;
        created: string;
        removal: Removal | null;
    };
    criteria: string | null;
    working_knowledge: string | null;
    syllabi: Syllabus[];
    lessons: Lesson[];
    quizzes: Quiz[];
    generations: Generation[];
    cost: {
        total: number;
        features: { feature: string; calls: number; cost: number }[];
    };
    usage: Usage[];
}) {
    const [asking, setAsking] = useState(false);

    return (
        <AdminShell current="courses" title={course.title}>
            <Head title={course.title} />
            <Link
                href={index()}
                className="label mb-6 inline-flex min-h-11 items-center text-muted-ink hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
            >
                ← All courses
            </Link>

            <dl className="mb-6 flex flex-wrap gap-x-10 gap-y-3">
                <Fact term="Student">
                    <Link
                        href={user(course.student.id)}
                        className="underline-offset-4 hover:underline"
                    >
                        {course.student.name}
                    </Link>
                </Fact>
                <Fact term="Module">{course.module}</Fact>
                <Fact term="Level">{course.level}</Fact>
                <Fact term="Attempt">{course.attempt}</Fact>
                <Fact term="Standing">{course.standing}</Fact>
                <Fact term="Created">{course.created}</Fact>
                <Fact term="Cost">{money.format(cost.total)}</Fact>
            </dl>

            <div className="mb-11">
                {course.removal && (
                    <p className="mb-3 max-w-[62ch]">
                        <span className="label mr-2 text-wine">Removed</span>
                        {course.removal.own
                            ? `on ${course.removal.when}`
                            : `with its ${course.removal.what}, on ${course.removal.when} — restore that from Catalog → Removed`}
                        {course.removal.reason && (
                            <em> · {course.removal.reason}</em>
                        )}
                    </p>
                )}
                {asking ? (
                    course.removal ? (
                        <ReasonForm
                            form={restore.form(course.removal.id)}
                            verb="Restore"
                            consequence="Brings back this course and its documents."
                            onDone={() => setAsking(false)}
                        />
                    ) : (
                        <ReasonForm
                            form={remove.form(course.id)}
                            verb="Remove course"
                            consequence={`Hides this course and its documents from ${course.student.name}, and closes anything it was waiting on. Their working knowledge stays.`}
                            onDone={() => setAsking(false)}
                        />
                    )
                ) : (
                    (!course.removal || course.removal.own) && (
                        <button
                            type="button"
                            onClick={() => setAsking(true)}
                            className="label min-h-11 border border-wine px-3 text-wine hover:bg-wine hover:text-ground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                        >
                            {course.removal ? 'Restore' : 'Remove'}
                        </button>
                    )
                )}
            </div>

            <Heading>Criteria and working knowledge</Heading>
            <Fold summary="COURSE_CRITERIA.md">
                <Text>{criteria ?? 'Not written yet.'}</Text>
            </Fold>
            <Fold summary="WORKING_KNOWLEDGE.md">
                <Text>{working_knowledge ?? 'Not written yet.'}</Text>
            </Fold>

            <Heading>Syllabus</Heading>
            {syllabi.length === 0 && <Empty>No syllabus drawn yet.</Empty>}
            {syllabi.map((draft) => (
                <Fold
                    key={draft.generation}
                    summary={
                        <>
                            Generation {draft.generation}
                            <span className="text-muted-ink">
                                {draft.created}
                            </span>
                            {draft.approved && (
                                <span className="label text-brass">
                                    Approved {draft.approved}
                                </span>
                            )}
                        </>
                    }
                >
                    {draft.feedback && (
                        <p className="mb-3">
                            <span className="label mr-2">Asked for</span>
                            <em>{draft.feedback}</em>
                        </p>
                    )}
                    <p className="font-semibold">
                        {draft.syllabus.course_title}
                    </p>
                    <p className="mb-3 max-w-[70ch] text-muted-ink">
                        {draft.syllabus.overview}
                    </p>
                    <ol className="mb-3 flex flex-col gap-1.5">
                        {draft.syllabus.lessons?.map((lesson) => (
                            <li key={lesson.position}>
                                {lesson.position}. {lesson.title}
                                <span className="block text-[14px] text-muted-ink">
                                    {lesson.objective}
                                </span>
                            </li>
                        ))}
                    </ol>
                    {draft.syllabus.final_exam_concepts && (
                        <p className="text-[14px] text-muted-ink">
                            Exam weights:{' '}
                            {draft.syllabus.final_exam_concepts.join(' · ')}
                        </p>
                    )}
                </Fold>
            ))}

            <Heading>Lessons</Heading>
            {lessons.length === 0 && <Empty>No lessons until approval.</Empty>}
            {lessons.map((lesson) => (
                <Fold
                    key={lesson.id}
                    summary={
                        <>
                            {lesson.position}. {lesson.title ?? 'Untitled'}
                            <span className="text-muted-ink">
                                {lesson.status}
                                {lesson.completed && ` ${lesson.completed}`}
                            </span>
                        </>
                    }
                >
                    {lesson.messages.length === 0 && (
                        <Empty>Nothing taught yet.</Empty>
                    )}
                    {lesson.messages.map((message, i) => (
                        <div key={message.id} className="mb-3">
                            {message.section !==
                                lesson.messages[i - 1]?.section && (
                                <p className="label mt-4 mb-2 border-b border-hairline pb-1">
                                    Section {message.section}
                                </p>
                            )}
                            <p className="label text-muted-ink">
                                {message.speaker} · {message.kind}
                            </p>
                            <Text>{message.content}</Text>
                        </div>
                    ))}
                </Fold>
            ))}

            <Heading>Checkpoints, homework and exams</Heading>
            {quizzes.length === 0 && <Empty>No quizzes yet.</Empty>}
            {quizzes.map((quiz) => (
                <Fold
                    key={quiz.id}
                    summary={
                        <>
                            <span className="label">{quiz.kind}</span>
                            {quiz.lesson && `Lesson ${quiz.lesson}`}
                            {quiz.section && `, section ${quiz.section}`}
                            <span className="text-muted-ink">
                                {quiz.sittings.length === 0
                                    ? 'not sat'
                                    : quiz.sittings
                                          .map((s) => `${s.correct}/${s.total}`)
                                          .join(' · ')}
                                {quiz.open.map(
                                    (o) =>
                                        ` · sitting ${o.sitting} open, ${o.ticked} ticked`,
                                )}
                            </span>
                        </>
                    }
                >
                    {quiz.sittings.map((s) => (
                        <p
                            key={s.sitting}
                            className="text-[14px] text-muted-ink"
                        >
                            Sitting {s.sitting}: {s.correct} of {s.total} right
                            · {Object.keys(s.answers).length} answered
                        </p>
                    ))}
                    <ol className="mt-3 flex flex-col gap-4">
                        {quiz.questions.map((question) => (
                            <li key={question.id}>
                                <p>{question.prompt}</p>
                                <ul className="mt-1 ml-4 text-[14px]">
                                    {question.options.map((option) => (
                                        <li
                                            key={option.id}
                                            className={
                                                option.id === question.correct
                                                    ? 'text-brass'
                                                    : undefined
                                            }
                                        >
                                            {option.id === question.correct
                                                ? '✓ '
                                                : '· '}
                                            {option.text}
                                            {quiz.sittings
                                                .filter(
                                                    (s) =>
                                                        s.answers[
                                                            question.id
                                                        ] === option.id,
                                                )
                                                .map((s) => (
                                                    <span
                                                        key={s.sitting}
                                                        className="label ml-2 text-wine"
                                                    >
                                                        chosen, sitting{' '}
                                                        {s.sitting}
                                                    </span>
                                                ))}
                                        </li>
                                    ))}
                                </ul>
                                {question.explanation && (
                                    <p className="mt-1 ml-4 text-[14px] text-muted-ink">
                                        {question.explanation}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </Fold>
            ))}

            <Heading>Generation requests</Heading>
            {generations.length === 0 ? (
                <Empty>None kept.</Empty>
            ) : (
                <Table head={['Kind', 'State', 'Requested', 'Failure']}>
                    {generations.map((request) => (
                        <tr key={request.id}>
                            <td>{request.kind}</td>
                            <td>{request.state}</td>
                            <td className="whitespace-nowrap text-muted-ink">
                                {request.requested}
                            </td>
                            <td>
                                {request.failure ? (
                                    <>
                                        <em>{request.failure}</em>
                                        <span className="block text-[13px] text-muted-ink">
                                            {request.failed}
                                        </span>
                                    </>
                                ) : (
                                    '—'
                                )}
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            <Heading>Cost by feature</Heading>
            {cost.features.length === 0 ? (
                <Empty>
                    No calls name this course. Calls from before usage rows
                    named their course are on the student’s page.
                </Empty>
            ) : (
                <Table head={['Feature', 'Calls', 'Cost']}>
                    {cost.features.map((row) => (
                        <tr key={row.feature}>
                            <td>{row.feature}</td>
                            <td className="tabular-nums">{row.calls}</td>
                            <td className="tabular-nums">
                                {money.format(row.cost)}
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            <Heading>Usage rows</Heading>
            {usage.length === 0 ? (
                <Empty>None.</Empty>
            ) : (
                <Table
                    head={[
                        'When',
                        'Feature',
                        'Model',
                        'Lesson',
                        'In',
                        'Cached',
                        'Out',
                        'Latency',
                        'Cost',
                        'Outcome',
                    ]}
                >
                    {usage.map((row) => (
                        <tr key={row.id}>
                            <td className="whitespace-nowrap text-muted-ink">
                                {row.when}
                            </td>
                            <td>
                                {row.feature}
                                <span className="block text-[13px] text-muted-ink">
                                    {row.prompt}
                                    {row.acting && (
                                        <span className="text-wine">
                                            {' '}
                                            · acting: {row.acting}
                                        </span>
                                    )}
                                </span>
                            </td>
                            <td>{row.model}</td>
                            <td>{row.lesson ?? '—'}</td>
                            <td className="tabular-nums">{row.input}</td>
                            <td className="tabular-nums">{row.cached}</td>
                            <td className="tabular-nums">{row.output}</td>
                            <td className="tabular-nums">{row.latency} ms</td>
                            <td className="tabular-nums">
                                {money.format(row.cost)}
                            </td>
                            <td>{row.outcome}</td>
                        </tr>
                    ))}
                </Table>
            )}
        </AdminShell>
    );
}

function Heading({ children }: { children: ReactNode }) {
    return <h2 className="label mt-11 mb-3">{children}</h2>;
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="text-muted-ink">{children}</p>;
}

/** A long thing, closed until asked for. */
function Fold({
    summary,
    children,
}: {
    summary: ReactNode;
    children: ReactNode;
}) {
    return (
        <details className="border-b border-hairline py-2">
            <summary className="flex min-h-11 cursor-pointer flex-wrap items-center gap-x-3">
                {summary}
            </summary>
            <div className="pb-4 pl-4">{children}</div>
        </details>
    );
}

/** Model-written prose, as it was written. */
function Text({ children }: { children: ReactNode }) {
    return (
        <pre className="overflow-x-auto border border-hairline bg-surface p-3 font-mono text-[13px] leading-[1.6] whitespace-pre-wrap">
            {children}
        </pre>
    );
}

function Table({ head, children }: { head: string[]; children: ReactNode }) {
    return (
        <div className="overflow-x-auto">
            <table className="ledger">
                <thead>
                    <tr>
                        {head.map((column) => (
                            <th key={column}>{column}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}
