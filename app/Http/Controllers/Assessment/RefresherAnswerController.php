<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\UnansweredQuestion;
use App\Assessment\QuizGrader;
use App\Assessment\QuizKind;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /refreshers/{refresher}/answers` (spec 6.4).
 *
 * The same shape as a checkpoint answer, and for the same reason: a refresher
 * teaches. Answering discloses the key and the explanation, answering again is
 * ordinary, and nothing is gated either way -- a refresher is a warm-up, so
 * getting one wrong costs the student nothing but a reminder.
 */
class RefresherAnswerController extends Controller
{
    public function store(Request $request, Quiz $refresher, QuizGrader $grader): JsonResponse
    {
        $this->authorize('participate', $refresher);

        if ($refresher->kind !== QuizKind::Refresher) {
            throw new NotFoundHttpException("Quiz [{$refresher->id}] is not a refresher.");
        }

        $validated = $request->validate([
            'question_id' => ['required', 'string', 'max:64'],
            'option_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $graded = $grader->grade($refresher, $request->user(), $validated['question_id'], $validated['option_id']);
        } catch (UnansweredQuestion $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($graded->toArray());
    }
}
