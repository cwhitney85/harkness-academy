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
}
