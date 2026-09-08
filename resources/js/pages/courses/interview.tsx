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
 * vocabulary has no way to say so (5.2.5). So every finished turn is followed
 * by a reload: if the course has moved on, the server redirects, and if it has
 * not, the transcript is simply repainted from its rows.
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
    limits: { answer_characters: number };
    urls: { stream: string; answers: string; complete: string };
};

const HEADINGS = {
    knowledge: {
        title: 'What you already know',
        lead: 'A short conversation about where you are starting from. Answer as roughly as you like — "I can order a coffee, I cannot hold a conversation" is more useful than a careful list.',
        done: 'Done — write it up',
    },
    goals: {
        title: 'What you want out of this',
        lead: 'The same again, for where you want to end up. What you say here is what the course is built to get you to.',
        done: 'Done — build my syllabus',
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

    // Guards the one request that is destructive if it is made twice. A ref
    // rather than state because React's StrictMode runs effects twice on
    // mount, and two concurrent opens would discard the first one's transcript.
    const opened = useRef(false);
    const foot = useRef<HTMLDivElement>(null);

    const handle = useCallback((event: TurnEvent) => {
        switch (event.name) {
            case 'turn.start':
                setLive('');
                break;
            case 'turn.delta':
                setLive((text) => (text ?? '') + event.data.text);
                break;
            case 'stream.error':
                setFailure(
                    event.data.retryable
                        ? 'The tutor lost their thread. Nothing is lost — send that again.'
                        : 'This interview cannot be continued here.',
                );
                break;
            default:
                break;
        }
    }, []);

    /** Repaint from rows, and follow the server if the course has moved on. */
    const settle = useCallback(() => {
        router.reload({
            onFinish: () => {
                setLive(null);
                setPending(null);
                setBusy(false);
            },
        });
    }, []);

    useEffect(() => {
        if (!may_open || opened.current) {
            return;
        }

        opened.current = true;
        setBusy(true);

        readTurnStream(urls.stream, handle)
            .then(settle)
            .catch(() => {
                setFailure(
                    'The tutor could not be reached. Reload the page to try again.',
                );
                setBusy(false);
            });
    }, [may_open, urls.stream, handle, settle]);

    useEffect(() => {
        foot.current?.scrollIntoView({ block: 'end' });
    }, [turns.length, live, pending]);

    function send() {
        const text = draft.trim();

        if (text === '' || busy) {
            return;
        }

        setBusy(true);
        setFailure(null);
        setPending(text);
        setDraft('');

        readTurnStream(urls.answers, handle, { method: 'POST', body: { text } })
            .then(settle)
            .catch(() => {
                // The transcript is intact and the answer is handed back, which
                // is the recovery the flow map draws for `stream.error`.
                setFailure(
                    'That did not reach the tutor. Try sending it again.',
                );
                setDraft(text);
                setPending(null);
                setLive(null);
                setBusy(false);
            });
    }

    return (
        <PipelineShell
            step={kind}
            course={course.title}
            footer={
                <div className="flex flex-col gap-2.5">
                    {failure && (
                        <p className="text-[15px] text-wine">{failure}</p>
                    )}
                    <div className="flex items-end gap-3">
                        <textarea
                            value={draft}
                            rows={1}
                            maxLength={limits.answer_characters}
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
                            disabled={busy || draft.trim() === ''}
                            onClick={send}
                        >
                            Send
                        </Button>
                    </div>
                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    urls.complete,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="label flex min-h-11 items-center text-wine underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass"
                        >
                            {copy.done}
                        </button>
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
                <p className="label mb-9">
                    Stop whenever you like — the Done button below is always
                    there.
                </p>

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
                        <Line speaker="tutor" content={live} streaming />
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
