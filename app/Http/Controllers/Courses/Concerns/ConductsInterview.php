<?php

namespace App\Http\Controllers\Courses\Concerns;

use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\InterviewSession;
use App\Courses\Interviews\Transcript;
use App\Models\Course;
use Illuminate\Http\Request;

/**
 * The half of an interview screen that does not depend on which interview it
 * is (spec 8.7.2, 8.7.3).
 *
 * 8.7.3 is stated as "same shape" and the engine already made that literally
 * true -- one InterviewSession, two Interview implementations (ledger D9). So
 * the HTTP layer has four controllers, not eight: the knowledge and goals
 * routes are the same four actions with a different `kind` default, and this
 * is where that default is read back.
 *
 * A route default rather than a URL segment, because the flow map names the
 * two URLs literally (`…/knowledge-interview`, `…/goals-interview`) and a
 * single pattern-matched segment would let `/courses/1/anything-interview`
 * reach a controller before it reached a 404.
 */
trait ConductsInterview
{
    /**
     * Pre: the route declares a `kind` default holding an InterviewKind value.
     *      A route that forgets it is a wiring bug and should fail loudly.
     */
    protected function kind(Request $request): InterviewKind
    {
        return InterviewKind::from((string) $request->route('kind'));
    }

    protected function session(Interviews $interviews, Course $course, InterviewKind $kind): InterviewSession
    {
        return match ($kind) {
            InterviewKind::Knowledge => $interviews->knowledge($course),
            InterviewKind::Goals => $interviews->goals($course),
        };
    }

    /**
     * What the screen paints from, and what the re-open precondition is read
     * off (spec 8.6).
     */
    protected function transcript(Course $course, InterviewKind $kind): Transcript
    {
        return new Transcript($course, $kind);
    }

    /**
     * Whether this interview is still the course's business.
     *
     * True at its own status and at the one before it -- opening is what moves
     * a course from `created` into the knowledge interview, so arriving early
     * is the normal case rather than an error. Anywhere else and the student
     * has moved on; NextAction says where to.
     */
    protected function underway(Course $course, InterviewSession $session): bool
    {
        return $course->isOpen()
            && ($course->status === $session->status() || $course->status->canPrecede($session->status()));
    }
}
