import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import CheckpointQuestion, {
    questionsInset,
} from '@/components/checkpoint-question';
import LessonSection from '@/components/lesson-section';
import StudyShell from '@/components/study-shell';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/api';
import { readTurnStream } from '@/lib/turn-stream';
import { store as checkpointAnswers } from '@/routes/lessons/checkpoints/answers';
import type {
    GradedAnswer,
    LessonCheckpointRow,
    LessonScreen,
    LessonTurn,
    RailLesson,
    TurnEvent,
} from '@/types';

/**
 * Screen S6 — the lesson, and S7, its completed state (spec 8.8).
 *
 * Everything the student does here happens on this URL. A section is streamed
 * (8.8.2), a checkpoint is answered (8.8.3), a phrase is explained (8.8.4), a
 * question is asked (8.8.5) and the lesson is finished (8.8.6) without a
 * navigation, because none of them is somewhere else — the flow map draws S7
 * as a state of this screen rather than as a thirteenth one.
 *
 * **What is painted comes from three places, and they do not overlap.** The
 * props restore the lesson (5.3): what has been taught, what was asked, which
 * checkpoints already exist. The five events paint what is arriving right now
 * (5.2.5), including the checkpoint that closes a section the student has just
 * been taught. The two JSON endpoints answer a checkpoint and explain a
 * highlight, and their replies stay on the screen rather than becoming props.
 *
 * The transcript is the merge of the two: rows the server restored, plus turns
 * that finished while the student sat here. Merging by `message_id` rather
 * than replacing local state on every visit is what keeps a partial reload —
 * the one after a checkpoint arrives — from dropping the turn that produced it.
 */
