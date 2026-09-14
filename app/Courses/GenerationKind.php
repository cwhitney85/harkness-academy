<?php

namespace App\Courses;

/**
 * The generations a student waits on, off the request that asked for them
 * (ledger D25, spec 11).
 *
 * The syllabus and the exam are here because they take minutes. The checkpoint
 * and the interview write-up are here because they are silent: each is a
 * non-streamed call that used to run inside a streamed response, and the host
 * cuts a response that says nothing for 60 seconds (T10). What they share is
 * that the screen has to say "this is being written" and the work has to
 * survive the request that asked for it.
 *
 * Everything else the model writes either streams (a section, an interview
 * turn), answers fast enough in its own request (an explanation), or is
 * already behind package 5's completion chain (homework, the summaries).
 */
enum GenerationKind: string
{
    case Syllabus = 'syllabus';
    case FinalExam = 'final_exam';
    case Checkpoint = 'checkpoint';
    case InterviewSummary = 'interview_summary';

    /**
     * What the screen calls the wait, in the student's words.
     *
     * Here rather than on the screens because two screens and a dashboard card
     * can each reach a pending request, and three surfaces inventing their own
     * phrasing is how "drawing up" becomes "generating" on one of them.
     */
    public function waitingMessage(): string
    {
        return match ($this) {
            self::Syllabus => 'Your syllabus is being drawn up',
            self::FinalExam => 'Your exam is being written',
            self::Checkpoint => 'Questions for this section are being written',
            self::InterviewSummary => 'What you said is being written up',
        };
    }

    /**
     * How long a request may stay out before nobody waits on it any more, in
     * seconds; null for as long as it takes.
     *
     * Only a checkpoint gives up (11.3). It fails open, so a screen still
     * saying one is coming after its job could have finished is claiming
     * something that is not going to happen -- which is exactly what a stopped
     * queue looks like from the lesson. Two minutes is `CheckpointGenerator`'s
     * two attempts at the `cheap` tier's 60 seconds. The other three fail
     * closed and hold something the student cannot go on without, so they wait.
     */
    public function patience(): ?int
    {
        return $this === self::Checkpoint ? 120 : null;
    }
}
