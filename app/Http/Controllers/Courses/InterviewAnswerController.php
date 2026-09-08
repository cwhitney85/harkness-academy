<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Exceptions\InterviewSummaryUnusable;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\InterviewSession;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Http\Controllers\Courses\Concerns\StreamsTurns;
use App\Models\Course;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `POST /courses/{course}/{kind}-interview/answers` (spec 8.7.2, 8.7.3).
 *
 * The student's turn goes into the transcript and the tutor's reply streams
 * back, which is the same shape as a lesson's Q&A turn and uses the same five
 * events.
 *
 * The interview may also end here rather than continue. The model ends it with
 * `signal_complete`, which arrives mid-stream and which InterviewSession acts
 * on once the stream finishes; the question cap ends it before a model call is
 * made at all, and the tutor's closing line is streamed in place of a question
 * it will not ask (spec 4.3.1, 4.4.1). Nothing in the 5.2.5 vocabulary can say
 * "and the interview is over", and adding a sixth event is a spec change
 * (5.2.5), so the screen reloads when a turn ends and this controller's GET
 * twin redirects if the course has moved.
 */
class InterviewAnswerController extends Controller
{
    use ConductsInterview, StreamsTurns;

    public function store(Request $request, Course $course, Interviews $interviews): StreamedResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:'.InterviewSession::MAX_ANSWER],
        ]);

        $kind = $this->kind($request);
        $session = $this->session($interviews, $course, $kind);

        try {
            $reply = $session->answer($validated['text']);
        } catch (InterviewNotUnderway|CourseNotOpen $e) {
            abort(422, $e->getMessage());
        } catch (InterviewSummaryUnusable $e) {
            // Only the cap path can raise this, and it fails closed (4.5.1):
            // the answer is recorded, the transcript is intact and the course
            // has not moved, so Done is still there to summarize it again.
            abort(422, $e->getMessage());
        }

        // The question cap: the answer was the last one this interview asked
        // for, so there is no next question -- only the line it closes on
        // (spec 4.3.1, 4.4.1).
        if ($reply === null) {
            return $this->streamClosingLine(InterviewSession::CLOSING_LINE);
        }

        return $this->streamInterviewTurn($course, $kind, $reply, [
            'course_id' => $course->id,
            'kind' => $kind->value,
            'turn' => 'answer',
        ]);
    }
}
