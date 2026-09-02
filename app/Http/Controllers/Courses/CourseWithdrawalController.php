<?php

namespace App\Http\Controllers\Courses;

use App\Courses\CourseTransitions;
use App\Courses\Exceptions\CourseNotOpen;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /courses/{course}/withdraw` -- setting a course aside (ledger D7b).
 *
 * The twin of `CourseResumptionController`, and a verb on a card the student
 * is already looking at rather than a screen of its own: the flow map keeps
 * the twelve at twelve on purpose (8.6).
 *
 * Nothing is lost. `withdraw` stamps a date and leaves `status` untouched, and
 * that untouched value is what puts the student back where they were when they
 * pick the course up again -- so this controller has no landing to decide.
 * They stay on the dashboard, where the card has moved lists.
 *
 * Pre:  the course belongs to this student and is open.
 * Post: `withdrawn_at` is stamped, the subtopic's one open slot is free, and
 *       the student is back on the dashboard.
 */
class CourseWithdrawalController extends Controller
{
    public function store(Request $request, Course $course, CourseTransitions $transitions): RedirectResponse
    {
        $this->authorize('participate', $course);

        try {
            $transitions->withdraw($course);
        } catch (CourseNotOpen) {
            // The double tap and the second tab, as everywhere else: the
            // course has already ended, by this hand or the exam's.
            throw ValidationException::withMessages([
                'withdraw' => 'That course has already ended, so there is nothing to set aside.',
            ]);
        }

        return back();
    }
}
