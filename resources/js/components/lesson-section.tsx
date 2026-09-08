import { Fragment } from 'react';

import type { LessonTurn } from '@/types';

/**
 * One section of a lesson, as the paper column reads it — `ALesson` and
 * `LessonPhone` in docs/design/canvas/.
 *
 * The section's own title is the column's `h1` and its concept slugs are the
 * wine label beneath it, which is what the artboards draw: the student is
 * always told what this stretch of prose is responsible for teaching.
 *
 * The drop cap belongs to the section's opening paragraph and to nothing else.
 * A Q&A turn is more of the same conversation rather than a new beginning, so
 * it is set as plain prose under the tutor's label — the same two voices the
 * interview screen draws, and for the same reason.
 *
 * Pre:  `turns` are this section's turns in the order they were spoken, and
 *       `live` is the turn currently arriving, if any.
 * Post: every tutor turn carries its `message_id` in the DOM. That is what
 *       makes a selection explainable (8.8.4): the endpoint names a transcript
 *       row and reads the passage server-side (D16), so the screen must be
 *       able to say which row a highlight came out of.
 */
export default function LessonSection({
    title,
    concepts,
    turns,
    live,
    marked,
}: {
    title: string;
    concepts: string[];
    turns: LessonTurn[];
    /** The turn arriving right now — deltas as they land (8.8.2). */
    live: string | null;
    /** The phrase the explainer is open on, marked in the prose it came from. */
    marked: { messageId: number; text: string } | null;
}) {
    return (
        <div className="measure py-7 sm:py-10">
            <h1 className="display text-[29px] leading-[1.12] sm:text-[43px] sm:leading-[1.07]">
                {title}
            </h1>
            <p className="label mt-1.5 mb-[18px] text-wine sm:mb-[26px]">
                {concepts.join(' · ')}
            </p>

            {turns.map((turn, index) =>
                turn.speaker === 'student' ? (
                    <Asked key={turn.message_id} content={turn.content} />
                ) : (
                    <div
                        key={turn.message_id}
                        data-message-id={turn.message_id}
                    >
                        <Prose
                            content={turn.content}
                            dropCap={index === 0}
                            mark={
                                marked?.messageId === turn.message_id
                                    ? marked.text
                                    : null
                            }
                        />
                    </div>
                ),
            )}

            {live !== null && (
                <Prose
                    content={live}
                    dropCap={turns.length === 0}
                    mark={null}
                    streaming
                />
            )}
        </div>
    );
}

/**
 * The student's own question, set as the interview screen sets it: on the
 * surface behind a brass rule, which is this design's established way of
 * saying "these words came from somewhere else". Caveat is the tutor's voice
 * and is never the student's (D21).
 */
function Asked({ content }: { content: string }) {
    return (
        <div className="mb-[15px] border-l-2 border-brass bg-surface py-2.5 pr-3 pl-4 sm:mb-[17px]">
            <p className="label mb-1">You asked</p>
            <p className="whitespace-pre-wrap">{content}</p>
        </div>
    );
}

/**
 * A turn of tutor prose: paragraphs, a drop cap on the first if this is where
 * the section opens, and the caret that says more is coming.
 *
 * The model writes ordinary prose with `*emphasis*` in it and nothing else —
 * `lesson.section` asks for a taught section, not for a document — so the only
 * markup read here is that one span. Anything richer would be a markdown
 * dependency for a syntax the prompt never invites.
 */
function Prose({
    content,
    dropCap,
    mark,
    streaming = false,
}: {
    content: string;
    dropCap: boolean;
    mark: string | null;
    streaming?: boolean;
}) {
    const paragraphs = content.split(/\n{2,}/).map(runs);

    return (
        <>
            {paragraphs.map((paragraph, index) => {
                const [capital, body] =
                    index === 0 && dropCap
                        ? opening(paragraph)
                        : ['', paragraph];

                return (
                    <p key={index} className="mb-[15px] sm:mb-[17px]">
                        {capital !== '' && (
                            <span className="display float-left mt-1 mr-[9px] text-[52px] leading-[0.82] text-wine sm:mt-1.5 sm:mr-3 sm:text-[68px] sm:leading-[0.8]">
                                {capital}
                            </span>
                        )}
                        {inline(body, mark)}
                        {streaming && index === paragraphs.length - 1 && (
                            <span className="ml-[3px] inline-block h-[18px] w-2 -translate-y-[3px] bg-wine align-baseline" />
                        )}
                    </p>
                );
            })}
        </>
    );
}

/** A stretch of a paragraph, and whether the model emphasised it. */
type Run = { text: string; em: boolean };

/**
 * `*emphasis*`, the only markup `lesson.section` invites, read as runs.
 *
 * Asterisks pair off in order, so an opener with no closer yet — which is what
 * every paragraph still arriving holds — runs to the end of what has landed
 * rather than being printed as a literal asterisk (8.8.7). The emphasis simply
 * grows as the rest of the span streams in.
 *
 * A run of asterisks is one delimiter, because the model writes `**this**` as
 * well as `*this*` whatever the prompt asks it for. Both are the one emphasis
 * this design has; what matters is that neither leaves an asterisk on the page.
 */
function runs(text: string): Run[] {
    return text
        .split(/\*+/)
        .map((part, index) => ({ text: part, em: index % 2 === 1 }))
        .filter((run) => run.text !== '');
}

/**
 * The section's opening letter, off the front of the first run so it can be
 * set as the drop cap: the letter the reader sees, never the asterisk that
 * opened an emphasis span in front of it.
 */
function opening(paragraph: Run[]): [string, Run[]] {
    const [first, ...rest] = paragraph;

    if (first === undefined) {
        return ['', paragraph];
    }

    return [
        first.text.charAt(0),
        [{ ...first, text: first.text.slice(1) }, ...rest],
    ];
}

/**
 * Emphasis, and the one marked phrase, inside a paragraph.
 *
 * The mark is looked for in the text the student can actually see — the runs
 * with their asterisks already taken out — because that is what their
 * selection was made of. It is then laid over those runs rather than cutting
 * the paragraph in two around it, which is what keeps an emphasis span
 * containing the mark one `<em>` instead of two unpaired halves (8.8.7).
 *
 * The mark is drawn as the artboards draw it: a wash of gold with a brass
 * underline, which is the same pair that labels and rules everything else in
 * the design (8.3.4) rather than a highlighter colour of its own.
 */
function inline(paragraph: Run[], mark: string | null) {
    const plain = paragraph.map((run) => run.text).join('');
    const from = mark === null ? -1 : plain.indexOf(mark);
    const to = mark === null ? -1 : from + mark.length;
    let at = 0;

    return paragraph.map((run, index) => {
        const start = at;
        at += run.text.length;

        const content =
            from === -1 ? run.text : wash(run.text, from - start, to - start);

        return run.em ? (
            <em key={index}>{content}</em>
        ) : (
            <Fragment key={index}>{content}</Fragment>
        );
    });
}

/** So much of one run as the mark covers, `from` and `to` being run-relative. */
function wash(text: string, from: number, to: number) {
    const start = Math.max(from, 0);
    const end = Math.min(to, text.length);

    if (start >= end) {
        return text;
    }

    return (
        <>
            {text.slice(0, start)}
            <mark className="bg-gold/50 text-ink shadow-[0_1.5px_0_var(--brass)]">
                {text.slice(start, end)}
            </mark>
            {text.slice(end)}
        </>
    );
}
