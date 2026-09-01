<?php

namespace Database\Seeders\Fixtures;

use App\Lessons\Speaker;

/**
 * One taught section of a fixture lesson (spec 8.2.3).
 *
 * The prose is what the tutor actually streamed, so it goes into the
 * transcript as a `section` turn exactly as the engine would have written it.
 * The exchange after it is the Q&A the student had in the middle of that
 * section -- the same table, a different turn kind (5.2.6).
 *
 * A `LessonFixture` carries one of these per section the student has reached,
 * which is how far the transcript goes: sections beyond the list are simply
 * not taught yet.
 */
final readonly class SectionFixture
{
    /**
     * @param  list<array{0: Speaker, 1: string}>  $exchange  spoken after the section, in order
     */
    public function __construct(
        public string $prose,
        public array $exchange = [],
    ) {}
}
