<?php

namespace App\Http\Controllers\Lessons;

use App\Courses\Exceptions\CourseNotOpen;
use App\Http\Controllers\Controller;
use App\Lessons\CompleteLesson;
use App\Lessons\Exceptions\LessonNotOpenable;
use App\Lessons\LessonState;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /lessons/{lesson}/complete` (spec 5.4.1).
 *
 * Returns as soon as the row is stamped. Everything the completion sets in
 * motion -- summary, working knowledge, homework, unlock -- is queued behind
 * this response, because 5.4.5 forbids the student's progress from waiting on
 * any of it.
 */
class LessonCompletionController extends Controller
{
    public function store(Request $request, Lesson $lesson, CompleteLesson $complete): JsonResponse
    {
        $this->authorize('participate', $lesson);

        try {
            $completed = $complete($lesson);
        } catch (LessonNotOpenable|CourseNotOpen $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(LessonState::of($completed)->toArray());
    }
}
