<?php

namespace App\Courses\Exceptions;

/**
 * A concept slug nobody declared (spec 4.5.2).
 *
 * The slug vocabulary is declared once, by the approved syllabus, and every
 * later mention of a concept -- checkpoints (5.2.2), lesson summaries (5.4.2),
 * exams (6.3), refreshers (6.4) -- has to resolve against it. A slug that does
 * not is a broken link in the connective tissue, not a new concept.
 */
final class UndeclaredConcept extends CourseException
{
    /**
     * @param  list<string>  $slugs
     * @param  list<string>  $declared
     */
    public static function among(array $slugs, array $declared): self
    {
        return new self(sprintf(
            'Concept slug(s) [%s] are not declared by this syllabus; it declares [%s].',
            implode(', ', $slugs),
            implode(', ', $declared),
        ));
    }
}
