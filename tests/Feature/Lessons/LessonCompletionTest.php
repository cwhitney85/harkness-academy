<?php

namespace Tests\Feature\Lessons;

use App\Assessment\QuizKind;
use App\Courses\LessonStatus;
use App\Lessons\CompleteLesson;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Pipeline\CompletionPipeline;
use App\Lessons\Pipeline\PipelineStep;
use App\Lessons\Pipeline\SummarizeLesson;
use App\Lessons\Pipeline\UnlockEscapeHatch;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Lesson;
use App\Models\LessonPipelineStep;
use App\Models\Quiz;
use App\Models\UsageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.4.1, 5.4.5 and 5.4.6 -- completion, the chain, and what happens when
 * the chain does not finish.
 *
 * The asymmetry under test throughout: completion is synchronous and
 * irrevocable, everything downstream of it is queued and allowed to fail. A
 * test that let those two blur would let a provider outage un-finish a
 * student's lesson.
 */
class LessonCompletionTest extends SectionStreamTestCase
{
    private const UPDATED_WK = "## Solid\n\n- Reads graphs confidently.\n- variable, expression and coefficient, used unprompted.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- Equations.";

    #[Test]
    public function the_happy_path_shows_all_five_effects(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $lesson->refresh();

        // 1. The lesson is completed.
        $this->assertSame(LessonStatus::Completed, $lesson->status);
        $this->assertNotNull($lesson->completed_at);

        // 2. The summary is persisted, both as JSON and as a document.
        $this->assertNotNull($lesson->summary_json);
        $this->assertSame(
            ['variable', 'expression', 'coefficient'],
            $lesson->summary()->slugs(),
        );

        $document = app(MemoryTree::class)->get($lesson->course->student, SummaryDocument::pathFor($lesson));

        $this->assertNotNull($document);
        $this->assertStringContainsString('## Concepts', $document);

        // 3. The working-knowledge document is a new version.
        $this->assertSame(
            self::UPDATED_WK,
            app(MemoryTree::class)->get($lesson->course->student, $lesson->course->workingKnowledgePath()),
        );

        // 4. Homework exists.
        $this->assertSame(1, $lesson->quizzes()->ofKind(QuizKind::Homework)->count());

        // 5. The next lesson is open.
        $this->assertSame(LessonStatus::Available, $lesson->next()->status);
    }

    #[Test]
    public function every_step_is_recorded_in_the_order_the_chain_runs_them(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $this->assertSame(
            PipelineStep::inOrder(),
            LessonPipelineStep::query()->orderBy('id')->pluck('step')->all(),
        );

        $this->assertTrue(app(CompletionPipeline::class)->isFinished($lesson->fresh()));
    }

    #[Test]
    public function re_dispatching_the_chain_duplicates_nothing(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $calls = UsageEvent::query()->count();
        $versions = app(MemoryTree::class)->document(
            $lesson->course->student,
            $lesson->course->workingKnowledgePath(),
        )->versionId;

        // Nothing scripted: a second run that called the model at all would
        // fail the test rather than quietly cost money.
        $this->scriptModel([]);

        app(CompletionPipeline::class)->resume($lesson->fresh());

        $this->assertSame($calls, UsageEvent::query()->count(), 'Spec 5.4.1: idempotent per (lesson, step).');
        $this->assertSame(4, LessonPipelineStep::query()->count());
        $this->assertSame(1, Quiz::query()->ofKind(QuizKind::Homework)->count());
        $this->assertSame(
            $versions,
            app(MemoryTree::class)->document($lesson->course->student, $lesson->course->workingKnowledgePath())->versionId,
            'No second WK version written over a good one.',
        );
    }

    #[Test]
    public function completing_twice_stamps_one_time_and_queues_one_chain(): void
    {
        $lesson = $this->taughtLesson();

        Bus::fake();

        app(CompleteLesson::class)($lesson);

        $completedAt = $lesson->fresh()->completed_at;

        Carbon::setTestNow(now()->addHour());

        app(CompleteLesson::class)($lesson->fresh());

        $this->assertEquals($completedAt, $lesson->fresh()->completed_at);

        Bus::assertDispatchedTimes(SummarizeLesson::class, 1);
    }

    #[Test]
    public function a_lesson_whose_last_section_was_never_taught_cannot_be_completed(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $this->expectException(LessonNotOpenable::class);

        try {
            app(CompleteLesson::class)($this->lesson->fresh());
        } finally {
            $this->assertNull($this->lesson->fresh()->completed_at);
        }
    }

    #[Test]
    public function the_student_keeps_their_completion_when_the_chain_dies(): void
    {
        Log::spy();

        $lesson = $this->taughtLesson();

        // The summarizer refuses, three times over.
        $this->scriptModel(array_fill(0, 3, 'not a summary'));

        app(CompleteLesson::class)($lesson);

        $lesson->refresh();

        // Spec 5.4.5: completion is sync and irrevocable.
        $this->assertSame(LessonStatus::Completed, $lesson->status);
        $this->assertNotNull($lesson->completed_at);

        // And the chain parked with an alert naming the way out.
        $this->assertNotNull($lesson->pipeline_parked_at);
        $this->assertSame(0, LessonPipelineStep::query()->count());
        $this->assertSame(LessonStatus::Locked, $lesson->next()->status);

        Log::shouldHaveReceived('alert')->once();
    }

