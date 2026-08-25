<?php

namespace App\Lessons;

use App\Courses\Syllabus\LessonGuidance;
use App\Models\Lesson;

/**
 * `{lesson}/SUMMARY.md` -- the record of a finished lesson (spec 5.4.3).
 *
 * A PHP renderer rather than a Blade template, for ledger D10.3's reason: this
 * is model-facing text and Blade would file it under `resources/views/`, the
 * directory for things people look at. A refresher quiz reads this document
 * (6.4); no route or component does.
 *
 * It sits beside GUIDANCE.md in the same lesson directory, which is the whole
 * arc of a lesson in one place: what it was meant to do, and what it did.
 *
 * Rendering is a pure function of `lessons.summary_json`, and a completed
 * lesson's summary is written once and never rewritten (5.4.3's "completed
 * summaries immutable"), so the document and the JSON cannot drift.
 */
final class SummaryDocument
{
    public const FILENAME = 'SUMMARY.md';

    /**
     * Where this lesson's summary lives in the memory tree.
     *
     * The same `{lesson-slug}` segment GUIDANCE.md uses, so the two are
     * siblings. Like GUIDANCE it carries no attempt number, which is the
     * reason only the highest attempt at a level is resumable (ledger D7b).
     */
    public static function pathFor(Lesson $lesson): string
    {
        $entry = $lesson->syllabusEntry();

        return $lesson->course->materialsPrefix().'/'.LessonGuidance::slugFor($entry).'/'.self::FILENAME;
    }

    /**
     * Post: markdown addressed to the model that will teach next, never to the
     * student.
     */
    public static function render(Lesson $lesson, LessonSummary $summary): string
    {
        $entry = $lesson->syllabusEntry();

        $lines = [
            "# {$entry['title']}",
            '',
            "Lesson {$lesson->position} of *{$lesson->course->approvedSyllabus->syllabus_json['course_title']}*, completed "
                .($lesson->completed_at?->toDateString() ?? 'today').'.',
            '',
            '## What happened',
            '',
            $summary->prose(),
            '',
            '## Concepts',
            '',
        ];

        foreach ($summary->concepts() as $concept) {
            $lines[] = "- **{$concept['slug']}** — {$concept['mastery']->value}: {$concept['evidence']}";
        }

        $lines[] = '';
        $lines[] = '## Struggles';
        $lines[] = '';
        $lines = [...$lines, ...self::bullets($summary->struggles(), 'Nothing the lesson recorded as a struggle.')];

        $lines[] = '';
        $lines[] = '## Questions the student asked';
        $lines[] = '';
        $lines = [...$lines, ...self::bullets($summary->questionsAsked(), 'The student asked nothing of their own accord.')];

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = 'The concept slugs above come from this course\'s syllabus. A refresher quiz built from this document may only ask about what is recorded here.';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function bullets(array $items, string $whenEmpty): array
    {
        if ($items === []) {
            return [$whenEmpty];
        }

        return array_map(static fn (string $item): string => "- {$item}", $items);
    }
}
