<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Http\Controllers\Controller;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\LessonProgress;
use App\Lessons\LessonSections;
use App\Lessons\LessonSession;
use App\Lessons\LessonSessions;
use App\Lessons\Streaming\EventStream;
use App\Lessons\Streaming\StreamError;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * `POST /lessons/{lesson}/questions` -- the free Q&A turns of ledger D5.
 *
 * A `qa` turn in 5.2.5's vocabulary, streamed exactly like a section, and it
 * joins the transcript. That last part is what makes the lesson one
 * conversation rather than N independent calls: the next section is taught by
 * a model that remembers answering the question, and the summarizer sees what
 * the student wanted to know.
 *
 * Distinct from the side-tab explainer of 5.5, which is stateless and leaves
 * the transcript byte-identical. A question asked here is part of the lesson;
 * a phrase highlighted there is not.
 */
class LessonQuestionController extends Controller
{
    public function store(
        Request $request,
        Lesson $lesson,
        LessonSessions $sessions,
        LessonProgress $progress,
    ): StreamedResponse {
        $this->authorize('participate', $lesson);

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:'.LessonSession::MAX_QUESTION],
        ]);

        try {
            $progress->assertSectionOpenable(
                $lesson,
                LessonSections::of($lesson),
                $lesson->section_index ?? 1,
            );
        } catch (LessonNotOpenable|CourseNotOpen $e) {
            abort(422, $e->getMessage());
        }

        $session = $sessions->for($lesson);

        return response()->stream(function () use ($session, $validated, $lesson): void {
            $out = new EventStream(function (string $frame): void {
                echo $frame;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            });

            try {
                $session->answerQuestion($validated['text'], $out);
            } catch (Throwable $e) {
                Log::error('Lesson question stream failed mid-flight.', [
                    'lesson_id' => $lesson->id,
                    'reason' => $e->getMessage(),
                ]);

                $out->send(StreamError::retryable('question_stream_failed'));
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
