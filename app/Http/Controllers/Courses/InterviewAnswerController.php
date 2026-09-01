<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\InterviewNotUnderway;
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
 * The model may end the interview here rather than the student: `signal_complete`
 * arrives mid-stream, and InterviewSession acts on it once the stream finishes
 * -- summarizing, discarding the transcript and moving the course on. Nothing
 * in the 5.2.5 vocabulary can say "and the interview is over", and adding a
 * sixth event is a spec change (5.2.5), so the screen reloads when a turn ends
 * and this controller's GET twin redirects if the course has moved.
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
        }

        return $this->streamInterviewTurn($course, $kind, $reply, [
            'course_id' => $course->id,
            'kind' => $kind->value,
            'turn' => 'answer',
        ]);
    }
}
