<?php

namespace App\Lessons;

use App\Lessons\Exceptions\NoSuchSection;
use App\Models\Lesson;
use Countable;

/**
 * The sections a lesson is code-paced through (ledger D5, spec 5.2).
 *
 * **Indices are 1-based**, like `lessons.position` and like the numbered list
 * GUIDANCE.md renders. The wire, the database, the SSE payloads and the
 * `lesson.section` prompt all count from one, so no layer has to remember
 * which side of an off-by-one it is on.
 *
 * Read from the approved syllabus JSON rather than parsed back out of
 * GUIDANCE.md. Both say the same thing -- GUIDANCE is rendered *from* the
 * syllabus (4.5.3) -- and the JSON is the form that cannot be misread. The
 * rule GUIDANCE.md's door test enforces is that no route or component reads
 * it; the lesson engine is its one legitimate reader, and it reads the whole
 * document as `guidance_doc` in the system prompt (5.1). What this class
 * supplies is the per-section slice, which markdown was never the source of.
 */
final readonly class LessonSections implements Countable
{
    /**
     * @param  list<array{title: string, concepts: list<string>}>  $sections
     */
    private function __construct(private array $sections) {}

    public static function of(Lesson $lesson): self
    {
        /** @var array<int, array{title: string, concepts: list<string>}> $sections */
        $sections = $lesson->syllabusEntry()['sections'];

        return new self(array_values($sections));
    }

    public function count(): int
    {
        return count($this->sections);
    }

    /**
     * Whether `$index` names a section of this lesson.
     */
    public function has(int $index): bool
    {
        return $index >= 1 && $index <= count($this->sections);
    }

    /**
     * @throws NoSuchSection
     */
    public function title(int $index): string
    {
        return $this->section($index)['title'];
    }

    /**
     * The concept slugs this section is responsible for.
     *
     * @return list<string>
     *
     * @throws NoSuchSection
     */
    public function concepts(int $index): array
    {
        return $this->section($index)['concepts'];
    }

    /**
     * Every slug the lesson declares, across all its sections, in slug order.
     *
     * The vocabulary 5.4.2 holds a summary to: a mastery report may only key
     * on concepts this lesson actually claimed to teach.
     *
     * @return list<string>
     */
    public function declaredConcepts(): array
    {
        $slugs = [];

        foreach ($this->sections as $section) {
            foreach ($section['concepts'] as $slug) {
                $slugs[$slug] = true;
            }
        }

        $slugs = array_keys($slugs);
        sort($slugs);

        return $slugs;
    }

    public function isLast(int $index): bool
    {
        return $index === count($this->sections);
    }

    /**
     * The `section_concepts` variable of the `lesson.section` prompt.
     *
     * @throws NoSuchSection
     */
    public function conceptList(int $index): string
    {
        $lines = array_map(
            static fn (string $slug): string => "- {$slug}",
            $this->concepts($index),
        );

        return implode("\n", $lines);
    }

    /**
     * The `guidance_section` variable of the `checkpoint.generate` prompt --
     * what this one section was meant to cover (5.2.1).
     *
     * @throws NoSuchSection
     */
    public function guidance(int $index): string
    {
        return sprintf(
            "Section %d of %d: **%s**\n\nConcepts: %s",
            $index,
            count($this->sections),
            $this->title($index),
            implode(', ', $this->concepts($index)),
        );
    }

    /**
     * @return array{title: string, concepts: list<string>}
     *
     * @throws NoSuchSection
     */
    private function section(int $index): array
    {
        if (! $this->has($index)) {
            throw NoSuchSection::at($index, count($this->sections));
        }

        return $this->sections[$index - 1];
    }
}
