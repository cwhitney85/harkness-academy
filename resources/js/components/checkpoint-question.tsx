import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import type { GradedAnswer, QuizQuestion } from '@/types';

/**
 * How far the questions sit from the prose's own left edge on desktop: the
 * hand's 132px margin and the 18px gutter beside it. Anything that has to line
 * up with the questions rather than with the prose — the screen's Continue
 * button, which follows the last Answer (8.8.8) — clears the same width, so
 * the two numbers below are the only place it is stated.
 *
 * A margin, not padding: `measure` already spends its padding on the gutter
 * that keeps prose off the edge of a phone, and overriding that would land the
 * button 20px short of the questions rather than under them.
 */
export const questionsInset = 'md:ml-[150px]';

/**
 * The in-lesson checkpoint — spec 8.8.3, direction D23, artboard
 * `CheckpointOptions` treatment C.
 *
 * **No container.** The tutor interrupts from the margin: the question is
 * posed in the hand, the options sit under a brass rule, and the prose either
 * side of it is uninterrupted. D23 rejected the card precisely because the
 * container that makes a checkpoint unmissable is the container that makes it
 * feel like a form.
 *
 * At ≤767px there is no margin, so the note moves into the column as the
 * question's lead-in line, still in the hand, still rotated, with the brass
 * rule beside the options only (`CheckpointPhone`, P1). That is the whole of
 * the reduction: nothing is added back.
 *
 * The margin the note sits in is taken out of the reading measure rather than
 * out of the page. The artboards are one frozen width; at 768px with the
 * explainer open there is no page margin to hang anything in, and a note
 * positioned into one would push the column sideways (8.5.2 forbids the
 * scrollbar that follows).
 *
 * Pre:  `questions` is the stripped payload of 5.2.3 — no `correct_option_id`
 *       and no `explanation` have crossed the wire.
 * Post: those two fields exist on this screen only for a question the student
 *       has actually answered, and only as the server's reply to
 *       `POST …/answers` (5.2.4). Nothing here can disclose an answer early
 *       because nothing here has one to disclose.
 * Invariant: answering never gates the lesson (5.2.4). The screen's Continue
 *       does not read this component's state, so a student may answer out of
 *       order, answer again, or press on.
 *
 * An option is a line of type and also something a finger has to hit. It keeps
 * the desktop artboard's tight row under a mouse and takes 44px wherever a
 * finger is expected — at ≤767px, which `CheckpointPhone` draws that way, and
 * under a coarse pointer at any width (8.5.4).
 *
 * Each option row is `relative` on purpose: the radio behind it is visually
 * hidden, and an absolutely positioned element with no positioned ancestor is
 * laid out against the document. Focusing one then scrolls the *page* — which
 * in this shell is the viewport, not the column — and the lesson slides out of
 * sight the moment a student picks an answer.
 */
export default function CheckpointQuestion({
    questions,
    graded,
    onAnswer,
}: {
    questions: QuizQuestion[];
    /** What the server said, per question id, once it has been asked. */
    graded: Record<string, GradedAnswer>;
    onAnswer: (questionId: string, optionId: string) => Promise<void>;
}) {
    const phone = useIsMobile();

    return (
        <div className="measure flex flex-col gap-2 pb-2 md:flex-row md:gap-[18px]">
            <Hand phone={phone} />

            <div className="flex min-w-0 grow flex-col gap-8">
                {questions.map((question) => (
                    <Question
                        key={question.id}
                        question={question}
                        graded={graded[question.id]}
                        onAnswer={onAnswer}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * The tutor's own voice — Caveat, wine, off the square (D21).
 *
 * In the margin it is right-aligned against the question and carries the
 * little rule that points at it; as a lead-in line it simply opens the
 * question, so the rule would be pointing at the words underneath it.
 */
function Hand({ phone }: { phone: boolean }) {
    if (phone) {
        return (
            <div className="hand -rotate-[1.1deg] text-[23px] leading-[1.25]">
                quick — which one?
            </div>
        );
    }

    return (
        <div className="hand w-[132px] flex-none -rotate-2 pt-0.5 text-right">
            quick — which one?
            <div className="mt-1.5 ml-auto h-px w-14 rotate-[4deg] bg-wine/45" />
        </div>
    );
}

/** One question: the prompt, the options behind the brass rule, and Answer. */
function Question({
    question,
    graded,
    onAnswer,
}: {
    question: QuizQuestion;
    graded?: GradedAnswer;
    onAnswer: (questionId: string, optionId: string) => Promise<void>;
}) {
    const [chosen, setChosen] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const answered = graded !== undefined;

    return (
        <>
            <p className="mb-2.5">{question.prompt}</p>

            <div className="flex flex-col border-l-2 border-brass pl-4">
                {question.options.map((option) => (
                    <label
                        key={option.id}
                        className="relative flex cursor-pointer items-baseline gap-[11px] py-1 max-md:min-h-11 pointer-coarse:min-h-11"
                    >
                        <input
                            type="radio"
                            name={question.id}
                            value={option.id}
                            checked={chosen === option.id}
                            disabled={answered}
                            onChange={() => setChosen(option.id)}
                            className="peer sr-only"
                        />
                        <span
                            className={[
                                'mt-[7px] size-[11px] flex-none border peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brass',
                                chosen === option.id
                                    ? 'border-wine bg-wine'
                                    : 'border-muted-ink',
                            ].join(' ')}
                        />
                        <span
                            className={
                                answered &&
                                graded.correct_option_id === option.id
                                    ? 'text-wine'
                                    : undefined
                            }
                        >
                            {option.text}
                        </span>
                        {answered && graded.correct_option_id === option.id && (
                            <span className="flex-none text-brass">✓</span>
                        )}
                    </label>
                ))}
            </div>

            {answered ? (
                <p className="mt-3.5 border-l-2 border-brass pl-4 text-[16.5px]">
                    <span className="label mr-2 text-wine">
                        {graded.correct ? 'Right' : 'Not this time'}
                    </span>
                    {graded.explanation}
                </p>
            ) : (
                <Button
                    size="action"
                    className="mt-4 w-full md:w-auto md:self-start"
                    disabled={chosen === null || busy}
                    onClick={() => {
                        if (chosen === null) {
                            return;
                        }

                        setBusy(true);
                        onAnswer(question.id, chosen).finally(() =>
                            setBusy(false),
                        );
                    }}
                >
                    Answer
                </Button>
            )}
        </>
    );
}
