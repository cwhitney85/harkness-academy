<?php

namespace App\Lessons\Pipeline;

use App\Courses\Syllabus\SyllabusConcepts;
use App\Models\Course;
use App\Models\Lesson;

/**
 * Catching a working-knowledge document that forgot something (spec 5.4.4).
 *
 * `wk.update` is a full-document replacement: the model receives the whole WK
 * and returns the whole WK. That is the right shape -- an append-only document
 * can never demote a concept the student turned out not to have -- but it puts
 * every previously recorded concept at risk on every single lesson. A model
 * that quietly drops a slug it was not thinking about erases a course's worth
 * of evidence, and nothing downstream would notice, because the next update
 * reads the eroded document as if it were the truth.
 *
 * WK is subtopic-scoped (ledger D8), so the vocabulary at risk is every slug
 * declared by every approved syllabus in the subtopic -- including courses at
 * other levels that finished months ago.
 *
 * **The guard reports; it does not block.** Spec 4.5.1's rule: failing closed
 * here would strand a completed lesson's pipeline over a document that is
 * merely worse, and `memory_versions` already keeps the previous version, so
 * erosion is diffable and recoverable. Blocking would trade a recoverable
 * problem for an unrecoverable one.
 */
final class ErosionGuard
{
    /**
     * Slugs the prior document recorded and the new one does not.
     *
     * A slug counts as surviving if it appears anywhere in the new document --
     * including under a different heading, which is exactly what an explicit
     * demotion looks like (`## Solid` → `## Shaky`). That is 5.4.4's "survives
     * or is explicitly demoted": what it forbids is disappearance.
     *
     * @return list<string>
     */
    public static function lost(Lesson $lesson, string $prior, string $next): array
    {
        $lost = [];

        foreach (self::vocabulary($lesson) as $slug) {
            if (self::mentions($prior, $slug) && ! self::mentions($next, $slug)) {
                $lost[] = $slug;
            }
        }

        return $lost;
    }

    /**
     * Every concept slug any course in this subtopic has declared.
     *
     * @return list<string>
     */
    public static function vocabulary(Lesson $lesson): array
    {
        $course = $lesson->course;

        $courses = Course::query()
            ->forSubtopic($course->student, $course->module_slug, $course->subtopic_slug)
            ->with('approvedSyllabus')
            ->get();

        $slugs = [];

        foreach ($courses as $sibling) {
            if ($sibling->approvedSyllabus === null) {
                continue;
            }

            foreach (SyllabusConcepts::of($sibling->approvedSyllabus)->declared() as $slug) {
                $slugs[$slug] = true;
            }
        }

        $slugs = array_keys($slugs);
        sort($slugs);

        return $slugs;
    }

    /**
     * Whether a document records a concept.
     *
     * Case-insensitive substring, deliberately generous. A false negative here
     * raises a false alarm in a log; a false positive hides a real erosion.
     * Slugs are hyphenated and specific enough that accidental matches are
     * rare, and the document is prose the model wrote about those same slugs.
     */
    private static function mentions(string $document, string $slug): bool
    {
        return stripos($document, $slug) !== false;
    }
}
