<?php

namespace App\Http\Controllers\Courses;

use App\Courses\GenerationKind;
use App\Courses\Syllabus\DrawUpSyllabus;
use App\Courses\Syllabus\SyllabusGenerator;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GenerationRequest;
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
 * can say *why* approve is the only move left (8.6). Both that and the empty
 * feedback above are still decided in the request: they are knowable without
 * calling the model, and only the generation itself goes to the queue (ledger
 * D25).
 *
 * A failed regeneration leaves the previous draft exactly where it was and is
 * recoverable by asking again -- the course sits at `syllabus_revision` and
 * `revision()` accepts it back from there.
 */
class SyllabusRevisionController extends Controller
{
    public function store(Request $request, Course $course, SyllabusGenerator $generator): RedirectResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $refusal = $generator->refusal($course, $validated['feedback']);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['feedback' => $refusal]);
        }

        if (GenerationRequest::open($course, GenerationKind::Syllabus) !== null) {
            DrawUpSyllabus::dispatch($course->id, $validated['feedback']);
        }

        return redirect()->route('courses.syllabus.show', $course);
    }
}
