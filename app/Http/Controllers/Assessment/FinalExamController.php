<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\ExamUnusable;
use App\Assessment\FinalExam;
use App\Assessment\QuizDocument;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET|POST /courses/{course}/final-exam` (spec 6.3).
 *
 * GET reports where the student stands; POST opens the exam, which generates
 * it at the `top` tier and moves the course to `final_exam`. Two verbs because
 * they cost different things: reading is free and the screen does it on every
 * visit, while opening spends the most expensive call in the system and
 * changes the course's status.
 *
 * Neither ever serves `correct_option_id` or `explanation`. An exam that hands
 * back its key stops measuring anything on the re-sit, and the re-sit is the
 * same exam (QuizKind::disclosesAnswerKey).
 */
class FinalExamController extends Controller
{
    public function show(Request $request, Course $course, FinalExam $exams): JsonResponse
    {
        $this->authorize('view', $course);

        return response()->json($this->state($course, $exams->exam($course), $exams));
    }

    public function store(Request $request, Course $course, FinalExam $exams): JsonResponse
    {
        $this->authorize('participate', $course);

        try {
            $exam = $exams->open($course);
        } catch (ExamNotAvailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ExamUnusable $e) {
            // Failed closed on purpose: the course has not moved and nothing
            // was written, so asking again is the whole recovery (6.3).
            return response()->json([
                'message' => 'The exam could not be written. Nothing has changed; try again in a moment.',
                'reason' => $e->getMessage(),
            ], 503);
        }

        return response()->json($this->state($course->refresh(), $exam, $exams));
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Course $course, ?Quiz $exam, FinalExam $exams): array
    {
        return [
            'course_id' => $course->id,
            'standing' => $course->standing()->value,
            'available' => $exams->isAvailable($course),
            'pass_mark' => $exams->passMark(),
            'exam' => $exam instanceof Quiz ? [
                'quiz_id' => $exam->id,
                'scope' => QuizDocument::of($exam)->scope(),
                'questions' => QuizDocument::of($exam)->clientPayload()['quiz']['questions'],
            ] : null,
        ];
    }
}
