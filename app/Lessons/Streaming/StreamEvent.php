<?php

namespace App\Lessons\Streaming;

/**
 * One event on the wire (spec 5.2.5).
 *
 * Contract for an implementation:
 *   - `name()` is a member of StreamVocabulary::NAMES. Nothing else may go
 *     over the wire; 5.2.5's done-condition is "no undocumented events", and
 *     StreamVocabularyTest fails the build on a name that is not listed.
 *   - `data()` is JSON-encodable and carries exactly the fields 5.2.5 declares
 *     for that name, with absent optionals omitted rather than sent as null.
 */
interface StreamEvent
{
    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function data(): array;
}
