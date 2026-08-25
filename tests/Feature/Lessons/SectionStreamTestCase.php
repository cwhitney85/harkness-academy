<?php

namespace Tests\Feature\Lessons;

use App\Lessons\LessonSessions;
use App\Models\Lesson;

/**
 * A single lesson, ready to teach, for the stream and checkpoint tests.
 */
abstract class SectionStreamTestCase extends LessonEngineTestCase
{
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lesson = $this->lesson();
    }

    /**
     * Teach one section into a collected frame list.
     *
     * @param  list<string>  $frames
     */
    protected function teach(int $index, array &$frames): void
    {
        app(LessonSessions::class)
            ->for($this->lesson->fresh())
            ->teachSection($index, $this->collectingStream($frames));
    }
}
