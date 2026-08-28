<?php

namespace App\Lessons\Pipeline;

use App\Ai\Gateway;
use App\Assessment\HomeworkResults;
use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Memory\MemoryTree;
use App\Models\Lesson;
use Illuminate\Support\Facades\Log;

/**
 * Step two: fold the lesson into what the student knows (spec 5.4.4).
 *
 * Full-document replacement -- prior WK, the summary JSON and the student's
 * homework record for this subtopic in, the whole new WK out. Anything the model leaves out is forgotten, which is why
 * ErosionGuard reads the result before it is written and why every write goes
 * through the path API, which versions it (spec 3.2).
 *
 * Writes an empty document over a good one: never. An empty reply here would
 * erase everything every course in this subtopic recorded (ledger D7a and D8),
 * so it throws and lets 5.4.5's retries have a go.
 */
final class UpdateWorkingKnowledge extends PipelineJob
{
    public function step(): PipelineStep
    {
        return PipelineStep::WorkingKnowledge;
    }

    /**
     * @throws LessonSummaryUnusable
     */
    protected function run(Lesson $lesson): void
    {
        $gateway = app(Gateway::class);
        $memory = app(MemoryTree::class);

        $summary = $lesson->summary();

        if ($summary === null) {
            throw LessonSummaryUnusable::for($lesson, 'the summary step has not written one yet.');
        }

        $path = $lesson->course->workingKnowledgePath();
        $prior = $memory->get($lesson->course->student, $path);

        if ($prior === null || trim($prior) === '') {
            throw LessonSummaryUnusable::for($lesson, "there is no working-knowledge document at [{$path}] to update.");
        }

        $response = $gateway
            ->prompt('wk.update', [
                'prior_wk_doc' => $prior,
                'lesson_summary_json' => $summary->encode(),
                // Homework cannot reach the model through the summary: this
                // lesson's summary was written at step one and its homework is
                // generated at step three, so what arrives here is the
                // homework set after *earlier* lessons (spec 6.2).
                'homework_results' => HomeworkResults::forSubtopicOf($lesson)->render(),
            ])
            ->feature('lesson.wk_update')
            ->for($lesson->course->user_id)
            ->generate();

        $updated = trim($response->text);

        if ($updated === '') {
            throw LessonSummaryUnusable::for($lesson, 'the working-knowledge update came back empty.');
        }

        $lost = ErosionGuard::lost($lesson, $prior, $updated);

        if ($lost !== []) {
            // Reported, not blocked (spec 5.4.4, and 4.5.1's rule). The
            // previous version is still in `memory_versions`, so this line is
            // the pointer to a diff someone can actually take.
            Log::warning('Working-knowledge update dropped concepts the prior document recorded.', [
                'lesson_id' => $lesson->id,
                'path' => $path,
                'lost' => $lost,
            ]);
        }

        $memory->put($lesson->course->student, $path, $updated);
    }
}
