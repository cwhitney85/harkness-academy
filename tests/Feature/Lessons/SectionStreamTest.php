<?php

namespace Tests\Feature\Lessons;

use App\Courses\LessonStatus;
use App\Lessons\LessonSessions;
use App\Lessons\Speaker;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\StreamEvent;
use App\Lessons\Streaming\StreamVocabulary;
use App\Lessons\Streaming\TurnKind;
use App\Models\LessonMessage;
use App\Models\Quiz;
use App\Models\UsageEvent;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Spec 5.2.5 and 5.2.6 -- the vocabulary and the transport.
 *
 * The done-conditions in one sentence each: a recorded transcript contains
 * nothing outside the five documented events, and killing a stream mid-section
 * then reconnecting leaves exactly one message row and a completed section.
 */
class SectionStreamTest extends SectionStreamTestCase
{
    #[Test]
    public function a_taught_section_streams_start_deltas_and_end(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        // No `checkpoint.ready`: a checkpoint that does not exist yet is
        // written on the queue, not in the stream (spec 11.1).
        $this->assertSame(['turn.start', 'turn.delta', 'turn.end'], $this->eventShapeIn($frames));
    }

    #[Test]
    public function a_recorded_transcript_contains_no_undocumented_events(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        foreach ($this->eventsIn($frames) as $event) {
            $this->assertContains(
                $event['name'],
                StreamVocabulary::NAMES,
                "Event [{$event['name']}] is not in the 5.2.5 vocabulary.",
            );
        }
    }

    #[Test]
    public function turn_start_carries_the_section_the_client_has_to_draw(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $start = $this->eventsIn($frames)[0]['data'];

        $this->assertSame(TurnKind::Section->value, $start['kind']);
        $this->assertSame(1, $start['section_index']);
        $this->assertSame('What a variable is', $start['section_title']);
        $this->assertSame(2, $start['sections_total']);
        $this->assertNotEmpty($start['turn_id']);
    }

    #[Test]
    public function turn_end_names_the_row_that_already_exists(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $message = LessonMessage::query()->firstOrFail();

        $this->assertSame($message->id, $this->eventNamed($frames, 'turn.end')['message_id']);
        $this->assertSame($message->turn_id, $this->eventNamed($frames, 'turn.end')['turn_id']);
        $this->assertSame($this->eventNamed($frames, 'turn.start')['turn_id'], $message->turn_id);

        $this->assertSame(TurnKind::Section, $message->kind);
        $this->assertSame(Speaker::Tutor, $message->speaker);
        $this->assertSame(1, $message->section_index);
    }

    #[Test]
    public function teaching_a_section_puts_the_lesson_in_progress(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);

        $lesson = $this->lesson->fresh();

        $this->assertSame(LessonStatus::InProgress, $lesson->status);
        $this->assertSame(1, $lesson->section_index);
        $this->assertNotNull($lesson->started_at);
    }

    #[Test]
    public function a_stream_killed_mid_section_leaves_nothing_behind(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.']);

        $this->killAfterFrames(2);

        $this->assertSame(0, LessonMessage::query()->count(), 'A row exists only for a turn that finished.');
        $this->assertSame(0, UsageEvent::query()->where('prompt_key', 'checkpoint.generate')->count());
    }

    #[Test]
    public function reconnecting_after_a_kill_re_teaches_the_section_exactly_once(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
        ]);

        $this->killAfterFrames(2);

        $frames = [];
        $this->teach(1, $frames);

        // 5.2.6's done-condition: a complete, non-duplicated section and
        // exactly one message row.
        $this->assertSame(1, LessonMessage::query()->count());
        $this->assertSame(['turn.start', 'turn.delta', 'turn.end'], $this->eventShapeIn($frames));
    }

    #[Test]
    public function reconnecting_to_a_finished_section_replays_it_without_calling_the_model(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $first = [];
        $this->teach(1, $first);

        $callsBefore = UsageEvent::query()->count();

        $second = [];
        $this->teach(1, $second);

        $this->assertSame($callsBefore, UsageEvent::query()->count(), 'A replay costs nothing.');
        $this->assertSame(1, LessonMessage::query()->count());

        $this->assertSame(
            ['turn.start', 'turn.delta', 'turn.end', 'checkpoint.ready'],
            $this->eventShapeIn($second),
        );

        $this->assertSame(
            $this->eventNamed($first, 'turn.start')['turn_id'],
            $this->eventNamed($second, 'turn.start')['turn_id'],
        );
        $this->assertSame(
            LessonMessage::query()->value('content'),
            $this->turnTextIn($second),
            'The replay carries the whole persisted turn, in one delta.',
        );
        $this->assertSame(
            Quiz::query()->sole()->id,
            $this->eventNamed($second, 'checkpoint.ready')['checkpoint_id'],
            'The checkpoint the queue wrote, not a new one.',
        );
    }

    #[Test]
    public function an_empty_reply_writes_no_row_and_leaves_the_section_untaught(): void
    {
        $this->scriptModel(['   ']);

        $frames = [];
        $this->teach(1, $frames);

        $this->assertSame(0, LessonMessage::query()->count());
        $this->assertSame(['turn.start'], $this->eventNamesIn($frames), 'No turn.end for a turn that produced nothing.');
    }

    #[Test]
    public function a_heartbeat_goes_out_when_the_stream_has_been_quiet(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        // A clock that jumps a full heartbeat interval on every reading, so
        // every gap between frames looks overdue.
        $tick = 0.0;
        $clock = function () use (&$tick): float {
            $tick += StreamVocabulary::HEARTBEAT_SECONDS;

            return $tick;
        };

        $frames = [];

        app(LessonSessions::class)
            ->for($this->lesson)
            ->teachSection(1, $this->collectingStream($frames, $clock));

        $this->assertContains(": heartbeat\n\n", $frames);

        // A comment is not an event: parsing the recording still finds only
        // the five documented names.
        foreach ($this->eventsIn($frames) as $event) {
            $this->assertContains($event['name'], StreamVocabulary::NAMES);
        }
    }

    #[Test]
    public function an_undocumented_event_cannot_be_sent(): void
    {
        $frames = [];

        $this->expectExceptionMessage('is not in the 5.2.5 vocabulary');

        $this->collectingStream($frames)->send(new class implements StreamEvent
        {
            public function name(): string
            {
                return 'turn.thinking';
            }

            /**
             * @return array<string, mixed>
             */
            public function data(): array
            {
                return [];
            }
        });
    }

    #[Test]
    public function sections_are_taught_in_order(): void
    {
        $this->scriptModel([]);

        $frames = [];

        $this->expectExceptionMessage('is ahead of section');

        app(LessonSessions::class)
            ->for($this->lesson)
            ->teachSection(2, $this->collectingStream($frames));
    }

    /**
     * Simulate the client going away part-way through the stream.
     *
     * A sink that throws is what a dead connection looks like from inside:
     * iteration stops, so laravel/ai's `then` callbacks -- the transcript
     * append and the usage row -- never run.
     */
    private function killAfterFrames(int $frames): void
    {
        $written = 0;

        $stream = new EventStream(function () use (&$written, $frames): void {
            $written++;

            if ($written > $frames) {
                throw new RuntimeException('client gone');
            }
        });

        try {
            app(LessonSessions::class)->for($this->lesson)->teachSection(1, $stream);

            $this->fail('Expected the stream to die.');
        } catch (RuntimeException $e) {
            $this->assertSame('client gone', $e->getMessage());
        }
    }
}
