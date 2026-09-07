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
 * `POST /courses/{course}/final-exam/answers` (spec 6.3.4, ledger D26).
 *
 * The answer sheet: what the student has ticked, saved as they tick it. The
 * whole book each time, because that is what the screen holds and because a
 * change of mind is then an ordinary save rather than a second endpoint.
 *
 * **This is not the sitting.** Nothing here is graded, nothing is recorded in
 * `quiz_attempts`, and the reply carries no verdict, no score and no answer
 * key -- it says how many selections are on the sheet and nothing else. What
 * D20 forbids is disclosure, and saving discloses nothing; hand-in is still
 * `courses.final-exam.sittings.store`, still whole, still marked once.
 */
class FinalExamAnswerSheetController extends Controller
{
    public function store(Request $request, Course $course, FinalExam $exams): JsonResponse
    {
        $this->authorize('participate', $course);

        $validated = $request->validate([
            // Present but empty is a legitimate save: it is the student
            // clearing the last answer they had ticked.
            'answers' => ['present', 'array'],
            'answers.*' => ['required', 'string', 'max:64'],
        ]);

        /** @var array<string, string> $answers */
        $answers = $validated['answers'];

        try {
            $exams->save($course, $answers);
        } catch (ExamNotAvailable|UnansweredQuestion $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['saved' => count($answers)]);
    }
}