export default function LessonScreenPage({
    lesson,
    resume_at,
    sections,
    transcript,
    checkpoints,
    limits,
    course,
    urls,
}: LessonScreen) {
    /** Turns that finished while the student was on this screen. */
    const [added, setAdded] = useState<LessonTurn[]>([]);
    /** Checkpoints that arrived on the stream rather than with the props. */
    const [arrived, setArrived] = useState<LessonCheckpointRow[]>([]);
    /** The turn arriving now, and the section head painted before it (8.8.2). */
    const [live, setLive] = useState<string | null>(null);
    /**
     * Which section is on screen, and its title. One piece of state, because
     * `turn.start` moves both at once and the rail moves both at once (8.8.2).
     */
    const [head, setHead] = useState(() => headAt(sections, resume_at));
    const viewing = head.index;
    const [graded, setGraded] = useState<Record<string, GradedAnswer>>({});
    const [selected, setSelected] = useState<Highlight | null>(null);
    const [explaining, setExplaining] = useState<Explanation | null>(null);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState<string | null>(null);

    const completed = lesson.status === 'completed';
    const total = lesson.sections_total;

    const turns = useMemo(() => merge(transcript, added), [transcript, added]);
    const taught = useCallback(
        (index: number) =>
            turns.some(
                (turn) =>
                    turn.kind === 'section' && turn.section_index === index,
            ),
        [turns],
    );

    /** What the accumulating turn is, until `turn.end` files it. */
    const buffer = useRef({ text: '', kind: 'section' as LessonTurn['kind'] });
    /** The section a finishing turn belongs to, readable from the handler. */
    const section = useRef(viewing);

    useEffect(() => {
        section.current = viewing;
    }, [viewing]);

    const handle = useCallback((event: TurnEvent) => {
        switch (event.name) {
            case 'turn.start':
                buffer.current = {
                    text: '',
                    kind: event.data.kind === 'qa' ? 'qa' : 'section',
                };
                setLive('');

                // The header before the body: the student is told which
                // section this is and how far through they are before the
                // first token of it lands (8.8.2).
                if (event.data.section_index !== undefined) {
                    section.current = event.data.section_index;
                    setHead({
                        index: event.data.section_index,
                        title: event.data.section_title ?? '',
                    });
                }

                break;
            case 'turn.delta':
                buffer.current.text += event.data.text;
                setLive(buffer.current.text);
                break;
            case 'turn.end':
                setAdded((rows) => [
                    ...rows,
                    {
                        message_id: event.data.message_id,
                        turn_id: event.data.turn_id,
                        kind: buffer.current.kind,
                        speaker: 'tutor',
                        section_index: section.current,
                        content: buffer.current.text,
                    },
                ]);
                setLive(null);
                break;
            case 'checkpoint.ready':
                setArrived((rows) => [
                    ...rows,
                    {
                        checkpoint_id: event.data.checkpoint_id,
                        section_index: event.data.checkpoint.section_index,
                        payload: { checkpoint: event.data.checkpoint },
                    },
                ]);
                break;
            case 'stream.error':
                setFailure(
                    event.data.retryable
                        ? 'The tutor lost their thread. Nothing is lost — carry on and it will pick up where it stopped.'
                        : 'This lesson cannot be taught here.',
                );
                break;
        }
    }, []);

    const teach = useCallback(
        (index: number) => {
            const source = sections[index - 1];

            if (source === undefined) {
                return;
            }

            setBusy(true);
            setFailure(null);

            readTurnStream(source.stream, handle)
                .catch(() =>
                    setFailure(
                        'The tutor could not be reached. Try this section again.',
                    ),
                )
                .finally(() => {
                    setLive(null);
                    setBusy(false);
                });
        },
        [sections, handle],
    );

    // A section the student has reached but never been taught is taught on
    // arrival: opening a lesson is the tutor starting to talk, not a button.
    // The ref survives StrictMode's double-invoked effect, which would
    // otherwise open two streams for one section.
    const opened = useRef(false);

    useEffect(() => {
        if (opened.current || completed || taught(resume_at)) {
            return;
        }

        opened.current = true;
        teach(resume_at);
    }, [completed, resume_at, taught, teach]);

    // Explain this has to be raised by a finger as well as by a mouse
    // (8.8.9). iOS Safari fires no `mouseup` for a long-press selection — the
    // callout takes the gesture over — but every browser fires
    // `selectionchange`, so that is what offers the explanation.
    //
    // Only a selection is taken from it, never the loss of one: pressing
    // "Explain this" collapses the selection before the click lands, and
    // reading that collapse would pull the button out from under the press.
    // The offer is withdrawn where it always was — on a mouse or a key coming
    // up in the prose with nothing selected.
    useEffect(() => {
        function offer() {
            const highlight = highlighted();

            if (highlight !== null) {
                setSelected(highlight);
            }
        }

        document.addEventListener('selectionchange', offer);

        return () => document.removeEventListener('selectionchange', offer);
    }, []);

    /** Answer a checkpoint (5.2.4) — the one moment the key is disclosed. */
    async function answer(
        checkpointId: number,
        questionId: string,
        optionId: string,
    ): Promise<void> {
        const url = checkpointAnswers.url({
            lesson: lesson.id,
            checkpoint: checkpointId,
        });

        try {
            const result = await postJson<GradedAnswer>(url, {
                question_id: questionId,
                option_id: optionId,
            });

            setGraded((all) => ({ ...all, [questionId]: result }));
        } catch (error) {
            setFailure((error as Error).message);
        }
    }

    /** Ask about this section (8.8.5) — a `qa` turn, into the transcript. */
    function ask() {
        const text = draft.trim();

        if (text === '' || busy) {
            return;
        }

        setBusy(true);
        setFailure(null);
        setDraft('');
        setAdded((rows) => [...rows, asked(text, head.index)]);

        readTurnStream(urls.question, handle, {
            method: 'POST',
            body: { text },
        })
            .catch(() => {
                setFailure('That did not reach the tutor. Ask it again.');
                setDraft(text);
            })
            .finally(() => {
                setLive(null);
                setBusy(false);
            });
    }

    /** Explain the highlight (8.8.4, 5.5). */
    async function explain(highlight: Highlight) {
        setExplaining({ ...highlight, explanation: null });
        setSelected(null);
        window.getSelection()?.removeAllRanges();

        try {
            const { explanation } = await postJson<{ explanation: string }>(
                urls.explanation,
                {
                    message_id: highlight.messageId,
                    selection: highlight.text,
                },
            );

            setExplaining({ ...highlight, explanation });
        } catch (error) {
            setExplaining(null);
            setFailure((error as Error).message);
        }
    }

    /**
     * S7's fourth edge (8.6): practise this lesson. The POST generates a
     * refresher and answers with where it is read, which is the URL we then
     * go to — a lesson refresher is the one grain reached from this screen.
     */
    function practise() {
        setBusy(true);

        postJson<{ url: string }>(urls.refresher, {})
            .then((refresher) => router.visit(refresher.url))
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setBusy(false));
    }

    function complete() {
        setBusy(true);

        postJson(urls.complete, {})
            // The rail marks the lesson done, the next one unlocks and the
            // column becomes S7 — all of it from the server's own state, and
            // none of it a page reload (8.8.6).
            .then(() => router.reload())
            .catch((error: Error) => setFailure(error.message))
            .finally(() => setBusy(false));
    }

    const checkpoint =
        live === null
            ? [...checkpoints, ...arrived].find(
                  (row) => row.section_index === viewing,
              )
            : undefined;
    const next = viewing + 1;

    return (
        <StudyShell
            course={course.title}
            title={lesson.title}
            lesson={{ number: lesson.position, total: course.lessons.length }}
            section={{ number: viewing, total }}
            rail={
                <Rail
                    lessons={course.lessons}
                    position={lesson.position}
                    sections={sections}
                    viewing={viewing}
                    taught={taught}
                    onView={(index) => setHead(headAt(sections, index))}
                />
            }
            explainer={
                explaining && (
                    <Explainer
                        highlight={explaining}
                        cap={limits.highlight_characters}
                    />
                )
            }
            explainerOpen={explaining !== null}
            onExplainerClose={() => setExplaining(null)}
            ask={
                completed ? undefined : (
                    <Ask
                        draft={draft}
                        limit={limits.question_characters}
                        cap={limits.highlight_characters}
                        selected={selected}
                        busy={busy}
                        failure={failure}
                        onDraft={setDraft}
                        onAsk={ask}
                        onExplain={explain}
                    />
                )
            }
        >
            <Head title={lesson.title} />

            {completed ? (
                <Complete
                    lesson={lesson}
                    course={course}
                    urls={urls}
                    busy={busy}
                    onPractise={practise}
                />
            ) : (
                <div
                    onMouseUp={() => setSelected(highlighted())}
                    onKeyUp={() => setSelected(highlighted())}
                >
                    <LessonSection
                        title={head.title}
                        concepts={sections[viewing - 1]?.concepts ?? []}
                        turns={turns.filter(
                            (turn) => turn.section_index === viewing,
                        )}
                        live={live}
                        marked={
                            explaining && {
                                messageId: explaining.messageId,
                                text: explaining.text,
                            }
                        }
                    />

                    {checkpoint && (
                        <CheckpointQuestion
                            questions={checkpoint.payload.checkpoint.questions}
                            graded={graded}
                            onAnswer={(questionId, optionId) =>
                                answer(
                                    checkpoint.checkpoint_id,
                                    questionId,
                                    optionId,
                                )
                            }
                        />
                    )}

                    {live === null && (
                        <div className="measure pb-10">
                            <Button
                                size="action"
                                disabled={busy}
                                className={[
                                    'w-full md:w-auto',
                                    // Under the Answer button, not under the
                                    // margin note beside it (8.8.8). With no
                                    // checkpoint on screen there is nothing
                                    // to follow, and the button keeps the
                                    // prose's own left edge.
                                    checkpoint ? questionsInset : '',
                                ].join(' ')}
                                onClick={() => {
                                    // A section the student is looking at
                                    // that was never taught is one whose
                                    // stream failed, and the only thing this
                                    // button may do is ask for it again.
                                    // Moving on would strand it: the rail
                                    // disables an untaught section, so
                                    // nothing could ever come back to it.
                                    if (!taught(viewing)) {
                                        teach(viewing);
                                    } else if (next > total) {
                                        complete();
                                    } else if (taught(next)) {
                                        setHead(headAt(sections, next));
                                    } else {
                                        teach(next);
                                    }
                                }}
                            >
                                {!taught(viewing)
                                    ? 'Try again'
                                    : next > total
                                      ? 'Finish this lesson'
                                      : 'Continue'}
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </StudyShell>
    );
}

type Highlight = { messageId: number; text: string };
type Explanation = Highlight & { explanation: string | null };

/**
 * The rail's body: the course's lessons, and the sections of this one — the
 * two lists `ALesson` draws, in its own anatomy.
 *
 * A lesson with no URL is locked, and a section not yet taught cannot be
 * jumped to: `LessonProgress` refuses to open one ahead of the furthest
 * reached, so offering it would be offering a 422.
 */
function Rail({
    lessons,
    position,
    sections,
    viewing,
    taught,
    onView,
}: {
    lessons: RailLesson[];
    /** The lesson this screen is showing — the one the wine band marks. */
    position: number;
    sections: LessonScreen['sections'];
    viewing: number;
    taught: (index: number) => boolean;
    onView: (index: number) => void;
}) {
    return (
        <>
            <div className="flex flex-col gap-2.5">
                <div className="label border-b border-field-green pb-1.5 text-gold">
                    Lessons
                </div>
                {lessons.map((entry) => {
                    const current = entry.position === position;
                    const row = (
                        <>
                            <span
                                className={`display w-[18px] flex-none text-right ${current ? 'text-gold' : 'text-brass'}`}
                            >
                                {entry.position}
                            </span>
                            <span className="grow text-[15.5px]">
                                {entry.title}
                            </span>
                            {entry.completed && (
                                <span className="flex-none text-brass">✓</span>
                            )}
                        </>
                    );

                    const shape = current
                        ? '-mx-[22px] bg-wine px-[22px] py-2.5 text-ground'
                        : entry.url === null
                          ? 'text-ground/45'
                          : 'text-ground/70';

                    return entry.url === null || current ? (
                        <div
                            key={entry.position}
                            className={`flex items-baseline gap-3 ${shape}`}
                        >
                            {row}
                        </div>
                    ) : (
                        <Link
                            key={entry.position}
                            href={entry.url}
                            className={`flex min-h-11 items-baseline gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass ${shape}`}
                        >
                            {row}
                        </Link>
                    );
                })}
            </div>

            <div className="mt-auto flex flex-col gap-2.5">
                <div className="label border-b border-field-green pb-1.5 text-gold">
                    This lesson
                </div>
                {sections.map((entry) => {
                    const mark = taught(entry.index)
                        ? entry.index === viewing
                            ? '▸'
                            : '✓'
                        : ' ';

                    return (
                        <button
                            key={entry.index}
                            type="button"
                            disabled={!taught(entry.index)}
                            onClick={() => onView(entry.index)}
                            className={[
                                'flex min-h-11 items-baseline gap-2.5 text-left text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass',
                                entry.index === viewing
                                    ? 'text-ground'
                                    : taught(entry.index)
                                      ? 'text-ground/65'
                                      : 'text-ground/45',
                            ].join(' ')}
                        >
                            <span
                                className={
                                    entry.index === viewing
                                        ? 'text-gold'
                                        : 'text-brass'
                                }
                            >
                                {mark}
                            </span>
                            <span>{entry.title}</span>
                        </button>
                    );
                })}
            </div>
        </>
    );
}

/**
 * The explainer field's content — the quoted phrase behind a brass rule, the
 * tutor's answer, and the cap the student is working under.
 *
 * The cap is stated here rather than only enforced, because a limit the
 * student cannot see is one they meet by being refused after they have already
 * committed to the selection (5.5's `limits`).
 */
function Explainer({
    highlight,
    cap,
}: {
    highlight: Explanation;
    cap: number;
}) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="border-l-2 border-brass pl-[13px] text-[16.5px] italic">
                {highlight.text}
            </div>
            <div className="grow text-[16px] leading-[1.62] md:text-[16.5px]">
                {highlight.explanation === null ? (
                    <p className="text-ground/70">Reading it over…</p>
                ) : (
                    highlight.explanation
                        .split(/\n{2,}/)
                        .map((paragraph, index) => (
                            <p key={index} className="mb-3.5 last:mb-0">
                                {paragraph}
                            </p>
                        ))
                )}
            </div>
            <div className="label mt-auto border-t border-ground/25 pt-3 text-ground/70">
                Highlight up to {cap} characters
            </div>
        </div>
    );
}

/**
 * The strip under the column: the question box of 5.5, and — while a phrase is
 * selected — the offer to explain it.
 *
 * Both counters read the server's numbers (`limits`), never a constant of
 * their own: a screen that wrote the cap out in its own words would go on
 * stating it after `LessonSession::MAX_QUESTION` changed (8.8.5).
 *
 * An over-cap highlight is refused here, before a request exists. The server
 * refuses it too and counts it (D16); what this saves is the student finding
 * out after they have committed.
 *
 * `ALesson` draws the counter beside the field, which is where it fits when
 * the field is the only thing on the strip. This strip also carries the Ask
 * button the interview screen established, and the three of them abreast leave
 * the field too narrow to type in at 390px — so the counter takes the line
 * below, as it does on the interview screen.
 */
function Ask({
    draft,
    limit,
    cap,
    selected,
    busy,
    failure,
    onDraft,
    onAsk,
    onExplain,
}: {
    draft: string;
    limit: number;
    cap: number;
    selected: Highlight | null;
    busy: boolean;
    failure: string | null;
    onDraft: (text: string) => void;
    onAsk: () => void;
    onExplain: (highlight: Highlight) => void;
}) {
    const overCap = selected !== null && selected.text.length > cap;

    return (
        <div className="flex flex-col gap-2.5">
            {failure && <p className="text-[15px] text-wine">{failure}</p>}

            {selected && (
                <div className="flex items-center gap-3 border-l-2 border-brass pl-3">
                    <span className="min-w-0 grow truncate text-[15px] italic">
                        {selected.text}
                    </span>
                    {overCap ? (
                        <span className="label flex-none text-wine">
                            Too long — {selected.text.length} of {cap}
                        </span>
                    ) : (
                        <button
                            type="button"
                            onClick={() => onExplain(selected)}
                            className="label flex min-h-11 flex-none items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                        >
                            Explain this
                        </button>
                    )}
                </div>
            )}

            <div className="flex items-end gap-3">
                <textarea
                    value={draft}
                    rows={1}
                    maxLength={limit}
                    placeholder="Ask about this section…"
                    aria-label="Ask about this section"
                    onChange={(event) => onDraft(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            onAsk();
                        }
                    }}
                    className="min-h-11 grow resize-y border border-hairline bg-surface px-3.5 py-2.5 text-[17px] placeholder:text-muted-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass"
                />
                <Button
                    size="action"
                    disabled={busy || draft.trim() === ''}
                    onClick={onAsk}
                >
                    Ask
                </Button>
            </div>

            <span className="label self-end">
                {draft.length} / {limit}
            </span>
        </div>
    );
}

