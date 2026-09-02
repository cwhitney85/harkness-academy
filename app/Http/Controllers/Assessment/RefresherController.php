<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\AnsweredQuestions;
use App\Assessment\Exceptions\RefresherNotAvailable;
use App\Assessment\Exceptions\RefresherUnusable;
use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Assessment\Refreshers;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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

    public function forModule(Request $request, Module $module, Refreshers $refreshers): JsonResponse
    {
        // No policy: a module is catalog, and the refresher is built from this
        // student's own records under it. There is nothing here belonging to
        // anyone else to authorize against.
        $student = $request->user();

        return $this->generated(fn (): Quiz => $refreshers->forModule($student, $module));
    }

    /**
     * Screen S11 -- the refresher, on the photocopy sheet (spec 8.9.4).
     *
     * One screen for all three grains, because one material serves all three
     * (8.4.2): what a lesson refresher and a module refresher differ in is the
     * scope printed at the head of the sheet, and that is a string the quiz
     * already carries.
     */
    public function show(Request $request, Quiz $refresher): Response
    {
        $this->authorize('view', $refresher);

        if ($refresher->kind !== QuizKind::Refresher) {
            throw new NotFoundHttpException("Quiz [{$refresher->id}] is not a refresher.");
        }

        $document = QuizDocument::of($refresher);

        return Inertia::render('refreshers/show', [
            'refresher' => [
                'quiz_id' => $refresher->id,
                'scope' => $document->scope(),
                'questions' => $document->clientPayload()['quiz']['questions'],
                'suggested_reading' => $document->suggestedReading(),
                'progress' => array_map(
                    static fn (array $row): array => [
                        'question_id' => $row['question_id'],
                        'answered' => $row['answered'],
                        'correct' => $row['correct'],
                        'attempts' => $row['attempts'],
                    ],
                    AnsweredQuestions::on($refresher, $request->user()),
                ),
            ],
            // A module-grain refresher hangs off no course (6.5), so the head
            // of the sheet says what it covers and nothing else.
            'course' => $refresher->course?->subtopic_title,
            'student' => $request->user()->name,
            'date' => $refresher->created_at->format('j M Y'),
            'urls' => [
                'answers' => route('refreshers.answers.store', $refresher),
                'dashboard' => route('dashboard'),
            ],
        ]);
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
            // Where the refresher just generated is read (8.6's three doors
            // into S11). The screen that asked for it follows this rather
            // than assembling a URL from the id.
            'url' => route('refreshers.show', $refresher),
            'scope' => $document->scope(),
            'questions' => $document->clientPayload()['quiz']['questions'],
            'suggested_reading' => $document->suggestedReading(),
        ];
    }
}
