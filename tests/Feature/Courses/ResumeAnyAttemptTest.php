<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\LessonStatus;
use App\Lessons\LessonContext;
use App\Memory\MemoryTree;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Lessons\LessonEngineTestCase;

/**
 * Spec 16.4.1 -- any withdrawn attempt can be resumed (ledger D64).
 *
 * D7b allowed only the newest because attempts shared document paths, so an
 * older attempt would have been taught from a newer one's notes. What this
 * proves is that the reason is gone: an older attempt comes back at its own
 * lesson, and its tutor reads its own guidance.
 */
class ResumeAnyAttemptTest extends LessonEngineTestCase
{
    #[Test]
    public function an_older_attempt_resumes_at_its_own_lesson_with_its_own_guidance(): void
    {
        $transitions = app(CourseTransitions::class);
        $tree = app(MemoryTree::class);

        $first = $this->activeCourse();
        $this->lesson($first, 1)->forceFill(['status' => LessonStatus::Completed])->save();
        $tree->put($first, $this->lesson($first, 2)->guidance_path, 'Attempt one: start from the balance picture.');
        $transitions->withdraw($first);

        $second = $this->activeCourse(['user_id' => $first->user_id, 'attempt' => 2]);
        $tree->put($second, $this->lesson($second, 2)->guidance_path, 'Attempt two: start from the number line.');
        $second->forceFill(['status' => CourseStatus::FinalExam])->save();
        $transitions->complete($second);

        $this->actingAs($first->student)
            ->post(route('courses.resume', $first))
            ->assertRedirect(route('lessons.show', $this->lesson($first, 2)));

        $prompt = app(LessonContext::class)->systemPromptFor($this->lesson($first, 2));

        $this->assertStringContainsString('Attempt one: start from the balance picture.', $prompt);
        $this->assertStringNotContainsString('Attempt two', $prompt);
    }
}