/**
 * S7 — the lesson finished (8.8.6).
 *
 * Four onward moves, which are the flow map's edges out of S7: the homework
 * the completion chain is writing, the lesson the chain unlocks, the exam when
 * there is no lesson left, and practising this lesson — a lesson-grain
 * refresher, generated by its POST and read on the screen 8.9.4 builds.
 *
 * Nothing here says whether the homework is ready: the chain writes it behind
 * this screen (5.4.5) and S8 is where "not yet" is said (8.9.1). Two screens
 * answering the same question is how they come to disagree.
 *
 * Homework and the exam are Inertia links: 8.9 made both URLs pages.
 *
 * `busy` on this screen is only ever the refresher (8.12.1): the Q&A footer is
 * not rendered once the lesson is complete and neither is the teaching column,
 * so the practise door is the one request S7 can have out, and it may say so
 * in its own name.
 */
function Complete({
    lesson,
    course,
    urls,
    busy,
    onPractise,
}: Pick<LessonScreen, 'lesson' | 'course' | 'urls'> & {
    busy: boolean;
    onPractise: () => void;
}) {
    const next = course.lessons.find(
        (entry) => entry.position === lesson.position + 1,
    );

    return (
        <div className="measure py-10 sm:py-14">
            <p className="label mb-3 text-wine">
                Lesson {lesson.position} done
            </p>
            <h1 className="display text-[32px] leading-[1.1] sm:text-[43px] sm:leading-[1.07]">
                {lesson.title}
            </h1>
            <p className="mt-5">{lesson.objective}</p>

            <div className="mt-9 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <Button asChild size="action" className="w-full sm:w-auto">
                    <Link href={urls.homework}>The homework</Link>
                </Button>

                {next?.url ? (
                    <Link
                        href={next.url}
                        className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        Next: {next.title}
                    </Link>
                ) : next === undefined ? (
                    <Link
                        href={urls.exam}
                        className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                    >
                        The final exam
                    </Link>
                ) : (
                    <span className="label flex min-h-11 items-center">
                        The next lesson is being prepared
                    </span>
                )}

                <button
                    type="button"
                    onClick={onPractise}
                    disabled={busy}
                    className="label flex min-h-11 cursor-pointer items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass disabled:cursor-default disabled:no-underline disabled:opacity-60"
                >
                    {busy ? 'Writing your refresher…' : 'Practise this lesson'}
                </button>
            </div>
        </div>
    );
}

