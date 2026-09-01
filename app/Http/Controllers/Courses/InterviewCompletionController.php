<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Exceptions\InterviewSummaryUnusable;
use App\Courses\Interviews\Interviews;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /courses/{course}/{kind}-interview/complete` -- the Done button
 * (spec 2.3.4, decision R3, 8.7.2, 8.7.3).
 *
 * Always available to the student, which is why it is its own URL rather than
 * a flag on an answer: the student may stop the conversation at any point, and
 * the model's own `signal_complete` lands on the same workflow through the
 * same DoneButton class package 2 tests.
 *
 * It fails **closed** (4.5.1). An unusable summary leaves the transcript
 * intact and the course where it was, so Done is pressable again -- writing an
 * empty document instead would erase what earlier courses in this subtopic
 * recorded (ledger D7a). The flow map draws that as "422 summary unusable
 * ──► still underway, Done pressable again".
 *
 * An interview that is already over is not an error. The model may have ended
 * it during the last answer, and a double tap is the ordinary way to arrive
 * here twice; both land on wherever the course now stands.
 */
class InterviewCompletionController extends Controller
{
    use ConductsInterview;

    public function store(Request $request, Course $course, Interviews $interviews): RedirectResponse
    {
        $this->authorize('participate', $course);

        $session = $this->session($interviews, $course, $this->kind($request));

        try {
            $session->doneButton()->press();
        } catch (InterviewNotUnderway|CourseNotOpen) {
            return redirect(NextAction::url($course->refresh()));
        } catch (InterviewSummaryUnusable) {
            throw ValidationException::withMessages([
                'complete' => 'That could not be summarized just now. Nothing was lost — press Done again.',
            ]);
        }

        return redirect(NextAction::url($course->refresh()));
    }
}
