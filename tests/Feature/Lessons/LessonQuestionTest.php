<?php

namespace Tests\Feature\Lessons;

use App\Lessons\Speaker;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\TurnKind;
use App\Models\LessonMessage;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * The free Q&A turns of ledger D5, in 5.2.5's vocabulary.
 *
 * A question asked mid-lesson is part of the lesson: it joins the transcript,
 * so the next section is taught by a model that remembers answering it and the
 * summarizer sees what the student wanted to know. That is the line between
 * this and the side-tab explainer of 5.5, which leaves the transcript
 * byte-identical.
 */
class LessonQuestionTest extends SectionStreamTestCase
{
    #[Test]
    public function a_question_and_its_answer_both_join_the_transcript(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a letter can stand for a different number in a different problem.',
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $this->ask('Can a letter stand for more than one number?')->assertOk();

        $turns = LessonMessage::query()->orderBy('position')->get();

        $this->assertCount(3, $turns);

        $this->assertSame(TurnKind::Qa, $turns[1]->kind);
        $this->assertSame(Speaker::Student, $turns[1]->speaker);
        $this->assertSame('Can a letter stand for more than one number?', $turns[1]->content);
        $this->assertSame(1, $turns[1]->section_index, 'A question happens inside a section.');

        $this->assertSame(TurnKind::Qa, $turns[2]->kind);
        $this->assertSame(Speaker::Tutor, $turns[2]->speaker);
    }

    #[Test]
    public function the_answer_streams_as_a_qa_turn_with_no_section_fields(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a letter can stand for a different number in a different problem.',
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $response = $this->ask('Can a letter stand for more than one number?')->assertOk();

        $events = EventStream::parse($response->streamedContent());

        $start = $events[0];

        $this->assertSame('turn.start', $start['name']);
        $this->assertSame(TurnKind::Qa->value, $start['data']['kind']);
        $this->assertArrayNotHasKey('section_index', $start['data'], 'A Q&A turn has no section to draw.');

        $this->assertSame('turn.end', $events[count($events) - 1]['name'], 'No checkpoint follows a question.');
    }

    #[Test]
    public function the_question_does_not_generate_a_second_checkpoint(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a letter can stand for a different number in a different problem.',
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $this->ask('Can a letter stand for more than one number?')->assertOk();

        $this->assertSame(1, Quiz::query()->count());
    }

    #[Test]
    public function a_question_needs_words(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$this->lesson->id}/questions", ['text' => ''])
            ->assertStatus(422);
    }

    #[Test]
    public function another_student_cannot_ask_a_question_in_this_lesson(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson("/lessons/{$this->lesson->id}/questions", ['text' => 'Hello?'])
            ->assertForbidden();
    }

    private function ask(string $text): TestResponse
    {
        $response = $this->actingAs($this->lesson->course->student)
            ->post("/lessons/{$this->lesson->id}/questions", ['text' => $text]);

        $response->streamedContent();

        return $response;
    }
}
