<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Http\Controllers\Controller;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\Exceptions\NoSuchSection;
use App\Lessons\LessonProgress;
use App\Lessons\LessonSections;
use App\Lessons\LessonSessions;
use App\Models\Lesson;
use App\Models\LessonMessage;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /lessons/{lesson}/sections/{index}/checkpoint` -- Try again, for a
 * section whose checkpoint did not arrive (spec 11.3).
 *
 * The end of a section's stream asks for its checkpoint on its own; this is
 * the student asking again once the screen has stopped waiting. It answers
 * with nothing, and the screen reloads onto the wait `LessonState` reports.
 *
 * Pre:  the student may be in this section, and it has been taught.
 * Post: the section's checkpoint exists, or a request for it is pending.
 */
class SectionCheckpointController extends Controller
{
    public function store(Lesson $lesson, int $index, LessonSessions $sessions, LessonProgress $progress): Response
    {
        $this->authorize('participate', $lesson);

        try {
            $progress->assertSectionOpenable($lesson, LessonSections::of($lesson), $index);
        } catch (NoSuchSection $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (LessonNotOpenable|CourseNotOpen $e) {
            abort(422, $e->getMessage());
        }

        $session = $sessions->for($lesson);

        if (! $session->transcript()->section($index) instanceof LessonMessage) {
            abort(422, 'This section has not been taught yet.');
        }

        $session->askForCheckpoint($index);

        return response()->noContent();
    }
}
