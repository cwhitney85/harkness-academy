<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Http\Controllers\Controller;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Exceptions\NoSuchSection;
use App\Lessons\LessonProgress;
use App\Lessons\LessonSections;
use App\Lessons\LessonSessions;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\StreamError;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * `GET /lessons/{lesson}/sections/{index}/stream` (spec 5.2.6).
 *
 * Everything that can be decided before a byte is sent is decided before a
 * byte is sent: authorization, whether the section exists, whether the student
 * may be there. Once an SSE response has started the status code is already
 * 200 and the only way left to say "no" is a `stream.error`, which a client
 * has to parse to learn it should stop asking. So the closed doors are HTTP
 * codes and only genuine mid-flight failures are events.
 *
 * Reconnect is not a special case here. The session replays a completed turn
 * and re-teaches an unfinished one, so this controller does the same thing on
 * the first request and the fifth.
 *
 * Buffering is the one deployment hazard (decision R4: the host must not
 * buffer SSE). `X-Accel-Buffering: no` handles nginx-family proxies; the
 * `ob_flush`/`flush` pair handles PHP's own output buffering.
 */
class SectionStreamController extends Controller
{
    public function __invoke(
        Request $request,
        Lesson $lesson,
        int $index,
        LessonSessions $sessions,
        LessonProgress $progress,
    ): StreamedResponse {
        $this->authorize('participate', $lesson);

        // Pre-flight. A 404 for a section that does not exist, a 422 for one
        // the student may not open yet -- both far more useful to a client
        // than a 200 that immediately says it went wrong.
        try {
            $progress->assertSectionOpenable($lesson, LessonSections::of($lesson), $index);
        } catch (NoSuchSection $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (LessonNotOpenable|CourseNotOpen $e) {
            abort(422, $e->getMessage());
        }

        $session = $sessions->for($lesson);

        return response()->stream(function () use ($session, $index, $lesson): void {
            $out = new EventStream(function (string $frame): void {
                echo $frame;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            });

            try {
                $session->teachSection($index, $out);
            } catch (Throwable $e) {
                // The pre-flight already ruled out the predictable refusals,
                // so anything reaching here is the provider or the database
                // having a bad moment -- worth another try from the client.
                Log::error('Lesson section stream failed mid-flight.', [
                    'lesson_id' => $lesson->id,
                    'section_index' => $index,
                    'reason' => $e->getMessage(),
                ]);

                $out->send(StreamError::retryable('section_stream_failed'));
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
