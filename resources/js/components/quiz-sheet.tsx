import type { ReactNode } from 'react';

import type { QuizAnswers, QuizQuestion } from '@/types';

/**
 * The photocopied classroom quiz sheet — spec 8.4.2, direction D22.
 *
 * **This is the only surface practice is ever set on.** Homework and all three
 * refresher grains render through here and differ in one thing: the `scope`
 * printed at the head of the sheet. A quiz drawn on the paper column is what
 * made quizzes read as corporate training, so the photocopy stock, the
 * typewriter face, the name-and-date block, the directions line and the sheet
 * being a third of a degree off square are all load-bearing, not decoration.
 *
 * Pre:  every question carries three or four options (QuestionSchema) and no
 *       answer key — this component has no way to draw one, which is the
 *       point (spec 5.2.3).
 * Post: exactly the options in `answers` are circled; a click reports the pair
 *       and changes nothing here. Grading, disclosure and what happens next
 *       belong to the screen, which owns the state.
 *
 * The final exam does **not** come through here. It is a blue book (BlueBook).
 */

const LETTERS = ['a', 'b', 'c', 'd'];

/** The constant half of the directions line; a screen may say more. */
const DIRECTIONS = 'Circle the letter of the best answer.';

/**
 * The longest an option can be and still share a line with its siblings.
 * «La película está aburriendo.» is 29 characters and wants its own line;
 * «están» does not.
 */
const INLINE_OPTION = 24;

function inline(question: QuizQuestion): boolean {
    return question.options.every(
        (option) => option.text.length <= INLINE_OPTION,
    );
}

export default function QuizSheet({
    course,
    scope,
    student,
    date,
    directions = DIRECTIONS,
    questions,
    answers,
    onAnswer,
    children,
}: {
    /** The course this practice belongs to, printed across the head. */
    course: string;
    /** What this sheet covers — the one thing homework and the three
     *  refresher grains differ in (spec 8.4.2). */
    scope: string;
    /** Written on the NAME rule in the student's own pen. */
    student: string;
    /** Written on the DATE rule, already formatted: a sheet is dated the day
     *  it is handed out, and the screen knows which day that was. */
    date: string;
    directions?: string;
    questions: QuizQuestion[];
    answers: QuizAnswers;
    onAnswer: (questionId: string, optionId: string) => void;
    /** What sits below the sheet, on the ground: the action and its counts. */
    children?: ReactNode;
}) {
    return (
        // The sheet is off square, so it is 5px wider than its box at the
        // bottom corners. The padding here is what stops that becoming a
        // horizontal scrollbar at 390px (spec 8.5.2).
        <div className="flex w-full flex-col items-center px-5">
            <div
                className="w-full max-w-[790px] rotate-[-0.35deg] bg-sheet px-6 py-8 sm:px-[54px] sm:py-[38px]"
                style={{ boxShadow: 'var(--shadow-sheet)' }}
            >
                <header className="font-sheet text-[14px] leading-[1.75] text-toner">
                    <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <h1 className="grow text-[15px] font-bold tracking-[0.06em] uppercase">
                            {course}
                        </h1>
                        <p className="text-[12.5px] tracking-[0.04em] uppercase">
                            {scope}
                        </p>
                    </div>

                    <div className="mt-[9px] mb-3 border-b-2 border-toner" />

                    <div className="flex flex-wrap gap-x-[34px] gap-y-1">
                        <p>
                            NAME{' '}
                            <PenRule
                                text={student}
                                width="min(180px, 42vw)"
                                tilt={-1.5}
                            />
                        </p>
                        <p>
                            DATE{' '}
                            <PenRule
                                text={date}
                                width="min(110px, 30vw)"
                                tilt={-1}
                            />
                        </p>
                    </div>

                    <p className="mt-[11px] text-[13px]">
                        DIRECTIONS: {directions}
                    </p>

                    <div className="mt-3 border-b border-toner opacity-50" />
                </header>

                <ol className="mt-5 font-sheet text-[14.5px] leading-[1.95] text-toner">
                    {questions.map((question, index) => (
                        <li
                            key={question.id}
                            className="mb-[17px] flex gap-3 last:mb-0"
                        >
                            <span
                                aria-hidden
                                className="w-6 shrink-0 font-bold"
                            >
                                {index + 1}.
                            </span>
                            <div className="grow">
                                <p id={`q-${question.id}`}>{question.prompt}</p>
                                <div
                                    role="radiogroup"
                                    aria-labelledby={`q-${question.id}`}
                                    // Short answers sit across one line, the
                                    // way a printed sheet sets them; a
                                    // question whose options are sentences
                                    // gets one to a line instead of a ragged
                                    // two-and-one.
                                    className={
                                        inline(question)
                                            ? 'flex flex-wrap gap-x-10'
                                            : 'flex flex-col'
                                    }
                                >
                                    {question.options.map(
                                        (option, position) => (
                                            <button
                                                key={option.id}
                                                type="button"
                                                role="radio"
                                                aria-checked={
                                                    answers[question.id] ===
                                                    option.id
                                                }
                                                onClick={() =>
                                                    onAnswer(
                                                        question.id,
                                                        option.id,
                                                    )
                                                }
                                                // 44px is the touch minimum
                                                // (spec 8.5.4); the sheet's own
                                                // line is already 28px of it.
                                                className="relative inline-flex min-h-[44px] cursor-pointer items-center px-[7px] text-left"
                                            >
                                                {answers[question.id] ===
                                                    option.id && (
                                                    <PenCircle
                                                        tilt={
                                                            index % 2 === 0
                                                                ? -3
                                                                : 1.5
                                                        }
                                                    />
                                                )}
                                                <span>
                                                    {LETTERS[position]}){' '}
                                                    {option.text}
                                                </span>
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>

            {children && (
                <div className="w-full max-w-[790px] py-4">{children}</div>
            )}
        </div>
    );
}

/**
 * A printed rule with something written on it in the student's hand.
 *
 * The pen sits above the rule rather than on it, the way writing on a form
 * does — the rule is where the baseline was meant to be, not where the ink is.
 */
function PenRule({
    text,
    width,
    tilt,
}: {
    text: string;
    width: string;
    tilt: number;
}) {
    return (
        <span
            className="relative inline-block border-b border-toner align-baseline"
            style={{ minWidth: width }}
        >
            <span
                className="hand absolute top-[-19px] left-2 whitespace-nowrap text-pen"
                style={{ transform: `rotate(${tilt}deg)` }}
            >
                {text}
            </span>
        </span>
    );
}

/**
 * The circle round a chosen letter — drawn, not printed, so it is the student's
 * mark and not the sheet's.
 *
 * The artboard sizes one of these per option in pixels; here it stretches to
 * whatever the option turned out to be, with `non-scaling-stroke` keeping the
 * nib the same width whether the answer is «es» or a full sentence.
 */
function PenCircle({ tilt }: { tilt: number }) {
    return (
        <svg
            aria-hidden
            className="pointer-events-none absolute inset-0 h-full w-full"
            viewBox="0 0 100 40"
            preserveAspectRatio="none"
        >
            <ellipse
                cx={50}
                cy={20}
                rx={47}
                ry={15}
                fill="none"
                stroke="var(--pen)"
                strokeWidth={1.7}
                vectorEffect="non-scaling-stroke"
                transform={`rotate(${tilt} 50 20)`}
            />
        </svg>
    );
}
