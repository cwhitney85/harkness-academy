<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\UnansweredQuestion;
use App\Assessment\QuizGrader;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /lessons/{lesson}/homework/answers` (spec 6.2).
 *
 * The same shape as 5.2.4's checkpoint answer, and deliberately so: homework
 * teaches, so answering discloses the key and the explanation, and answering
 * again is ordinary rather than an error.
 *
 * **It gates nothing.** Homework is not a second lock on the next lesson --
 * that lesson was unlocked by the chain that wrote this quiz. What the answers
 * do is reach the next working-knowledge update as evidence (HomeworkResults),
 * which is the whole of 6.2's "result feeds next WK update".
 */
class HomeworkAnswerController extends Controller
{
    public function store(Request $request, Lesson $lesson, Quizzes $quizzes, QuizGrader $grader): JsonResponse
    {
        $this->authorize('participate', $lesson);

        $quiz = $quizzes->existing(QuizKind::Homework, $lesson);

        if (! $quiz instanceof Quiz) {
            throw new NotFoundHttpException("Lesson [{$lesson->id}] has no homework yet.");
        }

        $validated = $request->validate([
            'question_id' => ['required', 'string', 'max:64'],
            'option_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $graded = $grader->grade(
                $quiz,
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
