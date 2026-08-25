<?php

namespace App\Http\Controllers\Lessons;

use App\Assessment\QuizKind;
use App\Http\Controllers\Controller;
use App\Lessons\Checkpoints\CheckpointGrader;
use App\Lessons\Exceptions\UnansweredQuestion;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /lessons/{lesson}/checkpoints/{checkpoint}/answers` (spec 5.2.4).
 *
 * Grading is code and the answer key never left the server, so this endpoint
 * is the only way a student learns whether they were right -- and the only
 * moment `correct_option_id` and `explanation` are legitimately disclosed
 * (5.2.3).
 *
 * **It changes nothing about the lesson.** No status, no section index, no
 * gate. 5.2.4: checkpoints inform, never gate Continue. A student may answer
 * out of order, answer again, or skip the checkpoint entirely and press on.
 */
class CheckpointAnswerController extends Controller
{
    public function store(Request $request, Lesson $lesson, Quiz $checkpoint, CheckpointGrader $grader): JsonResponse
    {
        $this->authorize('participate', $lesson);

        // A checkpoint belonging to another lesson is not a 403 -- the student
        // may well own it -- it is a URL that does not exist.
        if ($checkpoint->kind !== QuizKind::Checkpoint
            || $checkpoint->quizzable_type !== $lesson->getMorphClass()
            || $checkpoint->quizzable_id !== $lesson->id) {
            throw new NotFoundHttpException("Lesson [{$lesson->id}] has no checkpoint [{$checkpoint->id}].");
        }

        $validated = $request->validate([
            'question_id' => ['required', 'string', 'max:64'],
            'option_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $graded = $grader->grade(
                $checkpoint,
                $request->user(),
                $validated['question_id'],
                $validated['option_id'],
            );
        } catch (UnansweredQuestion $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($graded->toArray());
    }
}
