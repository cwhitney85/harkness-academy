<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\NextAction;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `POST /courses/{course}/syllabus/approval` -- the student says yes
 * (spec 8.7.4, 4.5.5).
 *
 * A POST to a noun, so approving and revising are two URLs rather than one
 * endpoint switching on a field (8.6).
 *
 * The syllabus approved is whichever generation the screen is showing, read
 * here rather than sent by the client: a course has exactly one current draft
 * (4.5.4.1), and letting the request name an id would let a stale tab approve
 * a generation the student has since replaced.
 *
 * **A level mismatch never blocks this** (4.5.7). The banner is a region of
 * S5 and nothing in this path reads `subtopic_mismatch`; that absence is the
 * done-condition.
 */
class SyllabusApprovalController extends Controller
{
    public function store(Request $request, Course $course, ApproveSyllabus $approve): RedirectResponse
    {
        $this->authorize('participate', $course);

        $draft = $course->latestSyllabus()->first();

        if (! $draft instanceof CourseSyllabus) {
            return back()->with('failure', 'There is no syllabus to approve yet.');
        }

        try {
            $approve($course, $draft);
        } catch (SyllabusNotDraftable|CourseNotOpen $e) {
            return back()->with('failure', $e->getMessage());
        }

        // `active`, so the resume table lands on lesson 1 -- the same rule
        // that will bring the student back here tomorrow (8.6).
        return redirect(NextAction::url($course->refresh()));
    }
}
