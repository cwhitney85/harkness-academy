<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\Exceptions\ExamNotAvailable;
use App\Assessment\Exceptions\ExamUnusable;
use App\Assessment\FinalExam;
use App\Assessment\QuizDocument;
use App\Courses\CourseStatus;
use App\Courses\LessonRail;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screens S9 and S10 -- the final exam and its gate (spec 8.9.2, 8.9.3).
 *
 * GET renders; POST opens the exam, which generates it at the `top` tier and
 * moves the course to `final_exam`. Two verbs because they cost different
 * things: reading is free and the screen does it on every visit, while opening
 * spends the most expensive call in the system.
 *
 * **The screen has three states and the server picks which** (8.6 draws S9 and
 * S10 at one URL). A student sitting in front of an open book, a student
 * reading what a sitting did to their course, and a student who has not opened
 * it yet are three different things to draw, and deciding between them in the
 * browser would put the rule in two places -- `status` says which, and only
 * the server holds it.
 *
 * Pre:  the student may view this course (CoursePolicy).
 * Post: no response from either verb carries `correct_option_id` or
 *       `explanation`. An exam that hands back its key stops measuring
 *       anything on the re-sit, and a re-sit is the same exam
 *       (QuizKind::disclosesAnswerKey, ledger D20).
 */
class FinalExamController extends Controller
{
    public function show(Request $request, Course $course, FinalExam $exams): Response|RedirectResponse
    {
        $this->authorize('view', $course);

        $exam = $exams->exam($course);
        $sitting = $exams->lastSitting($course);

        // "Not available yet ──► S6, naming the lessons still owed" (8.6). The
        // resume table is the one thing allowed to say where that is.
        if ($sitting === null && ! $exams->isAvailable($course)) {
            return redirect(NextAction::url($course));
        }

        $seated = $course->isOpen() && $course->status === CourseStatus::FinalExam;

        return Inertia::render('courses/final-exam', [
            // `closed` is the book as it is handed over, `open` is the student
            // sitting in front of it, `result` is what a sitting did.
            'state' => match (true) {
                $seated => 'open',
                $sitting !== null => 'result',
                default => 'closed',
            },
            'course' => [
                'title' => $course->subtopic_title,
                'lessons' => LessonRail::of($course),
            ],
            'student' => $course->student->name,
            'date' => ($exam instanceof Quiz ? $exam->created_at : now())->format('j M Y'),
            'pass_mark' => $exams->passMark(),
            // Only once the book is open. A generated exam is a row that
            // exists before the student has asked for it -- the seeded fixture
            // is exactly that -- and a shut book that shipped its questions
            // would be handing over the paper while claiming not to.
            'questions' => $seated && $exam instanceof Quiz
                ? QuizDocument::of($exam)->clientPayload()['quiz']['questions']
                : [],
            // A book already sat is opened straight to the leaf, with no
            // ceremony (8.4.4).
            'resit' => $sitting !== null,
            'sitting' => $sitting === null ? null : [
                ...$sitting->toArray(),
                'passed' => $sitting->score() >= $exams->passMark(),
            ],
            'urls' => [
                'open' => route('courses.final-exam.store', $course),
                'sittings' => route('courses.final-exam.sittings.store', $course),
                'refresher' => route('courses.refreshers.store', $course),
                'dashboard' => route('dashboard'),
            ],
        ]);
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

        $course->refresh();

        return response()->json([
            'course_id' => $course->id,
            'standing' => $course->standing()->value,
            'available' => $exams->isAvailable($course),
            'pass_mark' => $exams->passMark(),
            'exam' => [
                'quiz_id' => $exam->id,
                'scope' => QuizDocument::of($exam)->scope(),
                'questions' => QuizDocument::of($exam)->clientPayload()['quiz']['questions'],
            ],
        ]);
    }
}
