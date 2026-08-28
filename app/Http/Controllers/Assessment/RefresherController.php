<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\RefresherNotAvailable;
use App\Assessment\Exceptions\RefresherUnusable;
use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Assessment\Refreshers;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Asking to be reminded (spec 6.4, 6.5).
 *
 * A POST per grain, because what a refresher is about is decided by which
 * thing you asked from, and a `?grain=` parameter would put that decision in a
 * string the client picks. A GET on the quiz itself, because a refresher is
 * the one kind a student comes back to by its own id.
 *
 * Every POST generates. That is the point -- `quizzes_one_per_scope` excludes
 * this kind so that coming back next month asks new questions -- and it is
 * also why these are the throttled routes: each one spends tokens.
 */
class RefresherController extends Controller
{
    public function forLesson(Request $request, Lesson $lesson, Refreshers $refreshers): JsonResponse
    {
        $this->authorize('participate', $lesson);

        return $this->generated(fn (): Quiz => $refreshers->forLesson($lesson));
    }

    public function forSubtopic(Request $request, Course $course, Refreshers $refreshers): JsonResponse
    {
        $this->authorize('participate', $course);

        return $this->generated(fn (): Quiz => $refreshers->forSubtopic($course));
    }

    public function show(Request $request, Quiz $refresher): JsonResponse
    {
        $this->authorize('view', $refresher);

        if ($refresher->kind !== QuizKind::Refresher) {
            throw new NotFoundHttpException("Quiz [{$refresher->id}] is not a refresher.");
        }

        return response()->json($this->payload($refresher));
    }

    /**
     * @param  callable(): Quiz  $generate
     */
    private function generated(callable $generate): JsonResponse
    {
        try {
            $quiz = $generate();
        } catch (RefresherNotAvailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RefresherUnusable $e) {
            // Nothing was written and nothing gates on this, so asking again
            // is the whole recovery (6.4).
            return response()->json([
                'message' => 'The refresher could not be written. Try again in a moment.',
                'reason' => $e->getMessage(),
            ], 503);
        }

        return response()->json($this->payload($quiz), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Quiz $refresher): array
    {
        $document = QuizDocument::of($refresher);

        return [
            'quiz_id' => $refresher->id,
            'scope' => $document->scope(),
            'questions' => $document->clientPayload()['quiz']['questions'],
            'suggested_reading' => $document->suggestedReading(),
        ];
    }
}
