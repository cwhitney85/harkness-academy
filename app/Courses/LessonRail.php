<?php

namespace App\Courses;

use App\Models\Course;
use App\Models\Lesson;

/**
 * A course's lessons as a rail draws them (spec 8.5, 8.8, 8.9).
 *
 * Position, title, whether it is done, and the URL that opens it -- null while
 * the lesson is locked, so the rail draws "openable" from the presence of a
 * URL rather than from a second rule of its own.
 *
 * Written once because two screens draw the same list: the lesson rail (8.8)
 * and the exam rail (8.9.3), where a failed sitting's whole recovery is going
 * back to a lesson. Titles come off the approved syllabus in one read rather
 * than through `Lesson::syllabusEntry()` per row, which would parse the same
 * JSON once per lesson to answer the same question.
 *
 * Pre:  the course has an approved syllabus -- every state that draws a rail
 *       is past 4.5.4.
 * Post: every URL names a route that exists.
 */
final class LessonRail
{
    /**
     * @return list<array{position: int, title: string, completed: bool, url: string|null}>
     */
    public static function of(Course $course): array
    {
        /** @var list<array<string, mixed>> $entries */
        $entries = $course->approvedSyllabus->syllabus_json['lessons'];
        $titles = array_column($entries, 'title', 'position');

        return array_values(array_map(static fn (Lesson $lesson): array => [
            'position' => $lesson->position,
            'title' => (string) ($titles[$lesson->position] ?? "Lesson {$lesson->position}"),
            'completed' => $lesson->status === LessonStatus::Completed,
            'url' => $lesson->status === LessonStatus::Locked
                ? null
                : route('lessons.show', $lesson),
        ], $course->lessons->all()));
    }
}
