<?php

namespace App\Http\Controllers\Courses;

use App\Assessment\QuizLength;
use App\Courses\CourseStatus;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\Exceptions\SyllabusUnusable;
use App\Courses\NextAction;
use App\Courses\Syllabus\SyllabusGenerator;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S5 -- the syllabus the student approves or sends back (spec 8.7.4,
 * 8.7.5, 4.5.4).
 *
 * Three verbs at two URLs, which the flow map settled rather than leaving to
 * this controller: generating the first draft is its own POST here, revising
 * is `…/syllabus/revisions`, and approving is `…/syllabus/approval`. The last
 * two are separate because `CourseStatus` already treats them as two
 * successors of `syllabus_draft`.
 *
 * **Generation is not a side effect of arriving.** A `top`-tier call on a
 * redirect path has nowhere to report failure; on its own endpoint a failure
 * leaves the student here with a button, which is the whole recovery (4.5.1).
 * So GET renders an empty state when there is no draft, and the student asks.
 *
 * The map draws that failure as a 503. It is a redirect back with a flashed
 * message instead, because the screen is Inertia and a 503 page would replace
 * the very screen the recovery lives on. The outcome the map states -- "S5
 * unchanged; asking again is the recovery" -- is what actually happens.
 */
class SyllabusController extends Controller
{
    public function show(Request $request, Course $course): Response|RedirectResponse
    {
        $this->authorize('view', $course);

        if (! $this->drafting($course)) {
            return redirect(NextAction::url($course));
        }

        $draft = $course->latestSyllabus()->first();

        return Inertia::render('courses/syllabus', [
            'course' => [
                'id' => $course->id,
                'title' => $course->subtopic_title,
                'level' => $course->skill_level->title(),
            ],
            'syllabus' => $draft instanceof CourseSyllabus ? $this->draft($draft) : null,
            'revisions' => $this->revisions($draft),
            'exam' => $this->exam(),

            // Flashed by the three POSTs below when they refuse. Read here
            // rather than shared globally, so nothing outside this screen has
            // to know the word `failure` (spec 8.7.4).
            'failure' => $request->session()->get('failure'),
            'urls' => [
                'generate' => route('courses.syllabus.store', $course),
                'revise' => route('courses.syllabus.revisions.store', $course),
                'approve' => route('courses.syllabus.approval.store', $course),
            ],
        ]);
    }

    /**
     * Draw up generation 1 (spec 4.5.4).
     *
     * Pre:  the course stands at `syllabus_draft` and has no syllabus rows.
     * Post: one draft exists, or nothing was written and the course has not
     *       moved (SyllabusGenerator fails closed).
     */
    public function store(Request $request, Course $course, SyllabusGenerator $generator): RedirectResponse
    {
        $this->authorize('participate', $course);

        try {
            $generator->first($course);
        } catch (SyllabusUnusable) {
            return back()->with('failure', 'The syllabus could not be drawn up just now. Nothing has changed — ask again.');
        } catch (SyllabusNotDraftable|MissingCourseDocument $e) {
            return back()->with('failure', $e->getMessage());
        }

        return redirect()->route('courses.syllabus.show', $course);
    }

    /**
     * Whether S5 is where this course belongs at all.
     *
     * `syllabus_revision` counts: the previous draft still stands and is still
     * approvable while a regeneration is being asked for (8.6's resume table).
     */
    private function drafting(Course $course): bool
    {
        return $course->isOpen() && in_array(
            $course->status,
            [CourseStatus::SyllabusDraft, CourseStatus::SyllabusRevision],
            strict: true,
        );
    }

    /**
     * The draft as the screen reads it (spec 4.5.2's `json/syllabus.v1`).
     *
     * `subtopic_mismatch` travels with it rather than beside it: 4.5.7's
     * banner is a region of this screen and the flag is a field of the
     * syllabus, not a second fact about the course.
     *
     * @return array<string, mixed>
     */
    private function draft(CourseSyllabus $draft): array
    {
        return [
            'id' => $draft->id,
            'generation' => $draft->generation,
            'feedback' => $draft->feedback,
            ...$draft->syllabus_json,
        ];
    }

    /**
     * What the final exam this syllabus names will ask of the student.
     *
     * Read from the classes that enforce them rather than written on the
     * screen: `QuizLength::exam()` bounds the question count (D20) and
     * `assessment.final_exam.pass_mark` is the gate. A screen that stated
     * "eight to twelve questions, seventy per cent" in its own words would go
     * on saying it after either number changed.
     *
     * @return array<string, mixed>
     */
    private function exam(): array
    {
        $length = QuizLength::exam();

        return [
            'questions' => ['min' => $length->min, 'max' => $length->max],
            'pass_mark' => (float) config('assessment.final_exam.pass_mark'),
        ];
    }

    /**
     * How many times the student may still send it back (spec 4.5.4).
     *
     * Generation 1 is the first draft, so revisions used is one less than the
     * generation showing. The cap is config, read here rather than restated on
     * the screen.
     *
     * @return array<string, int>
     */
    private function revisions(?CourseSyllabus $draft): array
    {
        $allowed = (int) config('courses.max_syllabus_revisions');
        $used = $draft instanceof CourseSyllabus ? $draft->generation - 1 : 0;

        return [
            'used' => $used,
            'allowed' => $allowed,
            'remaining' => max(0, $allowed - $used),
        ];
    }
}
