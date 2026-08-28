<?php

namespace Tests\Feature\Lessons;

use App\Lessons\LessonSessions;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.6 -- the server's half of the streaming floor.
 *
 * 5.6's done-condition is a manual phone check over LTE and no test can stand
 * in for it. What a test *can* do is pin the two server-side properties the
 * check depends on, so a refactor cannot quietly spend the budget before
 * anyone has a phone in hand:
 *
 *   1. **Ordering.** The first frame leaves before any work whose latency the
 *      server does not control. The client has a section title and an "N of M"
 *      to draw the instant the connection opens, and everything after that is
 *      the provider's time, not ours.
 *   2. **No buffering.** R4's constraint on the host, restated as headers, on
 *      every route that streams. A proxy holding 8KB before releasing it turns
 *      a 300ms first token into a first token that never perceptibly arrives.
 *
 * Everything else 5.6 asks for -- how fast the first token *feels*, whether
 * deltas paint smoothly, whether the thing is usable one-handed -- is rendering
 * and belongs to package 7. This file is the line between the two halves.
 */
class StreamingFloorTest extends SectionStreamTestCase
{
    #[Test]
    public function a_section_gives_the_client_something_to_draw_before_the_model_is_called(): void
    {
        $drawn = $this->eventsBeforeTheFirstModelCall(
            ['A variable is a letter standing in for a number.', $this->checkpointResponse()],
            function (array &$frames): void {
                app(LessonSessions::class)
                    ->for($this->lesson->fresh())
                    ->teachSection(1, $this->collectingStream($frames));
            },
        );

        $this->assertSame(['turn.start'], array_column($drawn, 'name'));

        // The three fields exist so a client can draw "Section 1 of 4 --
        // Letters as numbers" without a second round trip. Sending them late
        // would be a blank panel for the whole of the provider's first token.
        $this->assertSame(1, $drawn[0]['data']['section_index']);
        $this->assertNotSame('', $drawn[0]['data']['section_title']);
        $this->assertGreaterThan(0, $drawn[0]['data']['sections_total']);
    }

    #[Test]
    public function a_question_gives_the_client_something_to_draw_before_the_model_is_called(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $taught = [];
        $this->teach(1, $taught);

        $drawn = $this->eventsBeforeTheFirstModelCall(
            ['Yes -- a different number in a different problem.'],
            function (array &$frames): void {
                app(LessonSessions::class)
                    ->for($this->lesson->fresh())
                    ->answerQuestion('Can a letter stand for more than one number?', $this->collectingStream($frames));
            },
        );

        // The question row is written first -- it has to be, or the answer
        // would land at a lower position than the question (5.2.5, D14) -- but
        // that is one insert, and the frame still precedes the call.
        $this->assertSame(['turn.start'], array_column($drawn, 'name'));
    }

    #[Test]
    public function every_streaming_route_tells_intermediaries_not_to_buffer(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- a different number in a different problem.',
        ]);

        $student = $this->lesson->course->student;

        $streamed = [
            $this->actingAs($student)->get("/lessons/{$this->lesson->id}/sections/1/stream"),
            $this->actingAs($student)->post("/lessons/{$this->lesson->id}/questions", ['text' => 'Why?']),
        ];

        foreach ($streamed as $response) {
            $response->streamedContent();

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')
                // nginx and its relatives buffer proxied responses by default,
                // and Laravel Cloud is one of its relatives (decision R4).
                ->assertHeader('X-Accel-Buffering', 'no')
                ->assertHeader('Connection', 'keep-alive');

            // `no-transform` is the directive that matters here: a caching
            // layer permitted to gzip the body is a caching layer that has to
            // hold the body first. Asserted by directive rather than by the
            // whole header, because the framework appends `private` of its own.
            $cacheControl = $response->headers->get('Cache-Control') ?? '';

            $this->assertStringContainsString('no-cache', $cacheControl);
            $this->assertStringContainsString('no-transform', $cacheControl);
        }
    }

    /**
     * Run a streamed turn and report what had already reached the client at the
     * moment the provider was first asked for anything.
     *
     * @param  list<array<string, mixed>|string>  $responses
     * @param  \Closure(list<string>): void  $turn
     * @return list<array{name: string, data: array<string, mixed>}>
     */
    private function eventsBeforeTheFirstModelCall(array $responses, \Closure $turn): array
    {
        $frames = [];
        $drawn = null;

        $this->fakeModel(function () use (&$responses, &$frames, &$drawn): array|string {
            $drawn ??= $this->eventsIn($frames);

            if ($responses === []) {
                $this->fail('The model was called more times than this test scripted.');
            }

            return array_shift($responses);
        });

        $turn($frames);

        $this->assertNotNull($drawn, 'The model was never called, so there is no ordering to check.');

        return $drawn;
    }
}
