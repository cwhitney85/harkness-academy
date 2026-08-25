<?php

namespace Tests\Feature\Lessons;

use App\Assessment\QuizKind;
use App\Lessons\LessonTranscript;
use App\Lessons\Pipeline\PipelineStep;
use App\Lessons\Speaker;
use App\Lessons\Streaming\TurnKind;
use App\Models\LessonMessage;
use App\Models\LessonPipelineStep;
use App\Models\Quiz;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * The constraints package 5 leans on, checked against the database.
 *
 * Every one of these backs a claim made in prose somewhere else -- "exactly
 * one message row", "a section is never given two checkpoints", "idempotent
 * per (lesson, step)". A claim enforced only by the code that happens to check
 * it first is a claim two concurrent workers can break.
 */
class LessonEngineSchemaTest extends SectionStreamTestCase
{
    #[Test]
    public function a_section_can_only_be_taught_once(): void
    {
        $transcript = new LessonTranscript($this->lesson);

        $transcript->append(TurnKind::Section, Speaker::Tutor, 'Taught.', 1, (string) Str::uuid());

        // Straight past LessonTranscript's own check, the way a second worker
        // would arrive: the index is what has to stop it.
        $this->expectException(QueryException::class);

        LessonMessage::query()->create([
            'lesson_id' => $this->lesson->id,
            'turn_id' => (string) Str::uuid(),
            'kind' => TurnKind::Section,
            'speaker' => Speaker::Tutor,
            'section_index' => 1,
            'content' => 'Taught again.',
            'position' => 99,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function a_section_may_hold_any_number_of_questions(): void
    {
        $transcript = new LessonTranscript($this->lesson);

        $transcript->append(TurnKind::Section, Speaker::Tutor, 'Taught.', 1, (string) Str::uuid());
        $transcript->append(TurnKind::Qa, Speaker::Student, 'Why?', 1, (string) Str::uuid());
        $transcript->append(TurnKind::Qa, Speaker::Tutor, 'Because.', 1, (string) Str::uuid());
        $transcript->append(TurnKind::Qa, Speaker::Student, 'And?', 1, (string) Str::uuid());

        $this->assertSame(4, LessonMessage::query()->count());
        $this->assertSame([1, 2, 3, 4], LessonMessage::query()->orderBy('position')->pluck('position')->all());
    }

    #[Test]
    public function a_section_can_only_hold_one_checkpoint(): void
    {
        $this->quiz(QuizKind::Checkpoint, 1);

        $this->expectException(QueryException::class);

        $this->quiz(QuizKind::Checkpoint, 1);
    }

    #[Test]
    public function a_lesson_can_only_hold_one_homework(): void
    {
        $this->quiz(QuizKind::Homework, null);

        // The null section index is why this needs an expression index: plain
        // unique constraints treat nulls as distinct, so a lesson would be
        // able to collect homework quizzes forever.
        $this->expectException(QueryException::class);

        $this->quiz(QuizKind::Homework, null);
    }

    #[Test]
    public function different_sections_and_different_kinds_coexist(): void
    {
        $this->quiz(QuizKind::Checkpoint, 1);
        $this->quiz(QuizKind::Checkpoint, 2);
        $this->quiz(QuizKind::Homework, null);

        $this->assertSame(3, Quiz::query()->count());
    }

    #[Test]
    public function a_pipeline_step_can_only_be_recorded_once(): void
    {
        LessonPipelineStep::query()->create([
            'lesson_id' => $this->lesson->id,
            'step' => PipelineStep::Summarize,
            'completed_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        LessonPipelineStep::query()->create([
            'lesson_id' => $this->lesson->id,
            'step' => PipelineStep::Summarize,
            'completed_at' => now(),
        ]);
    }

    private function quiz(QuizKind $kind, ?int $sectionIndex): Quiz
    {
        return $this->lesson->quizzes()->create([
            'user_id' => $this->lesson->course->user_id,
            'course_id' => $this->lesson->course_id,
            'kind' => $kind,
            'section_index' => $sectionIndex,
            'payload' => ['checkpoint' => ['section_index' => $sectionIndex ?? 0, 'questions' => []]],
        ]);
    }
}
