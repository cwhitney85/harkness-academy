<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Lessons\LessonState;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reopening a lesson (spec 5.3).
 *
 * Transcript plus section index, which is what 5.3 asks to be restored, plus
 * the checkpoints already earned so a student killed mid-checkpoint does not
 * lose the questions they were looking at.
 *
 * JSON rather than an Inertia page: package 7 owns the screen, the same way it
 * owns 4.5.4's approve/revise screen. What package 5 owes is the state.
 */
class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        return response()->json(LessonState::of($lesson)->toArray());
    }
}
