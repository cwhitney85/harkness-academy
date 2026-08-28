<?php

namespace App\Lessons;

use App\Assessment\QuizKind;
use App\Lessons\Checkpoints\Checkpoint;
use App\Models\Lesson;
use App\Models\LessonMessage;
use App\Models\Quiz;

/**
 * Everything a client needs to pick a lesson back up (spec 5.3).
 *
 * 5.3's requirement is "reopening restores transcript + section index", and
 * this is that pair plus the two things a client cannot derive from them: the
 * section titles, and the checkpoints already earned.
 *
 * Every checkpoint here is in its stripped form (5.2.3). Resuming must not be
 * the back door that hands out the answer key the stream withheld -- a student
 * who reconnects mid-checkpoint gets exactly what they had before.
 */
final readonly class LessonState
{
    private function __construct(
        private Lesson $lesson,
        private LessonSections $sections,
        private LessonTranscript $transcript,
    ) {}

    public static function of(Lesson $lesson): self
    {
        return new self($lesson, LessonSections::of($lesson), new LessonTranscript($lesson));
    }

    /**
     * The section a client should reopen at.
     *
     * The furthest section reached, not the one after it: a student who was
     * killed mid-section 3 comes back to section 3, and if its turn completed
     * it is replayed rather than re-taught (5.2.6).
     *
     * A lesson never opened resumes at 1 -- there is nothing to restore, and
     * "start at the beginning" is the correct restoration of nothing.
     */
    public function resumeAt(): int
    {
        return $this->lesson->section_index ?? 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $entry = $this->lesson->syllabusEntry();

        return [
            'lesson' => [
                'id' => $this->lesson->id,
                'position' => $this->lesson->position,
                'title' => $entry['title'],
                'objective' => $entry['objective'],
                'status' => $this->lesson->status->value,
                'section_index' => $this->lesson->section_index,
                'sections_total' => count($this->sections),
                'completed_at' => $this->lesson->completed_at?->toIso8601String(),
            ],
            'resume_at' => $this->resumeAt(),
            'sections' => $this->sectionRows(),
            'transcript' => $this->transcriptRows(),
            'checkpoints' => $this->checkpointRows(),
            'limits' => self::limits(),
        ];
    }

    /**
     * The bounds the screen has to respect, in the units it measures in.
     *
     * Not state -- the same numbers on every lesson -- but they travel with the
     * state because the alternative is the screen not knowing them. A limit the
     * client cannot see is a limit the student discovers by being refused after
     * they have already committed: highlighted the passage, pressed the button,
     * waited. Published, the screen can say "that is about as much as the
     * side-tab explains at once" while the selection is still being dragged.
     *
     * Each number is read from the class that enforces it, never restated here.
     * A published limit that disagreed with the enforced one would be worse
     * than no published limit at all -- it would make the screen confidently
     * wrong.
     *
     * @return array<string, int>
     */
    private static function limits(): array
    {
        return [
            'question_characters' => LessonSession::MAX_QUESTION,
            'highlight_characters' => SelectionExplainer::MAX_SELECTION,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sectionRows(): array
    {
        $rows = [];

        for ($index = 1; $index <= count($this->sections); $index++) {
            $rows[] = [
                'index' => $index,
                'title' => $this->sections->title($index),
                'concepts' => $this->sections->concepts($index),
                'taught' => $this->transcript->section($index) instanceof LessonMessage,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transcriptRows(): array
    {
        return array_map(
            static fn (LessonMessage $message): array => [
                'message_id' => $message->id,
                'turn_id' => $message->turn_id,
                'kind' => $message->kind->value,
                'speaker' => $message->speaker->value,
                'section_index' => $message->section_index,
                'content' => $message->content,
            ],
            $this->transcript->turnsInOrder(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checkpointRows(): array
    {
        /** @var list<Quiz> $quizzes */
        $quizzes = $this->lesson->quizzes()
            ->ofKind(QuizKind::Checkpoint)
            ->orderBy('section_index')
            ->get()
            ->all();

        return array_map(
            static fn (Quiz $quiz): array => [
                'checkpoint_id' => $quiz->id,
                'section_index' => $quiz->section_index,
                // Stripped, always (spec 5.2.3).
                'payload' => Checkpoint::of($quiz)->clientPayload(),
            ],
            $quizzes,
        );
    }
}
