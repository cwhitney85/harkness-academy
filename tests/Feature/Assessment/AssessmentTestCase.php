<?php

namespace Tests\Feature\Assessment;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Lessons\CompleteLesson;
use App\Lessons\LessonSections;
use App\Lessons\LessonSessions;
use App\Models\Course;
use App\Models\Lesson;
use Tests\Feature\Lessons\LessonEngineTestCase;

/**
 * The fixture package 6 starts from: package 5's, plus finished lessons.
 *
 * Extends the lesson engine's case rather than rebuilding it. Every assessment
 * except a checkpoint reads something the lesson engine wrote -- a summary, a
 * SUMMARY.md, a working-knowledge document, a homework quiz -- so a hand-built
 * fixture here would be a second opinion about what package 5 produces, and
 * the first divergence would show up as a passing package 6 test over a shape
 * that no longer exists.
 *
 * Lessons are taught through LessonSessions and completed through
 * CompleteLesson for the same reason: the transcripts, checkpoints and
 * pipeline rows are the real ones.
 */
abstract class AssessmentTestCase extends LessonEngineTestCase
{
    /**
     * Teach every section of a lesson, checkpoints and all.
     */
    protected function teachLesson(Lesson $lesson): Lesson
    {
        $sections = LessonSections::of($lesson);
        $script = [];

        for ($index = 1; $index <= count($sections); $index++) {
            $script[] = "Section {$index}: ".$sections->title($index).' explained.';
            $script[] = $this->checkpointResponse($index, $sections->concepts($index)[0]);
        }

        $this->scriptModel($script);

        for ($index = 1; $index <= count($sections); $index++) {
            $frames = [];

            app(LessonSessions::class)
                ->for($lesson->fresh())
                ->teachSection($index, $this->collectingStream($frames));
        }

        return $lesson->refresh();
    }

    /**
     * Teach a lesson and run its whole completion chain.
     *
     * Leaves behind exactly what 6.2 and 6.4 read: `lessons.summary_json`, a
     * rendered SUMMARY.md, a new working-knowledge version, and a homework
     * quiz.
     */
    protected function completeLesson(Lesson $lesson): Lesson
    {
        $this->teachLesson($lesson);

        $this->scriptModel([
            $this->summaryResponse($this->conceptsOf($lesson)),
            self::WK."\n\n- Lesson {$lesson->position} is folded in.",
            $this->quizResponse(),
        ]);

        app(CompleteLesson::class)($lesson->fresh());

        return $lesson->refresh();
    }

    /**
     * A course with every lesson completed -- ready for its final exam.
     */
    protected function taughtCourse(?Course $course = null): Course
    {
        $course ??= $this->activeCourse();

        foreach ($course->lessons as $lesson) {
            $this->completeLesson($lesson);
        }

        return $course->refresh();
    }

    /**
     * A course standing at `final_exam`.
     */
    protected function courseAtFinalExam(?Course $course = null): Course
    {
        return app(CourseTransitions::class)->to($this->taughtCourse($course), CourseStatus::FinalExam);
    }

    /**
     * The concept slugs one lesson's sections declare.
     *
     * @return list<string>
     */
    protected function conceptsOf(Lesson $lesson): array
    {
        return LessonSections::of($lesson)->declaredConcepts();
    }

    /**
     * A `json/quiz.v1` exam for the two-lesson fixture: two questions each.
     *
     * @return array<string, mixed>
     */
    protected function examResponse(): array
    {
        $questions = [];

        // Lesson 1 teaches variable, expression and coefficient; lesson 2
        // teaches equation, variable and inverse-operation. Two questions per
        // lesson is what QuizLength::exam() asks of a course this long.
        $rows = [
            ['e1', 'variable', 'What does the letter in 3n + 2 stand for?'],
            ['e2', 'expression', 'Which of these is an expression?'],
            ['e3', 'equation', 'Which of these is an equation?'],
            ['e4', 'inverse-operation', 'To undo "add 7", what do you do?'],
        ];

        foreach ($rows as [$id, $slug, $prompt]) {
            $questions[] = [
                'id' => $id,
                'prompt' => $prompt,
                'options' => [
                    ['id' => 'a', 'text' => 'The right answer'],
                    ['id' => 'b', 'text' => 'A plausible mistake'],
                    ['id' => 'c', 'text' => 'Another plausible mistake'],
                ],
                'correct_option_id' => 'a',
                'explanation' => "Because of {$slug}.",
                'concept_tag' => $slug,
            ];
        }

        return [
            'quiz' => [
                'scope' => 'Algebra: Getting Comfortable with the Unknown',
                'questions' => $questions,
                'suggested_reading' => [],
            ],
        ];
    }

    /**
     * A `json/quiz.v1` refresher, one question per slug.
     *
     * @param  list<string>  $slugs
     * @return array<string, mixed>
     */
    protected function refresherResponse(array $slugs): array
    {
        return [
            'quiz' => [
                'scope' => 'A warm-up',
                'questions' => array_map(static fn (string $slug): array => [
                    'id' => "r-{$slug}",
                    'prompt' => "A question about {$slug}, on a different example.",
                    'options' => [
                        ['id' => 'a', 'text' => 'The right answer'],
                        ['id' => 'b', 'text' => 'A plausible mistake'],
                        ['id' => 'c', 'text' => 'Another plausible mistake'],
                    ],
                    'correct_option_id' => 'a',
                    'explanation' => "Because of {$slug}.",
                    'concept_tag' => $slug,
                ], $slugs),
                'suggested_reading' => [],
            ],
        ];
    }
}
