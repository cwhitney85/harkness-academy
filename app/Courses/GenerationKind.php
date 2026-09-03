<?php

namespace App\Courses;

/**
 * The two things a student presses a button and then waits for (ledger D25).
 *
 * Both spend a `top`-tier call that takes minutes, which is the only thing
 * they have in common and the whole reason they share a table: the screen has
 * to say "this is being drawn up" and the work has to survive the request that
 * asked for it.
 *
 * Everything else the model writes is either instant enough to answer in the
 * request (a checkpoint, an explanation) or already behind package 5's
 * completion chain (homework, the summaries). Neither is a kind here, and a
 * third kind should not be added without one of those two facts changing.
 */
enum GenerationKind: string
{
    case Syllabus = 'syllabus';
    case FinalExam = 'final_exam';

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
        };
    }
}