    #[Test]
    public function the_artisan_command_finishes_a_parked_chain(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel(array_fill(0, 3, 'not a summary'));

        app(CompleteLesson::class)($lesson);

        $this->assertNotNull($lesson->fresh()->pipeline_parked_at);

        // The provider comes back.
        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        $this->artisan('lessons:retry-pipeline', ['lesson' => $lesson->id])->assertSuccessful();

        $lesson->refresh();

        $this->assertTrue(app(CompletionPipeline::class)->isFinished($lesson));
        $this->assertNull($lesson->pipeline_parked_at);
        $this->assertSame(LessonStatus::Available, $lesson->next()->status);
    }

    #[Test]
    public function a_retry_resumes_at_the_step_that_broke(): void
    {
        $lesson = $this->taughtLesson();

        // Summary lands; the WK update refuses three times.
        $this->scriptModel([$this->summaryResponse(), '', '', '']);

        app(CompleteLesson::class)($lesson);

        $this->assertSame([PipelineStep::Summarize], LessonPipelineStep::query()->pluck('step')->all());

        $summaryCalls = UsageEvent::query()->where('prompt_key', 'lesson.summarize')->count();

        $this->scriptModel([self::UPDATED_WK, $this->quizResponse()]);

        $this->artisan('lessons:retry-pipeline', ['lesson' => $lesson->id])->assertSuccessful();

        $this->assertSame(
            $summaryCalls,
            UsageEvent::query()->where('prompt_key', 'lesson.summarize')->count(),
            'The summary was already written; the retry must not pay for it again.',
        );
        $this->assertTrue(app(CompletionPipeline::class)->isFinished($lesson->fresh()));
    }

    #[Test]
    public function the_next_lesson_opens_only_after_the_working_knowledge_is_current(): void
    {
        $lesson = $this->taughtLesson();

        // Everything up to the unlock succeeds; the unlock never runs because
        // homework refuses. 5.4.6: the next lesson should see an updated WK,
        // so it must not open before the chain reaches its end.
        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, 'not a quiz', 'not a quiz', 'not a quiz']);

        app(CompleteLesson::class)($lesson);

        $this->assertSame(LessonStatus::Locked, $lesson->fresh()->next()->status);
        $this->assertSame(
            [PipelineStep::Summarize, PipelineStep::WorkingKnowledge],
            LessonPipelineStep::query()->orderBy('id')->pluck('step')->all(),
        );
    }

    #[Test]
    public function a_chain_parked_long_enough_unlocks_the_next_lesson_anyway(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel(array_fill(0, 3, 'not a summary'));

        app(CompleteLesson::class)($lesson);

        $hatch = app(UnlockEscapeHatch::class);

        $this->assertFalse($hatch->isDue($lesson->fresh()), 'Not yet -- the retries only just gave up.');
        $this->assertSame([], $hatch->sweep());

        Carbon::setTestNow(now()->addMinutes((int) config('lessons.unlock_escape_hatch_minutes') + 1));

        $this->assertTrue($hatch->isDue($lesson->fresh()));

        $this->artisan('lessons:unlock-stalled')->assertSuccessful();

        // A stale working-knowledge document beats a stuck course (5.4.6).
        $this->assertSame(LessonStatus::Available, $lesson->fresh()->next()->status);
        $this->assertNotNull($lesson->fresh()->pipeline_parked_at, 'The chain is still parked and still retryable.');
    }

    #[Test]
    public function the_summarizer_is_handed_the_transcript_and_the_code_computed_grades(): void
    {
        $lesson = $this->taughtLesson(answer: true);

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $prompt = $this->sent[0];

        $this->assertStringContainsString('A variable is a letter standing in for a number.', $prompt);
        $this->assertStringContainsString('## Objective', $prompt, 'The GUIDANCE document goes in whole.');

        // 5.4.2: the model interprets grades, it does not recompute them.
        $this->assertStringContainsString('### Section 1', $prompt);
        $this->assertStringContainsString('wrong first time', $prompt);
        $this->assertStringContainsString('(concept: variable)', $prompt);
    }

    #[Test]
    public function homework_is_aimed_by_the_syllabus_and_the_summary_together(): void
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $prompt = $this->sent[2];

        $this->assertStringContainsString('Translating short word problems into expressions.', $prompt);
        $this->assertStringContainsString('shopping list', $prompt);

        $homework = $lesson->quizzes()->ofKind(QuizKind::Homework)->firstOrFail();

        $this->assertNull($homework->section_index, 'Homework belongs to the lesson, not a section.');
        $this->assertCount(1, $homework->payload['quiz']['suggested_reading']);
    }

    /**
     * Teach both sections of lesson 1, so it is completable.
     */
    private function taughtLesson(bool $answer = false): Lesson
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(1),
            'A coefficient is the number in front of the letter.',
            $this->checkpointResponse(2),
        ]);

        $first = [];
        $this->teach(1, $first);

        if ($answer) {
            $checkpoint = Quiz::query()->where('section_index', 1)->firstOrFail();

            $this->actingAs($this->lesson->course->student)
                ->postJson("/lessons/{$this->lesson->id}/checkpoints/{$checkpoint->id}/answers", [
                    'question_id' => 'q1',
                    'option_id' => 'b',
                ])
                ->assertOk();
        }

        $second = [];
        $this->teach(2, $second);

        return $this->lesson->fresh();
    }
}
