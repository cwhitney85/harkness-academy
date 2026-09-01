<?php

namespace App\Courses\Interviews;

use App\Models\Course;
use App\Models\InterviewMessage;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Messages\Message;

/**
 * One interview's conversation so far (spec 4.3).
 *
 * The only writer and the only deleter of `interview_messages`. Scoped to one
 * course and one interview, so no method here can reach another's turns.
 *
 * Invariant: `discard()` leaves nothing behind. Everything downstream of the
 * summary -- the WK document, the criteria document -- is written from the
 * rendered text, never from a live query, so nothing can be reading these rows
 * at the moment they go.
 */
final class Transcript
{
    public function __construct(
        private readonly Course $course,
        private readonly InterviewKind $kind,
    ) {}

    /**
     * Record a turn.
     *
     * Post: the turn is the last line of `render()` and the last message of
     * `messages()`.
     */
    public function append(Speaker $speaker, string $content): InterviewMessage
    {
        return InterviewMessage::query()->create([
            'course_id' => $this->course->id,
            'kind' => $this->kind,
            'speaker' => $speaker,
            'content' => trim($content),
            'position' => $this->nextPosition(),
            'created_at' => now(),
        ]);
    }

    /**
     * The conversation as a script, for the summarizing prompt.
     */
    public function render(): string
    {
        $lines = [];

        foreach ($this->turns()->cursor() as $turn) {
            $lines[] = $turn->speaker->label().': '.$turn->content;
        }

        return implode("\n\n", $lines);
    }

    /**
     * The conversation as provider messages, for the next turn.
     *
     * @return list<Message>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->turns()->cursor() as $turn) {
            $messages[] = $turn->speaker->message($turn->content);
        }

        return $messages;
    }

    public function isEmpty(): bool
    {
        return ! $this->turns()->exists();
    }

    /**
     * Whether the tutor has actually asked something yet.
     *
     * Not the same question as `isEmpty()`, and the difference is the whole
     * of it: `InterviewSession::open()` writes the student's opening turn
     * *before* the tutor's first question streams, so a connection dropped
     * during that first question leaves a transcript that is not empty and
     * holds nothing anyone said. Answering it and summarizing it are both
     * meaningless, and summarizing it would overwrite what earlier courses in
     * the subtopic recorded (ledger D7a) with a document made of one line the
     * student never typed.
     *
     * So this, rather than emptiness, is what "underway" means, and what
     * 8.6's re-open precondition is narrowed to (Colin, 2026-09-01).
     */
    public function hasTutorTurn(): bool
    {
        return $this->turns()->where('speaker', Speaker::Tutor->value)->exists();
    }

    /**
     * The conversation as the screen draws it (spec 8.7.2).
     *
     * Neither `render()` nor `messages()` will do: one is a script for a
     * prompt and the other is provider messages. A reload paints from this,
     * and never by re-opening the interview.
     *
     * @return list<InterviewMessage>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->turns()->cursor() as $turn) {
            $lines[] = $turn;
        }

        return $lines;
    }

    /**
     * The turn most recently written, whoever said it.
     *
     * What `turn.end` reports (spec 5.2.5): the row exists by the time the
     * event goes out, so a client that saw it knows a reload will paint the
     * turn rather than lose it.
     */
    public function latestTurn(): ?InterviewMessage
    {
        return $this->turns()->reorder()->orderByDesc('position')->first();
    }

    /**
     * Throw the conversation away (spec 4.3).
     *
     * Called once the document it produced is written. Nothing keeps a copy.
     */
    public function discard(): void
    {
        $this->turns()->delete();
    }

    /**
     * @return Builder<InterviewMessage>
     */
    private function turns(): Builder
    {
        return InterviewMessage::query()
            ->where('course_id', $this->course->id)
            ->where('kind', $this->kind->value)
            ->orderBy('position');
    }

    private function nextPosition(): int
    {
        return 1 + (int) InterviewMessage::query()
            ->where('course_id', $this->course->id)
            ->where('kind', $this->kind->value)
            ->max('position');
    }
}
