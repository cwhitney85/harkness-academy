<?php

namespace App\Lessons\Pipeline;

use App\Models\Lesson;

/**
 * The chain, as a list (spec 5.4.1).
 *
 * One place that knows which jobs run and in what order, so
 * `PipelineStep::inOrder()` and the dispatched chain cannot drift apart --
 * PipelineOrderTest asserts they agree.
 */
final class LessonPipelineJobs
{
    /**
     * @return list<PipelineJob>
     */
    public function forLesson(Lesson $lesson): array
    {
        return [
            new SummarizeLesson($lesson->id),
            new UpdateWorkingKnowledge($lesson->id),
            new GenerateHomework($lesson->id),
            new UnlockNextLesson($lesson->id),
        ];
    }
}
