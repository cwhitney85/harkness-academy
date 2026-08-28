<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Homework;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /lessons/{lesson}/homework` (spec 6.2).
 *
 * The homework is written by step three of the completion chain (5.4.1), which
 * runs behind the completion the student has already been shown. So "not there
 * yet" is an ordinary state rather than a missing resource, and this answers
 * with `state: pending` instead of a 404 -- a 404 would tell the screen the
 * homework does not exist, when what is true is that it has not arrived. The
 * chain retries, parks and can be finished by `lessons:retry-pipeline` (5.4.5),
 * so pending is recoverable in every case and the screen's job is to wait.
 */
class HomeworkController extends Controller
{
    public function show(Request $request, Lesson $lesson, Quizzes $quizzes): JsonResponse
    {
        $this->authorize('view', $lesson);

        $quiz = $quizzes->existing(QuizKind::Homework, $lesson);

        if (! $quiz instanceof Quiz) {
            return response()->json([
                'lesson_id' => $lesson->id,
                'state' => 'pending',
                'homework' => null,
            ]);
        }

        return response()->json([
            'lesson_id' => $lesson->id,
            'state' => 'ready',
            'homework' => Homework::of($quiz, $lesson->course->student)->toArray(),
        ]);
    }
}
