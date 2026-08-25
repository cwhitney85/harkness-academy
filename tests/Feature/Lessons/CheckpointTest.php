<?php

namespace Tests\Feature\Lessons;

use App\Assessment\QuizKind;
use App\Lessons\Checkpoints\Checkpoint;
use App\Lessons\Checkpoints\CheckpointGenerator;
use App\Models\Quiz;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.2.1, 5.2.2 and 5.2.3 -- generation, the contract, and what the
 * student is allowed to see.
 *
 * The rule that separates this from every other generator: it fails **open**.
 * A checkpoint that will not generate costs a checkpoint; failing closed here
 * would cost the student a lesson they are standing in the middle of.
 */
class CheckpointTest extends SectionStreamTestCase
{
    #[Test]
    public function a_taught_section_gets_a_checkpoint_stored_with_its_answer_key(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $quiz = Quiz::query()->firstOrFail();

        $this->assertSame(QuizKind::Checkpoint, $quiz->kind);
        $this->assertSame(1, $quiz->section_index);
        $this->assertSame($this->lesson->id, $quiz->quizzable_id);
        $this->assertSame($this->lesson->course_id, $quiz->course_id);

        // 5.2.2's lossless round-trip: the key is in the row, and reading the
        // row back gives the same document the model produced.
        $stored = Checkpoint::of($quiz->fresh());

        $this->assertSame('a', $stored->question('q1')['correct_option_id']);
        $this->assertSame(1, $stored->sectionIndex());
        $this->assertEquals($this->checkpointResponse(), $stored->payload());
    }

    #[Test]
    public function the_streamed_payload_carries_no_answer_key(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        // 5.2.3's done-condition, checked against the bytes rather than the
        // object: nothing on the wire says which option is right.
        $wire = implode('', $frames);

        $this->assertStringNotContainsString('correct_option_id', $wire);
        $this->assertStringNotContainsString('explanation', $wire);
        $this->assertStringNotContainsString('A letter stands in for a quantity', $wire);

        $question = $this->eventNamed($frames, 'checkpoint.ready')['checkpoint']['questions'][0];

        $this->assertSame('q1', $question['id']);
        $this->assertCount(3, $question['options'], 'The options themselves still go out.');
        $this->assertArrayNotHasKey('correct_option_id', $question);
        $this->assertArrayNotHasKey('explanation', $question);
    }

    #[Test]
    public function one_bad_response_is_retried(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            'not a checkpoint at all',
            $this->checkpointResponse(),
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame(1, Quiz::query()->count());
        $this->assertSame(2, UsageEvent::query()->where('prompt_key', 'checkpoint.generate')->count());
        $this->assertContains('checkpoint.ready', $this->eventNamesIn($frames));
    }

    #[Test]
    public function two_bad_responses_fail_open_and_the_lesson_carries_on(): void
    {
        Log::spy();

        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            'nope',
            'still nope',
            'The next section teaches building expressions.',
            $this->checkpointResponse(2),
        ]);

        $first = [];
        $this->teach(1, $first);

        $this->assertSame(0, Quiz::query()->count(), 'No half-written checkpoint survives.');
        $this->assertSame(
            ['turn.start', 'turn.delta', 'turn.end'],
            $this->eventShapeIn($first),
            'The section completes quizless (spec 5.2.1).',
        );

        Log::shouldHaveReceived('warning')->once();

        // And the lesson advances, which is the whole point of failing open.
        $second = [];
        $this->teach(2, $second);

        $this->assertContains('checkpoint.ready', $this->eventNamesIn($second));
    }

    #[Test]
    public function a_key_that_names_no_option_is_rejected(): void
    {
        $broken = $this->checkpointResponse();
        $broken['checkpoint']['questions'][0]['correct_option_id'] = 'z';

        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $broken,
            $broken,
        ]);

        $frames = [];
        $this->teach(1, $frames);

        // Every answer would be wrong forever. Better no checkpoint at all.
        $this->assertSame(0, Quiz::query()->count());
    }

    #[Test]
    public function a_concept_tag_the_syllabus_does_not_declare_is_dropped_not_fatal(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(conceptTag: 'quadratic-formula'),
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $quiz = Quiz::query()->firstOrFail();

        // 5.2.2 makes the tag a SHOULD -- droppable without breaking the loop.
        // Keeping it would seed a refresher quiz with a concept no lesson
        // taught.
        $this->assertNull(Checkpoint::of($quiz)->question('q1')['concept_tag']);
        $this->assertContains('checkpoint.ready', $this->eventNamesIn($frames));
    }

    #[Test]
    public function a_declared_concept_tag_survives(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(conceptTag: 'variable'),
        ]);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame('variable', Checkpoint::of(Quiz::query()->firstOrFail())->question('q1')['concept_tag']);
    }

    #[Test]
    public function the_prompt_gets_the_section_transcript_and_the_section_plan(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $prompt = $this->sent[1];

        $this->assertStringContainsString('A variable is a letter standing in for a number.', $prompt);
        $this->assertStringContainsString('Section 1 of 2: **What a variable is**', $prompt);
        $this->assertStringContainsString('Concepts: variable', $prompt);
    }

    #[Test]
    public function a_section_is_never_given_two_checkpoints(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $generator = app(CheckpointGenerator::class);

        $again = $generator->forSection($this->lesson->fresh(), 1);

        $this->assertSame(Quiz::query()->firstOrFail()->id, $again->id);
        $this->assertSame(1, Quiz::query()->count());
        $this->assertSame(1, UsageEvent::query()->where('prompt_key', 'checkpoint.generate')->count());
    }
}
