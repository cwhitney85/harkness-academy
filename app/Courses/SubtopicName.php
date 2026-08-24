<?php

namespace App\Courses;

use App\Courses\Exceptions\UnusableSubtopicName;
use App\Models\Subtopic;
use Illuminate\Support\Str;

/**
 * What the student calls a subtopic, and the folder it becomes (spec 4.1).
 *
 * Both creation paths land here: picking "Algebra" from the catalog and
 * typing "algebra" freehand produce the same slug, so the open-course rule
 * sees one subtopic rather than two. Without that, both courses would write
 * the same WORKING_KNOWLEDGE.md and the second to finish would erase the
 * first's notes (ledger D7b).
 */
final readonly class SubtopicName
{
    /**
     * Kept under the `courses.subtopic_slug` column width, which is itself
     * sized so the deepest document path fits the memory tree (spec 4.5.3).
     */
    private const MAX_SLUG_LENGTH = 64;

    private function __construct(
        public string $title,
        public string $slug,
    ) {}

    public static function fromCatalog(Subtopic $subtopic): self
    {
        return new self($subtopic->title, $subtopic->slug);
    }

    /**
     * @throws UnusableSubtopicName
     */
    public static function fromFreeText(string $text): self
    {
        $title = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($title === '') {
            throw UnusableSubtopicName::for($text, 'it is blank.');
        }

        $slug = Str::slug($title);

        // Str::slug transliterates to ASCII, so a name written entirely in a
        // non-Latin script reduces to nothing. Accepted limit for v1: the
        // slug is a path segment a human has to read in `memory:show`, and a
        // generated stand-in would be unreadable there.
        if ($slug === '') {
            throw UnusableSubtopicName::for($text, 'it has no letters or digits that can form a folder name.');
        }

        if (strlen($slug) > self::MAX_SLUG_LENGTH) {
            throw UnusableSubtopicName::for($text, 'it is too long -- keep it under '.self::MAX_SLUG_LENGTH.' characters.');
        }

        return new self($title, $slug);
    }
}
