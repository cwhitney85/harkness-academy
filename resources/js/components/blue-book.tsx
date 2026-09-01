import { useState } from 'react';
import type { ReactNode } from 'react';

import Crest from '@/components/crest';
import type { QuizAnswers, QuizQuestion } from '@/types';

/**
 * The examination blue book — spec 8.4.3, direction D22.
 *
 * **The final exam is set on this and nothing else is.** The book states D20's
 * rule as an object rather than as a warning dialog: it is collected whole and
 * marked once, nothing is disclosed until it is handed in, and a re-sit is the
 * same examination. It arrives closed and is clicked open, which is the one
 * deliberately animated moment in the app (8.4.4).
 *
 * Pre:  `open` is the server's truth — the exam row exists — not a wish. The
 *       screen POSTs, and flips this when the exam comes back.
 * Post: on a re-sit the leaf is what renders: no cover ceremony is performed
 *       and no transition runs, because the ceremony has already happened once
 *       and repeating it would make a second sitting feel like a first.
 * Invariant: no question here can carry an answer key. The exam never
 *       discloses one (QuizKind::disclosesAnswerKey), and QuizQuestion has no
 *       field to put one in.
 */

export default function BlueBook({
    title,
    terms,
    student,
    course,
    date,
    questions,
    answers,
    onAnswer,
    open,
    resit = false,
    pending = false,
    onOpen,
    children,
}: {
    /** The course being examined, shown beside the closed book. */
    title: string;
    /** The exam's terms, in the student's words: what handing in means, what
     *  passing takes, and that a re-sit is the same examination. */
    terms: string;
    /** Written on the cover's rules in the student's own pen. */
    student: string;
    course: string;
    date: string;
    questions: QuizQuestion[];
    answers: QuizAnswers;
    onAnswer: (questionId: string, optionId: string) => void;
    /** Whether the exam exists yet. False renders the closed book. */
    open: boolean;
    /** Whether this is a second or later sitting (spec 8.4.4). */
    resit?: boolean;
    /** The exam is being written — opening spends the costliest call we make,
     *  so the button says so rather than appearing to do nothing. */
    pending?: boolean;
    onOpen: () => void;
    /** What sits below the book: handing in, and how far the student has got. */
    children?: ReactNode;
}) {
    const closed = !resit && !open;

    // Whether the book was ever shut in front of this student. A reload
    // mid-sitting and a re-sit both arrive at an already-open book, and
    // opening one that is open is not a ceremony — it is a flicker.
    const [wasShut] = useState(closed);
    const still = useStillness();
    const ceremony = wasShut && !closed && !still;

    return (
        <div className="flex w-full grow flex-col items-center bg-desk px-5 py-8">
            {closed ? (
                <ClosedBook
                    title={title}
                    terms={terms}
                    pending={pending}
                    onOpen={onOpen}
                />
            ) : (
                <>
                    <div
                        className="mx-auto flex w-full max-w-[1020px] grow flex-col md:flex-row"
                        style={{ boxShadow: 'var(--shadow-spread)' }}
                    >
                        <FoldedCover
                            student={student}
                            course={course}
                            date={date}
                            terms={terms}
                            ceremony={ceremony}
                        />
                        <Leaf
                            questions={questions}
                            answers={answers}
                            onAnswer={onAnswer}
                            ceremony={ceremony}
                        />
                    </div>
                    {children && (
                        <div className="mx-auto w-full max-w-[1020px] py-4">
                            {children}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

/**
 * The book as it is handed to you: shut, squared off by a degree and a half,
 * with two leaves showing under the cover.
 */
function ClosedBook({
    title,
    terms,
    pending,
    onOpen,
}: {
    title: string;
    terms: string;
    pending: boolean;
    onOpen: () => void;
}) {
    return (
        <div className="flex grow flex-col items-center justify-center gap-10 py-6">
            <div className="relative aspect-[372/486] w-full max-w-[372px] rotate-[-1.4deg]">
                <div className="absolute inset-0 translate-x-[5px] translate-y-[5px] bg-[oklch(0.965_0.006_240)]" />
                <div className="absolute inset-0 translate-x-[3px] translate-y-[3px] bg-[oklch(0.985_0.004_240)]" />
                <div
                    className="relative flex h-full flex-col bg-book-cover px-8 py-[34px] text-book-ink"
                    style={{
                        boxShadow: 'var(--shadow-book), var(--shadow-spine)',
                    }}
                >
                    <div className="flex grow flex-col gap-6 border-[1.5px] border-book-ink px-[22px] py-[26px]">
                        <CoverHead size={33} />
                        <CoverRules
                            gap="2.9"
                            width={['150px', '138px', '156px']}
                        />
                        <div className="mt-auto flex justify-center">
                            <Crest variant="outline" className="h-10 w-auto" />
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex max-w-[470px] flex-col items-center gap-4 text-center">
                <h1 className="display text-[34px] leading-[1.15]">{title}</h1>
                <p className="text-muted-ink">{terms}</p>
                <button
                    type="button"
                    onClick={onOpen}
                    disabled={pending}
                    className="mt-1.5 min-h-[44px] cursor-pointer bg-wine px-[38px] py-3.5 text-[15px] tracking-[0.13em] text-surface uppercase disabled:opacity-70"
                >
                    {pending ? 'Opening…' : 'Open the book'}
                </button>
            </div>
        </div>
    );
}

/**
 * The cover once the book is open: laid back to the left of the leaf, carrying
 * the name block the student filled in and the terms they agreed to by opening
 * it. On a phone there is no room beside the leaf, so it sits above it — the
 * same two objects in one column.
 */
function FoldedCover({
    student,
    course,
    date,
    terms,
    ceremony,
}: {
    student: string;
    course: string;
    date: string;
    terms: string;
    ceremony: boolean;
}) {
    return (
        <div
            className={`flex shrink-0 flex-col bg-book-cover px-[26px] py-[30px] text-book-ink md:w-[318px] ${
                ceremony ? 'book-cover-settle' : ''
            }`}
            style={{ boxShadow: 'var(--shadow-cover-gutter)' }}
        >
            <div className="flex grow flex-col gap-5 border-[1.5px] border-book-ink px-[18px] py-[22px]">
                <CoverHead size={28} />
                <CoverRules
                    gap="2.7"
                    width={['126px', '114px', '132px']}
                    written={[student, course, date]}
                />
                <p className="mt-auto text-[11.5px] leading-[1.75] tracking-[0.04em] opacity-85">
                    {terms}
                </p>
            </div>
        </div>
    );
}

function CoverHead({ size }: { size: number }) {
    return (
        <div className="text-center">
            <p
                className="display leading-[1.1] tracking-[0.02em]"
                style={{ fontSize: `${size}px` }}
            >
                Examination
                <br />
                Book
            </p>
            <div className="mx-6 mt-3 h-px bg-book-ink opacity-60" />
        </div>
    );
}

/** NAME / COURSE / DATE, printed empty and filled in by hand. */
function CoverRules({
    gap,
    width,
    written = ['', '', ''],
}: {
    gap: string;
    width: [string, string, string];
    written?: [string, string, string] | string[];
}) {
    const rules: [string, string, number][] = [
        ['Name', width[0], -1.5],
        ['Course', width[1], 0],
        ['Date', width[2], 0.8],
    ];

    return (
        <div
            className="text-[13px] tracking-[0.1em] uppercase"
            style={{ lineHeight: gap }}
        >
            {rules.map(([label, rule, tilt], index) => (
                <div key={label}>
                    {label}{' '}
                    <span
                        className="relative inline-block border-b border-book-ink align-baseline"
                        style={{ width: `min(${rule}, 40vw)` }}
                    >
                        {written[index] && (
                            <span
                                className="hand absolute top-[-19px] left-1.5 whitespace-nowrap text-pen normal-case"
                                style={{
                                    transform: `rotate(${tilt}deg)`,
                                    letterSpacing: 0,
                                }}
                            >
                                {written[index]}
                            </span>
                        )}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * The stapled interior (spec 8.4.3).
 *
 * Every line of body type here is set on `--leaf-rhythm`, and the rules are
 * drawn on the same number, so type sits on a rule rather than near one.
 * Anything between two lines is a whole multiple of the rhythm; a single
 * arbitrary margin would push everything below it off the rules for good.
 */
function Leaf({
    questions,
    answers,
    onAnswer,
    ceremony,
}: {
    questions: QuizQuestion[];
    answers: QuizAnswers;
    onAnswer: (questionId: string, optionId: string) => void;
    ceremony: boolean;
}) {
    return (
        <div
            className={`leaf relative grow overflow-hidden pt-[30px] pr-5 pb-[30px] pl-12 sm:pr-[46px] sm:pl-[82px] ${
                ceremony ? 'book-leaf-open' : ''
            }`}
            style={{ boxShadow: 'var(--shadow-leaf-gutter)' }}
        >
            {/* The red rule the margin is measured from. */}
            <div className="absolute top-0 bottom-0 left-8 w-px bg-leaf-margin opacity-60 sm:left-[62px]" />

            {[4, 13, 22].map((rules) => (
                <div
                    key={rules}
                    aria-hidden
                    className="absolute left-[22px] h-[3px] w-3 bg-[oklch(0.58_0.01_250)]"
                    style={{ top: `calc(${rules} * var(--leaf-rhythm) + 6px)` }}
                />
            ))}

            <div className="leaf-line flex items-baseline gap-3">
                <p className="label text-[oklch(0.50_0.030_250)]">
                    Final examination
                </p>
                <span className="grow" />
                <p className="label text-[oklch(0.50_0.030_250)]">
                    {questions.length} question
                    {questions.length === 1 ? '' : 's'}
                </p>
            </div>

            <ol>
                {questions.map((question, index) => (
                    <li key={question.id} className="flex gap-4 pt-[30px]">
                        <span
                            aria-hidden
                            className="leaf-line w-[26px] shrink-0"
                        >
                            {index + 1}.
                        </span>
                        <div className="grow">
                            <p className="leaf-line" id={`x-${question.id}`}>
                                {question.prompt}
                            </p>
                            <div
                                role="radiogroup"
                                aria-labelledby={`x-${question.id}`}
                            >
                                {question.options.map((option) => {
                                    const ticked =
                                        answers[question.id] === option.id;

                                    return (
                                        <button
                                            key={option.id}
                                            type="button"
                                            role="radio"
                                            aria-checked={ticked}
                                            onClick={() =>
                                                onAnswer(question.id, option.id)
                                            }
                                            className="leaf-option relative block w-full cursor-pointer pl-7 text-left"
                                        >
                                            <span
                                                aria-hidden
                                                className={`absolute top-[9px] left-0 h-[11px] w-[11px] border ${
                                                    ticked
                                                        ? 'border-[1.5px] border-pen bg-pen'
                                                        : 'border-[oklch(0.48_0.030_250)]'
                                                }`}
                                            />
                                            {option.text}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}

/**
 * Whether the viewer has asked for less movement. The blue book is the one
 * place in the app that animates, so it is the one place that has to ask.
 *
 * Read once, at the first render rather than in an effect: the answer decides
 * what the book's opening frame looks like, and an effect would arrive after
 * that frame had already been painted.
 */
function useStillness(): boolean {
    const [still] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    return still;
}
