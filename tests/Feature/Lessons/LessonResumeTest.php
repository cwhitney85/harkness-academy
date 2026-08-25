<?php

namespace Tests\Feature\Lessons;

use App\Courses\LessonStatus;
use App\Lessons\LessonSessions;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\StreamVocabulary;
use App\Models\LessonMessage;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.3 -- reopening restores transcript and section index.
 *
 * The manual done-condition is "kill the browser mid-section, resume,
 * complete". This is that, with the browser standing in for an HTTP client:
 * teach a section, drop the connection, come back, and finish the lesson.
 * What the student must never lose is the transcript; what they must never
 * gain is the answer key the stream withheld (5.2.3).
 */
class LessonResumeTest extends SectionStreamTestCase
{
    #[Test]
    public function reopening_returns_the_transcript_and_where_to_carry_on(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $state = $this->actingAs($this->lesson->course->student)
            ->getJson("/lessons/{$this->lesson->id}")
            ->assertOk()
            ->json();

        $this->assertSame(1, $state['resume_at']);
        $this->assertSame(1, $state['lesson']['section_index']);
        $this->assertSame(2, $state['lesson']['sections_total']);
        $this->assertSame(LessonStatus::InProgress->value, $state['lesson']['status']);
        $this->assertSame('Letters as Numbers', $state['lesson']['title']);

        $this->assertCount(1, $state['transcript']);
        $this->assertSame(LessonMessage::query()->value('content'), $state['transcript'][0]['content']);
        $this->assertSame('section', $state['transcript'][0]['kind']);
        $this->assertSame('tutor', $state['transcript'][0]['speaker']);

        $this->assertSame([true, false], array_column($state['sections'], 'taught'));
    }

    #[Test]
    public function a_resumed_checkpoint_is_still_stripped(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $response = $this->actingAs($this->lesson->course->student)
            ->getJson("/lessons/{$this->lesson->id}")
            ->assertOk();

        // Resume must not be the back door around 5.2.3.
        $response->assertDontSee('correct_option_id');
        $response->assertDontSee('A letter stands in for a quantity');

        $checkpoint = $response->json('checkpoints.0');

        $this->assertSame(1, $checkpoint['section_index']);
        $this->assertArrayNotHasKey('correct_option_id', $checkpoint['payload']['checkpoint']['questions'][0]);
    }

    #[Test]
    public function a_lesson_never_opened_resumes_at_its_first_section(): void
    {
        $state = $this->actingAs($this->lesson->course->student)
            ->getJson("/lessons/{$this->lesson->id}")
            ->assertOk()
            ->json();

        $this->assertSame(1, $state['resume_at']);
        $this->assertNull($state['lesson']['section_index']);
        $this->assertSame([], $state['transcript']);
        $this->assertSame(LessonStatus::Available->value, $state['lesson']['status']);
    }

    #[Test]
    public function a_killed_section_is_resumed_and_the_lesson_is_completed(): void
    {
        $this->scriptModel([
            // The turn that dies with the connection.
            'A variable is a letter standing in for a number.',
            // The same section, re-taught on reconnect.
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(1),
            'A coefficient is the number in front of the letter.',
            $this->checkpointResponse(2),
            // The completion chain: summary, WK, homework.
            $this->summaryResponse(),
            "## Solid\n\n- Reads graphs confidently.\n- variable, expression, coefficient.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- Equations.",
            $this->quizResponse(),
        ]);

        $student = $this->lesson->course->student;

        // The browser dies part-way through section 1: no row, no checkpoint.
        $this->killMidSection();

        $this->assertSame(0, LessonMessage::query()->count());

        // The student comes back and works to the end.
        $this->stream(1)->assertOk();
        $this->stream(2)->assertOk();

        $this->actingAs($student)
            ->postJson("/lessons/{$this->lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('lesson.status', LessonStatus::Completed->value);

        $this->assertSame(2, LessonMessage::query()->count(), 'One row per taught section, no duplicates.');
        $this->assertNotNull($this->lesson->fresh()->completed_at);
    }

    #[Test]
    public function the_stream_route_speaks_only_the_documented_vocabulary(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $response = $this->stream(1)->assertOk();

        $response->assertHeader('content-type', 'text/event-stream; charset=utf-8');
        $response->assertHeader('x-accel-buffering', 'no');

        $events = EventStream::parse($response->streamedContent());

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertContains($event['name'], StreamVocabulary::NAMES, $event['name']);
        }
    }

    #[Test]
    public function a_section_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->lesson->course->student)
            ->get("/lessons/{$this->lesson->id}/sections/9/stream")
            ->assertNotFound();
    }

    #[Test]
    public function a_locked_lesson_cannot_be_streamed(): void
    {
        $locked = $this->lesson($this->lesson->course, 2);

        $this->actingAs($locked->course->student)
            ->get("/lessons/{$locked->id}/sections/1/stream")
            ->assertStatus(422);
    }

    #[Test]
    public function another_student_cannot_read_or_stream_this_lesson(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/lessons/{$this->lesson->id}")->assertForbidden();
        $this->actingAs($intruder)->get("/lessons/{$this->lesson->id}/sections/1/stream")->assertForbidden();
    }

    /**
     * Request one section over HTTP, as a client would.
     */
    private function stream(int $index): TestResponse
    {
        $response = $this->actingAs($this->lesson->course->student)
            ->get("/lessons/{$this->lesson->id}/sections/{$index}/stream");

        // A streamed response does nothing until it is consumed.
        $response->streamedContent();

        return $response;
    }

    /**
     * The browser going away part-way through section 1.
     */
    private function killMidSection(): void
    {
        $written = 0;

        $stream = new EventStream(function () use (&$written): void {
            if (++$written > 2) {
                throw new \RuntimeException('client gone');
            }
        });

        try {
            app(LessonSessions::class)->for($this->lesson)->teachSection(1, $stream);
        } catch (\RuntimeException) {
            // Expected: this is what a dead connection looks like from here.
        }
    }
}
