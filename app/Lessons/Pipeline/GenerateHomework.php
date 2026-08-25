<?php

namespace App\Lessons\Pipeline;

use App\Ai\Gateway;
use App\Assessment\QuizKind;
use App\Assessment\QuizSchema;
use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Models\Lesson;
use Illuminate\Database\QueryException;

/**
 * Step three: homework aimed at what came out shaky (spec 6.2, riding 5.4.1).
 *
 * The `homework_focus` comes from the syllabus, written at planning time; the
 * summary supplies what actually happened. Both, because the plan says what
 * this lesson was for and the summary says where the student is -- homework
 * built from either alone is either off-target or off-syllabus.
 *
 * Serving this quiz to the student, and grading it, is package 6.2's half. All
 * this step owes is the row (ledger D12).
 */
final class GenerateHomework extends PipelineJob
{
    public function step(): PipelineStep
    {
        return PipelineStep::Homework;
    }

    /**
     * @throws LessonSummaryUnusable
     */
    protected function run(Lesson $lesson): void
    {
        $summary = $lesson->summary();

        if ($summary === null) {
            throw LessonSummaryUnusable::for($lesson, 'the summary step has not written one yet.');
        }

        $response = app(Gateway::class)
            ->prompt('homework.generate', [
                'lesson_summary_json' => $summary->encode(),
                'homework_focus' => (string) $lesson->syllabusEntry()['homework_focus'],
            ])
            ->feature('lesson.homework')
            ->for($lesson->course->user_id)
            ->schema(QuizSchema::definition())
            ->generate();

        if (! is_array($response->structured) || ! is_array($response->structured['quiz'] ?? null)) {
            throw LessonSummaryUnusable::for($lesson, 'the model returned no structured homework.');
        }

        try {
            $lesson->quizzes()->create([
                'user_id' => $lesson->course->user_id,
                'course_id' => $lesson->course_id,
                'kind' => QuizKind::Homework,
                'section_index' => null,
                'payload' => $response->structured,
            ]);
        } catch (QueryException $e) {
            // A concurrent chain already wrote this lesson's homework. The
            // unique index is the arbiter; one homework quiz is the outcome
            // either way.
            if (! $lesson->quizzes()->ofKind(QuizKind::Homework)->exists()) {
                throw $e;
            }
        }
    }
}
