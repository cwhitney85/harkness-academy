import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import PipelineShell from '@/components/pipeline-shell';
import { Button } from '@/components/ui/button';
import { readTurnStream } from '@/lib/turn-stream';
import type { TurnEvent } from '@/types';

/**
 * Screens S3 and S4 — the two interviews (spec 8.7.2, 8.7.3).
 *
 * One component for both, because the engine already made them one machine
 * (ledger D9): what differs is which document the conversation becomes, and
 * that is the server's business, not the screen's.
 *
 * **A reload paints from `turns` and never re-opens the interview.** Opening
 * discards the transcript, so the stream URL is requested only when the server
 * says `may_open` — which is true until the tutor has asked something, and so
 * covers both nothing having happened yet and a connection dropped during the
 * tutor's very first question. The engine refuses a destructive re-open as
 * well; this is the polite half of the same rule.
 *
 * The Done button is always visible (2.3.4, decision R3). It is in the footer
 * strip rather than in the flow of the conversation, so a long transcript
 * cannot scroll it away.
 *
 * The model may also end the interview itself, mid-answer, and the five-event
 * vocabulary has no way to say so (5.2.5). So every turn, finished or not, is
 * followed by a reload: if the course has moved on, the server redirects, and
 * if it has not, the transcript is simply repainted from its rows (spec 10.1).
 * A turn that did not end is recovered from what that reload brings back — see
 * `converse` — and never by posting the answer again on the student's behalf
 * (10.7).
 *
 * Ending the interview — by Done or by the tutor — puts the write-up on the
 * queue (spec 11.4), and the screen waits on `writing_up` the way S5 waits on
 * a syllabus: it takes no answers, and reloads until the course moves.
 *
 * That reload is what makes this screen an instance of the mounted-component
 * class (ledger D28). `router.reload()` preserves state, so when the redirect
 * it follows lands on the *other* interview, React is handed the same key and
 * keeps the component mounted — guards and all. Hence the wrapper below.
 */

type Turn = { id: number; speaker: 'student' | 'tutor'; content: string };

type Interview = {
    course: { id: number; title: string };
    kind: 'knowledge' | 'goals';
    turns: Turn[];
    may_open: boolean;
    /** The write-up is out on the queue (11.4). */
    writing_up: boolean;
    /** Why the last write-up failed; Done asks again. */
    write_up_failure: string | null;
    limits: { answer_characters: number };
    urls: { stream: string; answers: string; complete: string };
};

/**
 * The student decides when the conversation has gone on long enough.
 *
 * Said in `stop` and drawn as a real button below, because nothing else
 * bounds an interview: the tutor is asked to wrap up once it has asked its
 * questions, but only R3's two doors end one (spec 4.3.1). A student who
 * thinks the tutor is in charge of the length has no way out of a tutor that
 * is not (Colin, 2026-09-08).
 */
const HEADINGS = {
    knowledge: {
        title: 'What you already know',
        lead: 'A short conversation about where you are starting from. Answer as roughly as you like — "I can order a coffee, I cannot hold a conversation" is more useful than a careful list.',
        done: 'Done — write it up',
        writing: 'Writing up what you know…',
        stop: "You decide when it's enough. Press Done at any point and the tutor writes up what you have said so far.",
    },
    goals: {
        title: 'What you want out of this',
        lead: 'The same again, for where you want to end up. What you say here is what the course is built to get you to.',
        done: 'Done — build my syllabus',
        writing: 'Writing up what you want…',
        stop: "You decide when it's enough. Press Done at any point and your syllabus is built from what you have said so far.",
    },
} as const;

/** `kind` is what every piece of state below describes, so it is the key. */
export default function InterviewScreen(props: Interview) {
    return <Conversation key={props.kind} {...props} />;
}

