<?php

namespace App\Courses\Syllabus;

use App\Courses\Exceptions\UndeclaredConcept;
use App\Models\CourseSyllabus;

/**
 * The concept vocabulary one syllabus declares (spec 4.5.2.2, ledger D10.2).
 *
 * The syllabus JSON *is* the vocabulary -- there is no concepts table, because
 * nothing in v1 queries by concept and inventing that schema here would design
 * package 6 before 6.4's requirements are known (ledger D10.2 rejects it, D9
 * sets the precedent).
 *
 * This is the contract 5.2.2, 5.4.2, 6.3 and 6.4 consume: a slug they mention
 * either resolves here or is a broken link.
 */
final readonly class SyllabusConcepts
{
    /**
     * @param  array<string, list<int>>  $placements  slug => lesson positions, ascending
     */
    private function __construct(private array $placements) {}

    /**
     * @param  array<string, mixed>  $syllabus  a `json/syllabus.v1` document
     */
    public static function from(array $syllabus): self
    {
        $placements = [];

        foreach ($syllabus['lessons'] ?? [] as $lesson) {
            foreach ($lesson['sections'] ?? [] as $section) {
                foreach ($section['concepts'] ?? [] as $slug) {
                    $placements[$slug][] = (int) $lesson['position'];
                }
            }
        }

        foreach ($placements as $slug => $positions) {
            $positions = array_values(array_unique($positions));
            sort($positions);
            $placements[$slug] = $positions;
        }

        return new self($placements);
    }

    public static function of(CourseSyllabus $syllabus): self
    {
        return self::from($syllabus->syllabus_json);
    }

    /**
     * Every slug the syllabus declares, in slug order.
     *
     * @return list<string>
     */
    public function declared(): array
    {
        $slugs = array_keys($this->placements);
        sort($slugs);

        return $slugs;
    }

    public function has(string $slug): bool
    {
        return isset($this->placements[$slug]);
    }

    /**
     * The lesson a concept is first taught in.
     *
     * Pre: `$slug` is declared. A slug may appear in more than one lesson --
     * the prompts require it to mean the same thing in each -- so the earliest
     * position is the one that introduces it.
     *
     * @throws UndeclaredConcept
     */
    public function introducedIn(string $slug): int
    {
        if (! $this->has($slug)) {
            throw UndeclaredConcept::among([$slug], $this->declared());
        }

        return $this->placements[$slug][0];
    }

    /**
     * Every lesson position that touches a concept, ascending.
     *
     * @return list<int>
     *
     * @throws UndeclaredConcept
     */
    public function taughtIn(string $slug): array
    {
        if (! $this->has($slug)) {
            throw UndeclaredConcept::among([$slug], $this->declared());
        }

        return $this->placements[$slug];
    }

    /**
     * Post: every slug given is declared, or nothing is -- the exception names
     * all the offenders at once rather than the first one found.
     *
     * @param  iterable<string>  $slugs
     *
     * @throws UndeclaredConcept
     */
    public function assertDeclared(iterable $slugs): void
    {
        $undeclared = [];

        foreach ($slugs as $slug) {
            if (! $this->has($slug)) {
                $undeclared[] = $slug;
            }
        }

        if ($undeclared !== []) {
            throw UndeclaredConcept::among(array_values(array_unique($undeclared)), $this->declared());
        }
    }
}
