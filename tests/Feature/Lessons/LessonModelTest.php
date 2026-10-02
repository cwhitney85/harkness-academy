<?php

namespace Tests\Feature\Lessons;

use App\Ai\Tier;
use App\Lessons\LessonSessions;
use App\Models\AiModel;
use App\Models\AiTier;
use App\Models\UsageEvent;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 14.1.2 and 14.1.6 -- a lesson runs on the model its tier names when it
 * starts, and keeps that model when the tier moves.
 */
class LessonModelTest extends LessonEngineTestCase
{
    #[Test]
    public function moving_mid_leaves_an_open_lesson_on_its_model_and_starts_the_next_on_the_new_one(): void
    {
        $this->scriptModel([
            'Section one.', $this->checkpointResponse(),
            'Section two.', $this->checkpointResponse(2, 'expression'),
            'Lesson two, section one.', $this->checkpointResponse(),
        ]);

        $course = $this->activeCourse();
        $lesson = $this->lesson($course);
        $original = Tier::Mid->primary()['id'];
        $frames = [];

        app(LessonSessions::class)->for($lesson)->teachSection(1, $this->collectingStream($frames));

        AiTier::query()->where('name', 'mid')->sole()->update(['ai_model_id' => $this->otherModel()->id]);

        app(LessonSessions::class)->for($lesson->fresh())->teachSection(2, $this->collectingStream($frames));

        $this->assertSame(
            [$original, $original],
            UsageEvent::query()->where('prompt_key', 'lesson.section')->orderBy('id')->pluck('ai_model_id')->all(),
            'Both sections of the open lesson ran on the model it started on.',
        );

        // Lesson 2 opens once lesson 1 is complete; the fixture only needs it
        // to be a lesson that has not started.
        $next = $this->lesson($course, 2);
        $next->forceFill(['status' => $lesson->status])->save();

        app(LessonSessions::class)->for($next)->teachSection(1, $this->collectingStream($frames));

        $this->assertSame(
            $this->otherModel()->id,
            UsageEvent::query()->where('prompt_key', 'lesson.section')->latest('id')->value('ai_model_id'),
            'A lesson that starts after the change runs on the new model, with no code change.',
        );
    }

    private function otherModel(): AiModel
    {
        return AiModel::query()->firstOrCreate(['provider' => 'anthropic', 'model' => 'another-model'], [
            'display_name' => 'Another',
            'input_per_mtok' => 1, 'output_per_mtok' => 1, 'cache_read_per_mtok' => 1, 'cache_write_per_mtok' => 1,
        ]);
    }
}
