<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\UnansweredQuestion;
use App\Assessment\FinalExam;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /courses/{course}/final-exam/sittings` (spec 6.3).
 *
 * The whole exam in one request, because passing is a property of the set and
 * a per-question endpoint would let a student learn the answers mid-exam.
 *
 * This is the one endpoint in the system that can end a course. Pass stamps
 * `completed_at`; fail returns the course to `active` with the concepts missed
 * named, which is the signal ledger D7b chose over a `failed` end state.
 */
class FinalExamSittingController extends Controller
{
    public function store(Request $request, Course $course, FinalExam $exams): JsonResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:64'],
        ]);

        /** @var array<string, string> $answers */
        $answers = $validated['answers'];

        try {
            $result = $exams->sit($course, $answers);
        } catch (ExamNotAvailable|UnansweredQuestion $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }
}
