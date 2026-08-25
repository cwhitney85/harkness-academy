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
