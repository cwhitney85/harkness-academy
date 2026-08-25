<?php

namespace App\Lessons;

use App\Lessons\Streaming\TurnKind;
use App\Models\Lesson;
use App\Models\LessonMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\Message;

/**
 * One lesson's conversation (spec 5.2.6, 5.3).
 *
 * The only writer of `lesson_messages`, and there is no deleter: unlike an
 * interview transcript this one is kept. 5.3 resumes from it and 5.4.2
 * summarizes it, so a turn that vanished would take the student's progress
 * with it.
 *
 * Invariant: a row exists for exactly the turns that finished streaming. The
 * transport persists at `turn.end` and nowhere else, so a killed connection
 * leaves the transcript exactly as it was before the turn started -- which is
 * what makes re-teaching a section idempotent (5.2.6).
 */
final class LessonTranscript
{
    public function __construct(private readonly Lesson $lesson) {}

    /**
     * Record a completed turn.
     *
     * Post: the turn is the last line of `render()`, the last message of
     * `messages()`, and its id is what `turn.end` reports.
     *
     * The insert races two ways -- two reconnecting clients, or a retried job
     * -- and both are caught by unique indexes rather than by a read-then-write
     * check that a concurrent writer can slip between. A taught section that
     * loses the race returns the row that won, so the caller still has a
     * message id to report.
     */
    public function append(TurnKind $kind, Speaker $speaker, string $content, int $sectionIndex, string $turnId): LessonMessage
    {
        return DB::transaction(function () use ($kind, $speaker, $content, $sectionIndex, $turnId): LessonMessage {
            $existing = $this->taughtSection($kind, $speaker, $sectionIndex);

            if ($existing instanceof LessonMessage) {
                return $existing;
            }

            return LessonMessage::query()->create([
                'lesson_id' => $this->lesson->id,
                'turn_id' => $turnId,
                'kind' => $kind,
                'speaker' => $speaker,
                'section_index' => $sectionIndex,
                'content' => trim($content),
                'position' => $this->nextPosition(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * The tutor's completed teaching of one section, if it exists.
     *
     * What 5.2.6's reconnect branches on: present means replay, absent means
     * teach it again.
     */
    public function taughtSection(TurnKind $kind, Speaker $speaker, int $sectionIndex): ?LessonMessage
    {
        if ($kind !== TurnKind::Section || $speaker !== Speaker::Tutor) {
            return null;
        }

        return $this->section($sectionIndex);
    }

    public function section(int $sectionIndex): ?LessonMessage
    {
        return $this->turns()
            ->where('kind', TurnKind::Section->value)
            ->where('speaker', Speaker::Tutor->value)
            ->where('section_index', $sectionIndex)
            ->first();
    }

    /**
     * Every turn, in the order they were spoken.
     *
     * @return list<LessonMessage>
     */
    public function turnsInOrder(): array
    {
        return array_values($this->turns()->get()->all());
    }

    /**
     * The conversation as a script, for `lesson.summarize` (spec 5.4.2).
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
     * This is the uncached tail of 5.1's context: the stable prefix is the
     * system prompt, and everything here follows it.
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
     * Everything said in earlier sections, and nothing from this one -- the
     * context a section turn is regenerated from after a reconnect (5.2.6).
     *
     * @return list<Message>
     */
    public function messagesBeforeSection(int $sectionIndex): array
    {
        return $this->messagesWhere(fn (Builder $query): Builder => $query->where('section_index', '<', $sectionIndex));
    }

    /**
     * Everything said before one turn, that turn excluded.
     *
     * The history half of a Q&A exchange. The student's question is already in
     * the transcript by the time the call is built -- it has to be, or the
     * tutor's reply would land at a lower position than the question it
     * answers -- and the call sends it as the outgoing message. Handing it
     * back as history too would put the same words in front of the model
     * twice, in two consecutive user turns.
     *
     * @return list<Message>
     */
    public function messagesPreceding(LessonMessage $turn): array
    {
        return $this->messagesWhere(fn (Builder $query): Builder => $query->where('position', '<', $turn->position));
    }

    /**
     * @param  \Closure(Builder<LessonMessage>): Builder<LessonMessage>  $scope
     * @return list<Message>
     */
    private function messagesWhere(\Closure $scope): array
    {
        $messages = [];

        foreach ($scope($this->turns())->cursor() as $turn) {
            $messages[] = $turn->speaker->message($turn->content);
        }

        return $messages;
    }

    /**
     * Just one section's turns, as a script, for `checkpoint.generate`.
     */
    public function renderSection(int $sectionIndex): string
    {
        $lines = [];

        foreach ($this->turns()->where('section_index', $sectionIndex)->cursor() as $turn) {
            $lines[] = $turn->speaker->label().': '.$turn->content;
        }

        return implode("\n\n", $lines);
    }

    public function isEmpty(): bool
    {
        return ! $this->turns()->exists();
    }

    /**
     * @return Builder<LessonMessage>
     */
    private function turns(): Builder
    {
        return LessonMessage::query()
            ->where('lesson_id', $this->lesson->id)
            ->orderBy('position');
    }

    private function nextPosition(): int
    {
        return 1 + (int) LessonMessage::query()
            ->where('lesson_id', $this->lesson->id)
            ->max('position');
    }
}
