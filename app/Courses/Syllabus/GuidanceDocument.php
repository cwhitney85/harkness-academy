<?php

namespace App\Courses\Syllabus;

/**
 * One lesson's teaching notes, rendered from the approved syllabus (4.5.3).
 *
 * A PHP renderer rather than a Blade template, deliberately (ledger D10.3):
 * Blade would put model-facing text in `resources/views/`, the directory that
 * holds user-facing things, and 4.5.3's rule -- no route or component reads
 * GUIDANCE.md -- would then need an exemption for its own template.
 *
 * Rendering is a pure function of the approved syllabus, and the approved
 * syllabus never changes (spec 4.5.4.1), which is what makes materialising
 * these files safe.
 */
final class GuidanceDocument
{
    /**
     * Pre: `$lesson` is an entry of `$syllabus['lessons']`.
     * Post: markdown addressed to the tutor, never to the student.
     *
     * @param  array<string, mixed>  $syllabus  a `json/syllabus.v1` document
     * @param  array<string, mixed>  $lesson  one entry of `lessons[]`
     */
    public static function render(array $syllabus, array $lesson): string
    {
        $lines = [
            "# {$lesson['title']}",
            '',
            "Lesson {$lesson['position']} of ".count($syllabus['lessons'])." in *{$syllabus['course_title']}*.",
            '',
            '## Objective',
            '',
            (string) $lesson['objective'],
            '',
            '## Sections',
            '',
            'Teach these in order. Each one ends in a checkpoint before the next begins.',
            '',
        ];

        foreach ($lesson['sections'] as $index => $section) {
            $lines[] = ($index + 1).". **{$section['title']}**";
            $lines[] = '   Concepts: '.implode(', ', $section['concepts']);
            $lines[] = '';
        }

        $lines[] = '## Homework focus';
        $lines[] = '';
        $lines[] = (string) $lesson['homework_focus'];
        $lines[] = '';
        $lines[] = '## Pacing';
        $lines[] = '';
        $lines[] = "Aim for about {$lesson['estimated_minutes']} minutes. Treat that as the shape of the lesson, not a stopwatch -- a student who is engaged and asking questions is the point.";
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = 'The concept slugs above are the vocabulary checkpoints, summaries and exams all key on. Use them as written; do not invent new ones mid-lesson.';

        return implode("\n", $lines)."\n";
    }
}
