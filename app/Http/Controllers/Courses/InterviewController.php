<?php

namespace App\Http\Controllers\Courses;

use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\Interviews;
use App\Courses\Interviews\InterviewSession;
use App\Courses\NextAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courses\Concerns\ConductsInterview;
use App\Models\Course;
use App\Models\InterviewMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screens S3 and S4 -- the two interviews (spec 8.7.2, 8.7.3).
 *
 * **A reload paints from here and never re-opens the interview.** That is the
 * whole contract of this action, and the reason `may_open` is computed on the
 * server: opening discards the transcript, so a client left to work out for
 * itself whether to call the stream URL is a client that can lose a student's
 * interview. The engine refuses a destructive re-open as well
 * (InterviewSession::open), which makes this flag a courtesy rather than the
 * only guard.
 *
 * `may_open` is true in exactly two cases: nothing has been said yet, and the
 * connection dropped during the tutor's very first question -- where the
 * transcript holds the student's own "I am ready to start." and nothing else.
 * Re-opening there discards nothing the student typed, which is the whole of
 * what 8.6's precondition protects (Colin, 2026-09-01).
 */
class InterviewController extends Controller
{
    use ConductsInterview;

    public function show(Request $request, Course $course, Interviews $interviews): Response|RedirectResponse
    {
        $this->authorize('participate', $course);

        $kind = $this->kind($request);
        $session = $this->session($interviews, $course, $kind);

        if (! $this->underway($course, $session)) {
            return redirect(NextAction::url($course));
        }

        $transcript = $this->transcript($course, $kind);

        return Inertia::render('courses/interview', [
            'course' => [
                'id' => $course->id,
                'title' => $course->subtopic_title,
                'level' => $course->skill_level->title(),
            ],
            'kind' => $kind->value,
            'turns' => array_map(
                fn (InterviewMessage $turn): array => [
                    'id' => $turn->id,
                    'speaker' => $turn->speaker->value,
                    'content' => $turn->content,
                ],
                $transcript->lines(),
            ),
            'may_open' => ! $transcript->hasTutorTurn(),
            'limits' => ['answer_characters' => InterviewSession::MAX_ANSWER],
            'urls' => $this->urls($course, $kind),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function urls(Course $course, InterviewKind $kind): array
    {
        $prefix = $kind === InterviewKind::Knowledge
            ? 'courses.knowledge-interview'
            : 'courses.goals-interview';

        return [
            'stream' => route($prefix.'.stream', $course),
            'answers' => route($prefix.'.answers.store', $course),
            'complete' => route($prefix.'.complete', $course),
        ];
    }
}
