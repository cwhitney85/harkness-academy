<?php

namespace App\Lessons\Pipeline;

use App\Ai\Gateway;
use App\Lessons\Checkpoints\CheckpointResults;
use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Lessons\LessonSections;
use App\Lessons\LessonSummary;
use App\Lessons\LessonSummarySchema;
use App\Lessons\LessonTranscript;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Step one: what did this lesson establish? (spec 5.4.2, 5.4.3)
 *
 * The JSON lands on the lesson row and the rendered SUMMARY.md lands in the
 * memory tree, **in one transaction**. The tree is Postgres too (ledger D3a),
 * so this is the same guarantee ApproveSyllabus relies on: there is no state
 * where the row claims a summary the tree does not have.
 *
 * The checkpoint grades reaching the prompt are code-computed
 * (CheckpointResults). 5.4.2's rule is that the model interprets grades and
 * never recomputes them -- handing it the questions and the raw answers would
 * let the summary disagree with the mark the student was shown.
 */
final class SummarizeLesson extends PipelineJob
{
    public function step(): PipelineStep
    {
        return PipelineStep::Summarize;
    }

    /**
     * @throws LessonSummaryUnusable
     */
    protected function run(Lesson $lesson): void
    {
        $gateway = app(Gateway::class);
        $memory = app(MemoryTree::class);

        $sections = LessonSections::of($lesson);
        $transcript = new LessonTranscript($lesson);

        $guidance = $memory->get($lesson->course->student, $lesson->guidance_path);

        if ($guidance === null) {
            throw LessonSummaryUnusable::for($lesson, "its guidance document at [{$lesson->guidance_path}] is gone.");
        }

        $response = $gateway
            ->prompt('lesson.summarize', [
                'lesson_transcript' => $transcript->render(),
                'guidance_doc' => $guidance,
                'checkpoint_results' => CheckpointResults::for($lesson)->render(),
            ])
            ->feature('lesson.summarize')
            ->for($lesson->course->user_id)
            ->schema(LessonSummarySchema::definition())
            ->generate();

        if (! is_array($response->structured)) {
            throw LessonSummaryUnusable::for($lesson, 'the model returned no structured summary.');
        }

        $summary = LessonSummary::fromModel($response->structured, $lesson, $sections);

        DB::transaction(function () use ($lesson, $summary, $memory): void {
            $lesson->forceFill(['summary_json' => $summary->document()])->save();

            $memory->put(
                $lesson->course->student,
                SummaryDocument::pathFor($lesson),
                SummaryDocument::render($lesson, $summary),
            );
        });
    }
}
