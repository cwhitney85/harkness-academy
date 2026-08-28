<?php

namespace App\Lessons\Pipeline;

use App\Ai\Gateway;
use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Assessment\QuizSchema;
use App\Assessment\Quizzes;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Models\Lesson;

/**
 * Step three: homework aimed at what came out shaky (spec 6.2, riding 5.4.1).
 *
 * The `homework_focus` comes from the syllabus, written at planning time; the
 * summary supplies what actually happened. Both, because the plan says what
 * this lesson was for and the summary says where the student is -- homework
 * built from either alone is either off-target or off-syllabus.
 *
 * Serving this quiz to the student, and grading it, is package 6.2's half. All
 * this step owes is a row that can be served: the answer key is checked
 * against the options here, and a concept slug the syllabus does not declare
 * is dropped rather than carried into a later refresher (5.2.2's rule).
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

        $document = QuizDocument::in($response->structured, QuizKind::Homework);

        // Fails **closed**, unlike a checkpoint. A checkpoint that will not
        // generate costs the student a quiz they can walk past (5.2.1); an
        // ungradable homework row is one the student meets later and cannot
        // answer, and the chain's retries are free at this point because the
        // lesson is already completed and irrevocable (5.4.5).
        $defects = $document->defects();

        if ($defects !== []) {
            throw LessonSummaryUnusable::for($lesson, 'the homework cannot be graded: '.implode(' ', $defects));
        }

        // Created through 6.1's service, which owns the one-per-lesson race
        // a concurrent chain can run (ledger D12).
        app(Quizzes::class)->create(
            QuizKind::Homework,
            $lesson,
            $lesson->course,
            $document->withResolvedTags(
                SyllabusConcepts::of($lesson->course->approvedSyllabus)->declared(),
            )->payload(),
        );
    }
}
