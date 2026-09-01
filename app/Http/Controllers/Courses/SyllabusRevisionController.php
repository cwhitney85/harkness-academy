<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\Exceptions\SyllabusUnusable;
use App\Courses\Syllabus\SyllabusGenerator;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /courses/{course}/syllabus/revisions` -- send it back with notes
 * (spec 8.7.4, 4.5.4).
 *
 * The typed request is the whole point: `SyllabusGenerator::revision` refuses
 * empty feedback, because "make it different" is not something a model can act
 * on and a revision that changed nothing would still burn one of three.
 *
 * Running out of revisions is a 422 rather than a hidden button, so the screen
 * can say *why* approve is the only move left (8.6). A failed regeneration
 * leaves the previous draft exactly where it was and is recoverable by asking
 * again -- the course sits at `syllabus_revision` and `revision()` accepts it
 * back from there.
 */
class SyllabusRevisionController extends Controller
{
    public function store(Request $request, Course $course, SyllabusGenerator $generator): RedirectResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        try {
            $generator->revision($course, $validated['feedback']);
        } catch (SyllabusNotDraftable $e) {
            throw ValidationException::withMessages(['feedback' => $e->getMessage()]);
        } catch (SyllabusUnusable) {
            return back()->with('failure', 'The revision could not be drawn up just now. Your previous syllabus is untouched — ask again, or approve what you have.');
        } catch (MissingCourseDocument $e) {
            return back()->with('failure', $e->getMessage());
        }

        return redirect()->route('courses.syllabus.show', $course);
    }
}
