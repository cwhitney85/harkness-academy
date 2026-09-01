<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\IllegalTransition;
use App\Courses\Exceptions\InterviewAlreadyUnderway;
use App\Courses\Interviews\Interviews;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Http\Controllers\Courses\Concerns\StreamsTurns;
use App\Models\Course;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /courses/{course}/{kind}-interview/stream` -- the tutor speaks first.
 *
 * The same split as a lesson (8.6): the opening question arrives over GET and
 * every answer after it over POST. It carries the one precondition in the
 * whole flow map, because opening *discards* the transcript.
 *
 * Everything that can be decided before a byte is sent is decided before a
 * byte is sent, exactly as in SectionStreamController: `open()` is called out
 * here, where its refusals are still HTTP status codes. Once an SSE response
 * has started the status is already 200, and the only way left to say no is a
 * `stream.error` a client has to parse to learn it should stop asking.
 */
class InterviewStreamController extends Controller
{
    use ConductsInterview, StreamsTurns;

    public function __invoke(Request $request, Course $course, Interviews $interviews): StreamedResponse
    {
        $this->authorize('participate', $course);

        $kind = $this->kind($request);
        $session = $this->session($interviews, $course, $kind);

        if (! $this->underway($course, $session)) {
            abort(422, 'This interview is not what the course is waiting on.');
        }

        try {
            $opening = $session->open();
        } catch (InterviewAlreadyUnderway|CourseNotOpen|IllegalTransition $e) {
            abort(422, $e->getMessage());
        }

        return $this->streamInterviewTurn($course, $kind, $opening, [
            'course_id' => $course->id,
            'kind' => $kind->value,
            'turn' => 'opening',
        ]);
    }
}
