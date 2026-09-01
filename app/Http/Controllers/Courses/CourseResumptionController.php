<?php

namespace App\Http\Controllers\Courses;

use App\Courses\CourseTransitions;
use App\Courses\Exceptions\CourseNotResumable;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /courses/{course}/resume` -- picking a course back up (ledger D7b).
 *
 * A verb on a course the student is already looking at, not a screen: two
 * doors reach it, S2's "carry on, or start over?" (8.7.6) and the dashboard's
 * set-aside list (8.10.3), and both send the student back where they came from
 * if it is refused.
 *
 * Where they land on success is not this controller's decision. `resume`
 * clears `withdrawn_at` and leaves `status` alone, which is exactly what makes
 * NextAction -- read a second time -- put them back where they stopped.
 *
 * The refusal must say *which* of the three checks failed, because "a later
 * attempt replaced it" and "your other course is open" ask the student for
 * different things (8.6).
 */
class CourseResumptionController extends Controller
{
    public function store(Request $request, Course $course, CourseTransitions $transitions): RedirectResponse
    {
        $this->authorize('participate', $course);

        try {
            $transitions->resume($course);
        } catch (CourseNotResumable $e) {
            throw ValidationException::withMessages(['resume' => $e->refusal->explanation()]);
        }

        return redirect(NextAction::url($course));
    }
}
