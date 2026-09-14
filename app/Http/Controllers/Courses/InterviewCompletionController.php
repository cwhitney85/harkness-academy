<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\InterviewNotUnderway;
use App\Courses\Interviews\Interviews;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `POST /courses/{course}/{kind}-interview/complete` -- the Done button
 * (spec 2.3.4, decision R3, 8.7.2, 8.7.3).
 *
 * Always available to the student, which is why it is its own URL rather than
 * a flag on an answer: the student may stop the conversation at any point, and
 * the model's own `signal_complete` lands on the same workflow through the
 * same DoneButton class package 2 tests.
 *
 * The write-up itself is queued (spec 11.4), so this answers with the
 * interview screen in its writing-up state, and the screen waits there. It
 * fails **closed** (4.5.1) on the queue: an unusable summary leaves the
 * transcript intact, the course where it was, and the request carrying the
 * sentence that makes Done pressable again -- writing an empty document
 * instead would erase what earlier courses in this subtopic recorded (ledger
 * D7a).
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
            // Already over, or already being written up: either way the
            // answer is wherever the course now stands.
        }

        return redirect(NextAction::url($course->refresh()));
    }
}