function Conversation({
    course,
    kind,
    turns,
    may_open,
    writing_up,
    write_up_failure,
    limits,
    urls,
}: Interview) {
    const copy = HEADINGS[kind];

    /** The tutor's turn as it arrives, before its row exists. */
    const [live, setLive] = useState<string | null>(null);
    /** The student's answer, shown the moment they send it. */
    const [pending, setPending] = useState<string | null>(null);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState<string | null>(null);
    /**
     * Done has been pressed and its visit is out (8.12.3). From the moment it
     * lands, `writing_up` says the same thing for as long as the write-up is
     * on the queue.
     */
    const [pressed, setPressed] = useState(false);
    const writing = pressed || writing_up;

    // Guards the one request that must not be made twice. A ref rather than
    // state because React's StrictMode runs effects twice on mount, and two
    // concurrent opens would each stream an opening question into the same
    // transcript.
    const opened = useRef(false);
    const foot = useRef<HTMLDivElement>(null);

    /**
     * One tutor turn — the opening question, or the reply to `answer` — then
     * the reload that settles the screen on the server's copy of it.
     *
     * Only `turn.end` says the turn is safely written. Anything else — a
     * refusal, a `stream.error`, a cut connection, an empty reply — is judged
     * by what the reload brings back, because the server may have done more
     * than the stream lived to say:
     *
     * - **The course moved.** The reload redirects, this component unmounts,
     *   and nothing set below survives (10.1, 10.2).
     * - **No new tutor turn.** The answer goes back in the box (10.3) — the
     *   engine replaces an answer that was never replied to, so sending it
     *   again does not say it twice — and whatever the tutor had got through
     *   stays on screen until the next turn replaces it (10.4).
     * - **A new tutor turn, and the stream reported a failure.** The reply was
     *   written and the write-up after it was not (ledger D9's rider: it fails
     *   closed), so the recovery is Done, not a second copy of the answer.
     *
     * Every copy names something on this screen (10.5).
     */
    const converse = useCallback(
        (url: string, answer?: string) => {
            const before = turns.at(-1)?.id ?? 0;
            let ended = false;
            let reported = false;

            setBusy(true);
            setFailure(null);
            setLive(null);

            readTurnStream(
                url,
                (event: TurnEvent) => {
                    switch (event.name) {
                        case 'turn.start':
                            setLive('');
                            break;
                        case 'turn.delta':
                            setLive((text) => (text ?? '') + event.data.text);
                            break;
                        case 'turn.end':
                            ended = true;
                            break;
                        case 'stream.error':
                            reported = true;
                            break;
                        default:
                            break;
                    }
                },
                answer === undefined
                    ? {}
                    : { method: 'POST', body: { text: answer } },
            )
                .catch(() => {})
                .then(() => {
                    let replied = ended;

                    router.reload({
                        onSuccess: (page) => {
                            replied ||= (page.props.turns as Turn[]).some(
                                (turn) =>
                                    turn.speaker === 'tutor' &&
                                    turn.id > before,
                            );
                        },
                        onFinish: () => {
                            setPending(null);
                            setBusy(false);

                            if (replied) {
                                setLive(null);

                                if (!ended && reported) {
                                    setFailure(
                                        'That could not be written up just now. Nothing is lost — press Done to try again.',
                                    );
                                }
                            } else if (answer === undefined) {
                                setFailure('The tutor could not be reached.');
                            } else {
                                setDraft(answer);
                                setFailure(
                                    'The tutor did not reply. Your answer is back in the box below — send it again.',
                                );
                            }
                        },
                    });
                });
        },
        [turns],
    );

    useEffect(() => {
        if (!may_open || opened.current) {
            return;
        }

        opened.current = true;
        converse(urls.stream);
    }, [may_open, urls.stream, converse]);

    // The syllabus's five-second wait. When the write-up lands the course has
    // moved and this reload redirects; when it fails, `writing_up` goes false
    // and the failure arrives with it.
    useEffect(() => {
        if (!writing_up) {
            return;
        }

        const timer = window.setInterval(() => router.reload(), 5000);

        return () => window.clearInterval(timer);
    }, [writing_up]);

    useEffect(() => {
        foot.current?.scrollIntoView({ block: 'end' });
    }, [turns.length, live, pending]);

    function send() {
        const text = draft.trim();

        // Nothing to answer until the tutor has asked (`may_open`), and
        // nothing once the conversation is being written up.
        if (text === '' || busy || may_open || writing) {
            return;
        }

        setPending(text);
        setDraft('');
        converse(urls.answers, text);
    }

    /**
     * The Done button (8.12.3, 2.3.4).
     *
     * One press only. The POST asks the queue for the write-up and lands back
     * here in `writing_up`; the server refuses a second request too, but a
     * button that looks pressable while one is out invites the press.
     */
    function finish() {
        if (writing) {
            return;
        }

        setPressed(true);
        setFailure(null);

        router.post(
            urls.complete,
            {},
            { preserveScroll: true, onFinish: () => setPressed(false) },
        );
    }

    const shown = failure ?? (writing ? null : write_up_failure);

    return (
        <PipelineShell
            step={kind}
            course={course.title}
            footer={
                <div className="flex flex-col gap-2.5">
                    {shown && (
                        <div className="flex items-center gap-4">
                            <p className="text-[15px] text-wine">{shown}</p>
                            {may_open && !busy && (
                                <Button
                                    size="action"
                                    variant="outline"
                                    onClick={() => converse(urls.stream)}
                                >
                                    Try again
                                </Button>
                            )}
                        </div>
                    )}
                    <div className="flex items-end gap-3">
                        <textarea
                            value={draft}
                            rows={1}
                            maxLength={limits.answer_characters}
                            disabled={writing}
                            placeholder="Answer in your own words…"
                            onChange={(event) => setDraft(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !event.shiftKey) {
                                    event.preventDefault();
                                    send();
                                }
                            }}
                            className="min-h-11 grow resize-y border border-hairline bg-surface px-3.5 py-2.5 text-[17px] placeholder:text-muted-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brass"
                        />
                        <Button
                            size="action"
                            disabled={
                                busy ||
                                may_open ||
                                writing ||
                                draft.trim() === ''
                            }
                            onClick={send}
                        >
                            Send
                        </Button>
                    </div>
                    <div className="flex items-center gap-4">
                        <Button
                            size="action"
                            variant="outline"
                            disabled={writing}
                            onClick={finish}
                        >
                            {writing ? copy.writing : copy.done}
                        </Button>
                        <span className="grow" />
                        <span className="label">
                            {draft.length} / {limits.answer_characters}
                        </span>
                    </div>
                </div>
            }
        >
            <Head title={copy.title} />

            <div className="measure py-8 sm:py-12">
                <h1 className="display text-[38px] leading-[1.07] sm:text-[43px]">
                    {copy.title}
                </h1>
                <p className="mt-4 mb-2">{copy.lead}</p>
                <p className="label mb-9">{copy.stop}</p>

                <div className="flex flex-col gap-7">
                    {turns.map((turn) => (
                        <Line
                            key={turn.id}
                            speaker={turn.speaker}
                            content={turn.content}
                        />
                    ))}

                    {pending !== null && (
                        <Line speaker="student" content={pending} />
                    )}

                    {live !== null && (
                        <Line speaker="tutor" content={live} streaming={busy} />
                    )}
                </div>

                <div ref={foot} className="h-2" />
            </div>
        </PipelineShell>
    );
}

/**
 * One turn of the conversation.
 *
 * The tutor speaks as prose on the ground, exactly as a lesson section does.
 * The student's own words are set on the surface behind a brass rule — the
 * same rule the explainer uses to hold a quoted phrase (D21), which is the
 * established way this design marks "these words came from somewhere else".
 *
 * Deliberately not the hand: Caveat is the *tutor's* voice (D21), and putting
 * the student in it would make the one piece of real personality in the design
 * mean two things.
 */
function Line({
    speaker,
    content,
    streaming = false,
}: {
    speaker: 'student' | 'tutor';
    content: string;
    streaming?: boolean;
}) {
    if (speaker === 'student') {
        return (
            <div className="border-l-2 border-brass bg-surface py-2.5 pr-3 pl-4">
                <p className="label mb-1">You</p>
                <p className="whitespace-pre-wrap">{content}</p>
            </div>
        );
    }

    return (
        <div>
            <p className="label mb-1.5 text-wine">The tutor</p>
            <p className="whitespace-pre-wrap">
                {content}
                {streaming && (
                    <span className="ml-[3px] inline-block h-[18px] w-2 -translate-y-[3px] bg-wine align-baseline" />
                )}
            </p>
        </div>
    );
}
