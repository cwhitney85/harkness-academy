<?php

namespace Tests\Feature\Lessons;

use App\Courses\LessonStatus;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.2.4 -- `POST /lessons/{lesson}/checkpoints/{checkpoint}/answers`.
 *
 * Right, wrong, repeat and out-of-order are the four the done-condition names.
 * The fifth thing under test is what does *not* happen: nothing here moves the
 * lesson, because checkpoints inform and never gate Continue.
 */
class CheckpointGradingTest extends SectionStreamTestCase
{
    private Quiz $checkpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scriptModel(['A variable is a letter standing in for a number.', $this->threeQuestionCheckpoint()]);

        $frames = [];
        $this->teach(1, $frames);

        $this->checkpoint = Quiz::query()->firstOrFail();
    }

    #[Test]
    public function a_right_answer_comes_back_with_the_key_and_the_explanation(): void
    {
        $response = $this->answer('q1', 'a');

        $response->assertOk()->assertExactJson([
            'correct' => true,
            'correct_option_id' => 'a',
            'explanation' => 'A letter stands in for a quantity that has not been pinned down.',
        ]);

        $attempt = QuizAttempt::query()->firstOrFail();

        $this->assertTrue($attempt->correct);
        $this->assertSame(1, $attempt->attempt);
        $this->assertSame($this->lesson->course->user_id, $attempt->user_id);
    }

    #[Test]
    public function a_wrong_answer_is_told_so_and_still_shown_the_key(): void
    {
        $this->answer('q1', 'b')->assertOk()->assertJson([
            'correct' => false,
            'correct_option_id' => 'a',
        ]);

        $this->assertFalse(QuizAttempt::query()->firstOrFail()->correct);
    }

    #[Test]
    public function answering_again_records_a_second_attempt_rather_than_replacing_the_first(): void
    {
        $this->answer('q1', 'b')->assertOk();
        $this->answer('q1', 'a')->assertOk();

        $attempts = QuizAttempt::query()->where('question_id', 'q1')->orderBy('attempt')->get();

        $this->assertCount(2, $attempts);
        $this->assertSame([1, 2], $attempts->pluck('attempt')->all());
        $this->assertSame([false, true], $attempts->pluck('correct')->all());
    }

    #[Test]
    public function questions_may_be_answered_in_any_order(): void
    {
        $this->answer('q3', 'a')->assertOk();
        $this->answer('q1', 'a')->assertOk();
        $this->answer('q2', 'b')->assertOk();

        $this->assertSame(
            ['q3', 'q1', 'q2'],
            QuizAttempt::query()->orderBy('id')->pluck('question_id')->all(),
        );
    }

    #[Test]
    public function grading_costs_nothing_and_moves_nothing(): void
    {
        $before = UsageEvent::query()->count();

        $this->answer('q1', 'b')->assertOk();

        // Ledger D5: grading is code. The answer key is already in the row.
        $this->assertSame($before, UsageEvent::query()->count());

        $lesson = $this->lesson->fresh();

        $this->assertSame(LessonStatus::InProgress, $lesson->status);
        $this->assertSame(1, $lesson->section_index);
        $this->assertNull($lesson->completed_at);
    }

    #[Test]
    public function a_question_this_checkpoint_does_not_ask_is_refused(): void
    {
        $this->answer('q9', 'a')->assertStatus(422);

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    #[Test]
    public function an_option_the_question_does_not_offer_is_refused(): void
    {
        $this->answer('q1', 'z')->assertStatus(422);

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    #[Test]
    public function a_checkpoint_belonging_to_another_lesson_is_not_found(): void
    {
        // The student's own next lesson, so the policy passes and the only
        // thing left to refuse it is the scope check.
        $other = $this->lesson($this->lesson->course, 2);

        $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$other->id}/checkpoints/{$this->checkpoint->id}/answers", [
                'question_id' => 'q1',
                'option_id' => 'a',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function another_student_cannot_answer_this_checkpoint(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson($this->url(), ['question_id' => 'q1', 'option_id' => 'a'])
            ->assertForbidden();

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    #[Test]
    public function a_signed_out_visitor_is_turned_away(): void
    {
        $this->postJson($this->url(), ['question_id' => 'q1', 'option_id' => 'a'])
            ->assertUnauthorized();
    }

    private function answer(string $questionId, string $optionId): TestResponse
    {
        return $this->actingAs($this->lesson->course->student)
            ->postJson($this->url(), ['question_id' => $questionId, 'option_id' => $optionId]);
    }

    private function url(): string
    {
        return "/lessons/{$this->lesson->id}/checkpoints/{$this->checkpoint->id}/answers";
    }

    /**
     * @return array<string, mixed>
     */
    private function threeQuestionCheckpoint(): array
    {
        $checkpoint = $this->checkpointResponse();

        $checkpoint['checkpoint']['questions'][] = [
            'id' => 'q2',
            'prompt' => 'What is the coefficient in 7y?',
            'options' => [
                ['id' => 'a', 'text' => 'y'],
                ['id' => 'b', 'text' => '7'],
                ['id' => 'c', 'text' => '7y'],
            ],
            'correct_option_id' => 'b',
            'explanation' => 'The coefficient is the number multiplying the variable.',
            'concept_tag' => 'variable',
        ];

        $checkpoint['checkpoint']['questions'][] = [
            'id' => 'q3',
            'prompt' => 'Which of these is an expression?',
            'options' => [
                ['id' => 'a', 'text' => '2x + 1'],
                ['id' => 'b', 'text' => 'Add two to x, then stop'],
                ['id' => 'c', 'text' => 'x is four'],
            ],
            'correct_option_id' => 'a',
            'explanation' => 'An expression combines numbers and variables without asserting equality.',
            'concept_tag' => 'variable',
        ];

        return $checkpoint;
    }
}