/** The section head as the props restore it, before any turn has started. */
function headAt(sections: LessonScreen['sections'], index: number) {
    return { index, title: sections[index - 1]?.title ?? '' };
}

/** The student's own question, shown the moment they send it. */
function asked(text: string, sectionIndex: number): LessonTurn {
    return {
        // Negative, so this stands-in row can never collide with a real one.
        // It is a `lesson_messages` row on the server; its id arrives with the
        // next restoration rather than with the turn it triggered.
        message_id: -Date.now(),
        turn_id: 'pending',
        kind: 'qa',
        speaker: 'student',
        section_index: sectionIndex,
        content: text,
    };
}

/**
 * The restored transcript, plus turns that finished on this screen.
 *
 * A reload replaces props without remounting, so the two lists can describe the
 * same turn. They are matched on what was said rather than on `message_id`: the
 * student's own question is a row the server writes, and the screen shows it
 * the moment they send it, without ever learning its id.
 */
function merge(restored: LessonTurn[], added: LessonTurn[]): LessonTurn[] {
    const said = (turn: LessonTurn) =>
        `${turn.speaker}|${turn.section_index}|${turn.content}`;
    const seen = new Set(restored.map(said));

    return [...restored, ...added.filter((turn) => !seen.has(said(turn)))];
}

/**
 * What the student has selected, and which transcript row it came out of.
 *
 * Null unless the selection lies inside one tutor turn: the explainer names a
 * row and the server reads the passage out of it (D16), so a highlight that
 * spans two turns has no single row to name.
 */
function highlighted(): Highlight | null {
    const selection = window.getSelection();
    const text = selection?.toString() ?? '';

    if (selection === null || text.trim() === '') {
        return null;
    }

    const node =
        selection.anchorNode instanceof Element
            ? selection.anchorNode
            : selection.anchorNode?.parentElement;
    const row = node?.closest('[data-message-id]');

    if (!(row instanceof HTMLElement) || !row.contains(selection.focusNode)) {
        return null;
    }

    return { messageId: Number(row.dataset.messageId), text };
}
