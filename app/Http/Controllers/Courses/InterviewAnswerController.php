<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\InterviewSession;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Http\Controllers\Courses\Concerns\StreamsTurns;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `POST /courses/{course}/{kind}-interview/answers` (spec 8.7.2, 8.7.3).
 *
 * The student's turn goes into the transcript and the tutor's reply streams
 * back, which is the same shape as a lesson's Q&A turn and uses the same five
 * events.
 *
 * The interview may also end here rather than continue, and both ways it can
 * are invisible from this controller. The model ends it with `signal_complete`
 * mid-stream; the question cap ends it by making this turn the tutor's last,
 * asked for with a wrap-up in the brief (spec 4.3.1, 4.4.1). Either way
 * InterviewSession summarizes when the stream finishes, and what streams back
 * is an ordinary tutor turn. Nothing in the 5.2.5 vocabulary can say "and the
 * interview is over", and adding a sixth event is a spec change (5.2.5), so
 * the screen reloads when a turn ends and this controller's GET twin redirects
 * if the course has moved.
 *
 * An interview that is already over is a refusal, not a failure (spec 10.2):
 * the course moved, most often because the model ended the interview during
 * the answer before this one. So it answers the way the Done button does, with
 * wherever the course now stands, and the screen's reload follows it there.
 */
class InterviewAnswerController extends Controller
{
    use ConductsInterview, StreamsTurns;

    public function store(Request $request, Course $course, Interviews $interviews): StreamedResponse|RedirectResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:'.InterviewSession::MAX_ANSWER],
        ]);

        $kind = $this->kind($request);
        $session = $this->session($interviews, $course, $kind);

        try {
            $reply = $session->answer($validated['text']);
        } catch (InterviewNotUnderway|CourseNotOpen) {
            return redirect(NextAction::url($course->refresh()));
        }

        return $this->streamInterviewTurn($course, $kind, $reply, [
            'course_id' => $course->id,
            'kind' => $kind->value,
            'turn' => 'answer',
        ]);
    }
}
