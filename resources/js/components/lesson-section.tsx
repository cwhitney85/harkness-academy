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
    const paragraphs = content.split(/\n{2,}/);

    return (
        <>
            {paragraphs.map((paragraph, index) => (
                <p key={index} className="mb-[15px] sm:mb-[17px]">
                    {index === 0 && dropCap && (
                        <span className="display float-left mt-1 mr-[9px] text-[52px] leading-[0.82] text-wine sm:mt-1.5 sm:mr-3 sm:text-[68px] sm:leading-[0.8]">
                            {paragraph.charAt(0)}
                        </span>
                    )}
                    {inline(
                        index === 0 && dropCap ? paragraph.slice(1) : paragraph,
                        mark,
                    )}
                    {streaming && index === paragraphs.length - 1 && (
                        <span className="ml-[3px] inline-block h-[18px] w-2 -translate-y-[3px] bg-wine align-baseline" />
                    )}
                </p>
            ))}
        </>
    );
}

/**
 * Emphasis, and the one marked phrase, inside a paragraph.
 *
 * The mark is drawn as the artboards draw it: a wash of gold with a brass
 * underline, which is the same pair that labels and rules everything else in
 * the design (8.3.4) rather than a highlighter colour of its own.
 */
function inline(text: string, mark: string | null) {
    const at = mark === null ? -1 : text.indexOf(mark);

    if (at === -1 || mark === null) {
        return emphasise(text);
    }

    return (
        <>
            {emphasise(text.slice(0, at))}
            <mark className="bg-gold/50 text-ink shadow-[0_1.5px_0_var(--brass)]">
                {mark}
            </mark>
            {emphasise(text.slice(at + mark.length))}
        </>
    );
}

/** `*emphasis*`, the only markup `lesson.section` invites. */
function emphasise(text: string) {
    return text
        .split(/\*([^*\n]+)\*/g)
        .map((part, index) =>
            index % 2 === 1 ? <em key={index}>{part}</em> : part,
        );
}
