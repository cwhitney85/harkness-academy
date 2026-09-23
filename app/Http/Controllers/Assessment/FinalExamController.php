<?php

namespace App\Http\Controllers\Assessment;

use App\Assessment\FinalExam;
use App\Assessment\QuizDocument;
use App\Assessment\WriteFinalExam;
use App\Courses\CourseStatus;
use App\Courses\GenerationKind;
use App\Courses\LessonRail;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GenerationRequest;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screens S9 and S10 -- the final exam and its gate (spec 8.9.2, 8.9.3).
 *
 * GET renders; POST asks for the exam, which is written at the `top` tier and
 * moves the course to `final_exam`. Two verbs because they cost different
 * things: reading is free and the screen does it on every visit, while opening
 * spends the most expensive call in the system.
 *
 * **The writing itself runs on the queue** (ledger D25). It takes longer than
 * any edge will hold a request open, so the POST asks and the screen waits on
 * the `opening` state until the book is there.
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

        // A demo has no exam (spec 12.6.2); its course ends at sign-up.
        if ($course->student->isDemo()) {
            return redirect()->route('demo.account.create');
        }

        $exam = $exams->exam($course);
        $sitting = $exams->lastSitting($course);

        // "Not available yet ──► S6, naming the lessons still owed" (8.6). The
        // resume table is the one thing allowed to say where that is.
        if ($sitting === null && ! $exams->isAvailable($course)) {
            return redirect(NextAction::url($course));
        }

        $seated = $course->isOpen() && $course->status === CourseStatus::FinalExam;
        $pending = GenerationRequest::for($course, GenerationKind::FinalExam);
        $writing = $pending?->isPending() ?? false;

        return Inertia::render('courses/final-exam', [
            // `closed` is the book as it is handed over, `opening` is it being
            // written, `open` is the student sitting in front of it, `result`
            // is what a sitting did.
            //
            // `opening` is checked before `open` because the two are true
            // together for a moment: `FinalExam::open()` moves the course and
            // the job settles the request a beat later, and showing a book the
            // student cannot answer yet is worse than showing the wait twice.
            'state' => match (true) {
                $writing => 'opening',
                $seated => 'open',
                $sitting !== null => 'result',
                default => 'closed',
            },

            // Carried on the request row when the writing failed on the queue,
            // where there was no response left to report it in (ledger D25).
            'failure' => $pending instanceof GenerationRequest ? $pending->failure : null,
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
            // What the student has already ticked in the book they are
            // sitting now, cleared at hand-in so a re-sit opens empty
            // (6.3.4). Selections only: no verdict travels with them.
            // Cast so an empty sheet crosses the wire as `{}` rather than as
            // the `[]` an empty PHP array encodes to.
            'sheet' => (object) ($seated ? $exams->sheet($course) : []),

            // A book already sat is opened straight to the leaf, with no
            // ceremony (8.4.4).
            'resit' => $sitting !== null,
            'sitting' => $sitting === null ? null : [
                ...$sitting->toArray(),
                'passed' => $sitting->score() >= $exams->passMark(),
            ],
            'urls' => [
                'open' => route('courses.final-exam.store', $course),
                'answers' => route('courses.final-exam.answers.store', $course),
                'sittings' => route('courses.final-exam.sittings.store', $course),
                'refresher' => route('courses.refreshers.store', $course),
                'dashboard' => route('dashboard'),
            ],
        ]);
    }

    public function store(Request $request, Course $course, FinalExam $exams): JsonResponse
    {
        $this->authorize('participate', $course);
        abort_if($course->student->isDemo(), 403, 'A demo has no final exam.');

        // Availability is knowable without the model, so it stays a 422 in the
        // request rather than becoming a wait that ends in a refusal.
        $refusal = $exams->refusal($course);

        if ($refusal !== null) {
            return response()->json(['message' => $refusal], 422);
        }

        if (GenerationRequest::open($course, GenerationKind::FinalExam) !== null) {
            WriteFinalExam::dispatch($course->id);
        }

        // 202: the book is being written, and the screen reloads onto the
        // `opening` state to wait for it (ledger D25). Nothing of the exam is
        // in this reply, which keeps the answer key out of it by construction
        // rather than by remembering to strip it.
        return response()->json(['state' => 'opening'], 202);
    }
}
