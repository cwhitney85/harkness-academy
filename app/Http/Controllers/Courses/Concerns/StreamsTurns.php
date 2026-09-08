<?php

namespace App\Http\Controllers\Courses\Concerns;

use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Speaker;
use App\Courses\Interviews\Transcript;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\StreamError;
use App\Lessons\Streaming\TurnDelta;
use App\Lessons\Streaming\TurnEnd;
use App\Lessons\Streaming\TurnKind;
use App\Lessons\Streaming\TurnStart;
use App\Models\Course;
use App\Models\InterviewMessage;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * An interview turn on the wire (spec 5.2.5, 8.7.2).
 *
 * The five-event vocabulary, unchanged: `TurnKind::Interview` was put in it by
 * package 5 precisely so a client that can render a lesson section can render
 * an interview question without learning a second event language. Nothing here
 * adds a name -- `EventStream::send` would refuse one.
 *
 * Pre:  `$response` has not been iterated, and every refusal the request could
 *       have earned has already been answered with an HTTP status. Once this
 *       is called the status is 200 and only `stream.error` is left.
 * Post: `turn.start` precedes every delta; `turn.end` goes out only after the
 *       tutor's row exists, and does not go out at all when there is no row --
 *       an empty reply, or an interview the model ended mid-stream, whose
 *       transcript was summarized and discarded before this could read it. In
 *       both cases the screen's reload is what tells the student where they
 *       now are.
 *
 * Buffering is the deployment hazard SectionStreamController names (decision
 * R4) and the headers here are the same three answers to it.
 */
trait StreamsTurns
{
    /**
     * @param  array<string, mixed>  $logContext  what to say if it fails mid-flight
     */
    protected function streamInterviewTurn(
        Course $course,
        InterviewKind $kind,
        StreamableAgentResponse $response,
        array $logContext,
    ): StreamedResponse {
        return $this->interviewTurn(function (EventStream $out, string $turnId) use ($course, $kind, $response, $logContext): void {
            try {
                $response->each(function (mixed $event) use ($out, $turnId): void {
                    if ($event instanceof TextDelta && $event->delta !== '') {
                        $out->send(new TurnDelta($turnId, $event->delta));
                    }

                    $out->heartbeatIfDue();
                });
            } catch (Throwable $e) {
                // The pre-flight ruled out the predictable refusals, so what
                // reaches here is the provider having a bad moment or a
                // summary the model could not write -- both worth another try
                // from the client, and neither having moved the course.
                Log::error('Interview stream failed mid-flight.', [
                    ...$logContext,
                    'reason' => $e->getMessage(),
                ]);

                $out->send(StreamError::retryable('interview_stream_failed'));

                return;
            }

            $written = (new Transcript($course, $kind))->latestTurn();

            if ($written instanceof InterviewMessage && $written->speaker === Speaker::Tutor) {
                $out->send(new TurnEnd($turnId, $written->id));
            }
        });
    }

    /**
     * The tutor's last word, when the question cap ended the interview
     * (spec 4.3.1, 4.4.1).
     *
     * Pre:  the interview is already summarized -- the cap is enforced in
     *       InterviewSession::answer(), which writes the document and moves
     *       the course before this is reached, so a failure there is still an
     *       HTTP status rather than a `stream.error`.
     * Post: the line has gone out as one turn. No `turn.end`: it names a row,
     *       and this line has none to name -- the transcript it would have
     *       joined was discarded by the summarization it follows. The client's
     *       reload is what carries the student on, exactly as it does when the
     *       model ends an interview mid-stream.
     */
    protected function streamClosingLine(string $line): StreamedResponse
    {
        return $this->interviewTurn(function (EventStream $out, string $turnId) use ($line): void {
            $out->send(new TurnDelta($turnId, $line));
        });
    }

    /**
     * One interview turn on the wire, whoever wrote it.
     *
     * @param  Closure(EventStream, string): void  $body  everything after `turn.start`
     */
    private function interviewTurn(Closure $body): StreamedResponse
    {
        return response()->stream(function () use ($body): void {
            $out = new EventStream(function (string $frame): void {
                echo $frame;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            });

            $turnId = (string) Str::uuid();

            // Sent before the first token so a client can paint the tutor's
            // empty turn immediately, which is 8.8.2's rule for a lesson and
            // costs nothing to keep true here.
            $out->send(new TurnStart(turnId: $turnId, kind: TurnKind::Interview));

            $body($out, $turnId);
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
