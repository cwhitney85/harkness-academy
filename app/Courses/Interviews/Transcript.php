<?php

namespace App\Courses\Interviews;

use App\Models\Course;
use App\Models\InterviewMessage;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Messages\Message;

/**
 * One interview's conversation so far (spec 4.3).
 *
 * The only writer of `interview_messages`, and there is no deleter: a turn is
 * kept for as long as its course is (ledger D9, amended 2026-09-19). Scoped to
 * one course and one interview, so no method here can reach another's turns.
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
     * Record the student's answer to the tutor's latest question.
     *
     * An answer already standing after that question is one whose reply never
     * arrived -- the row is written before the provider is called, so a failed
     * stream leaves it behind. The next answer replaces it rather than
     * following it, so sending it again is not saying it twice (spec 10.3).
     *
     * Post: the transcript ends with exactly one student turn, holding `$content`.
     */
    public function answer(string $content): InterviewMessage
    {
        $latest = $this->latestTurn();

        if ($latest?->speaker !== Speaker::Student) {
            return $this->append(Speaker::Student, $content);
        }

        $latest->update(['content' => trim($content)]);

        return $latest;
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

    /**
     * How many questions the tutor has asked.
     *
     * Where the cap of 4.3.1 and 4.4.1 is counted. The tutor's turns *are* the
     * questions: it asks one at a time by instruction, and the one thing it
     * says that is not a question -- the line it closes on at the cap -- is
     * never written down (spec 4.3.1).
     */
    public function questionsAsked(): int
    {
        return $this->turns()->where('speaker', Speaker::Tutor->value)->count();
    }

    /**
     * Whether the tutor has actually asked something yet.
     *
     * What "underway" means, and what 8.6's re-open precondition reads. A
     * student turn only ever follows a tutor turn, so this is false exactly
     * when the transcript is empty -- but the two are asking different
     * questions, and answering or summarizing a conversation the tutor never
     * spoke in is meaningless whichever way the rows happen to fall.
     */
    public function hasTutorTurn(): bool
    {
        return $this->questionsAsked() > 0;
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
