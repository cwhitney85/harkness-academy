<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Lessons\Exceptions\SelectionNotInLesson;
use App\Lessons\SelectionExplainer;
use App\Models\Lesson;
use App\Models\LessonMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /lessons/{lesson}/explanations` -- the side-tab of spec 5.5.
 *
 * The request names *which* transcript row the highlight came from and quotes
 * the highlight; it does not send the surrounding passage. The server reads
 * that out of `lesson_messages`, so the context in the call is always text the
 * tutor actually produced, and the only client-authored text in the whole call
 * is a highlight capped at SelectionExplainer::MAX_SELECTION characters. A
 * client-supplied passage would have turned an authenticated endpoint into a
 * general-purpose completion on our bill.
 *
 * Not streamed. 5.2.5 permits it ("Explainer MAY ship non-streamed first") and
 * `explainer.select` declares `output: markdown`, which is the same decision
 * written in the prompt file: under 200 words on the cheap tier arrives whole
 * fast enough that an SSE channel would be ceremony. The `explainer` turn kind
 * stays in the vocabulary for the day that stops being true.
 *
 * `participate` rather than `view`: the side-tab spends money on the student's
 * behalf. But it deliberately does *not* run LessonProgress' openability check
 * -- a stateless read cannot violate a progress invariant, and gating it would
 * mean a student revising a finished lesson could not use the one feature that
 * makes revision bearable.
 */
class SelectionExplanationController extends Controller
{
    public function store(
        Request $request,
        Lesson $lesson,
        SelectionExplainer $explainer,
    ): JsonResponse {
        $this->authorize('participate', $lesson);

        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
            'selection' => ['required', 'string', 'min:1', 'max:'.SelectionExplainer::MAX_SELECTION],
        ]);

        $source = $lesson->messages()->whereKey($validated['message_id'])->first();

        if (! $source instanceof LessonMessage) {
            abort(422, "Message [{$validated['message_id']}] is not part of this lesson.");
        }

        try {
            $explanation = $explainer->explain($lesson, $source, $validated['selection']);
        } catch (SelectionNotInLesson $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['explanation' => $explanation]);
    }
}
