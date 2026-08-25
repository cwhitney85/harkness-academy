<?php

namespace Tests\Feature\Lessons;

use App\Ai\Tier;
use App\Lessons\LessonTranscript;
use App\Lessons\Speaker;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\TurnKind;
use App\Models\LessonMessage;
use App\Models\Quiz;
use App\Models\UsageEvent;
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
    public function the_turn_bills_under_its_own_prompt_key_at_the_session_tier(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a letter can stand for a different number in a different problem.',
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $this->ask('Can a letter stand for more than one number?')->assertOk();

        $event = UsageEvent::query()->where('prompt_key', 'lesson.qa')->firstOrFail();

        // Q&A volume is the least predictable cost in a lesson, so it gets its
        // own bucket rather than sharing `lesson.section`'s (ledger D14).
        $this->assertSame('lesson.qa', $event->feature);
        $this->assertSame(Tier::Mid->value, $event->tier, 'Inherited from the session, so the cached prefix still hits.');

        $this->assertSame(
            1,
            UsageEvent::query()->where('prompt_key', 'lesson.section')->count(),
            'The teaching turn and the question are separate rows.',
        );
    }

    #[Test]
    public function the_question_is_sent_once_not_twice(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a letter can stand for a different number in a different problem.',
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $question = 'Can a letter stand for more than one number?';

        $this->ask($question)->assertOk();

        // The question is the outgoing message; the history handed alongside
        // it stops short of the question, so the model does not read the same
        // words twice in two consecutive user turns.
        $lesson = $this->lesson->fresh();
        $transcript = new LessonTranscript($lesson);
        $asked = LessonMessage::query()->where('speaker', Speaker::Student->value)->firstOrFail();

        $history = $transcript->messagesPreceding($asked);

        $this->assertCount(1, $history, 'Only the taught section precedes the question.');
        $this->assertStringNotContainsString($question, (string) $history[0]->content);

        // Trimmed: the prompt file's body is one placeholder and a newline,
        // as every prompt file ends.
        $this->assertSame($question, trim($this->sent[2]), 'The rendered `lesson.qa` body is the question itself.');
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
