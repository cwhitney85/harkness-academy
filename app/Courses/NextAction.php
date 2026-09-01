<?php

namespace App\Courses;

use App\Models\Course;
use App\Models\Lesson;

/**
 * Where a course picks back up (spec 8.6, "Where a resume lands").
 *
 * The flow map states this as a table and says nothing else may decide it.
 * This is that table, written once: the dashboard's "next action" per course
 * (8.10.1), the landing after `POST /courses/{course}/resume` (8.7.6), and
 * every guard on a pipeline screen that finds a course has moved past it all
 * read the same rows here.
 *
 * Pre:  `$course` belongs to the student the URL will be shown to. This class
 *       does no authorization -- it answers "where", not "whether".
 * Post: the return value is a URL of a route that exists.
 *
 * `standing()` rather than `status`, because a withdrawn course still holds
 * the pipeline position it was left on (ledger D7d) and the ending outranks
 * it.
 */
final class NextAction
{
    public static function url(Course $course): string
    {
        return match ($course->standing()) {
            CourseStatus::Created,
            CourseStatus::KnowledgeInterview => route('courses.knowledge-interview.show', $course),

            CourseStatus::GoalsInterview => route('courses.goals-interview.show', $course),

            CourseStatus::SyllabusDraft,
            CourseStatus::SyllabusRevision => route('courses.syllabus.show', $course),

            CourseStatus::Active => self::activeCourse($course),

            CourseStatus::FinalExam => route('courses.final-exam.show', $course),

            // A course the student set aside lives in the dashboard's
            // set-aside list; `POST …/resume` clears the date and this table
            // is then read a second time (8.6, 8.10.3).
            CourseStatus::Withdrawn => route('dashboard'),

            // The table says `GET /modules/{module}`, which is 8.10.2's URL
            // and does not exist yet. The dashboard is where a completed
            // course's card sits until it does; this arm is the whole of
            // 8.10.2's change here.
            CourseStatus::Completed => route('dashboard'),
        };
    }

    /**
     * The lowest lesson still owed, or the exam if none remain (spec 8.6).
     */
    private static function activeCourse(Course $course): string
    {
        $lesson = $course->lessons()
            ->where('status', '!=', LessonStatus::Completed->value)
            ->first();

        return $lesson instanceof Lesson
            ? route('lessons.show', $lesson)
            : route('courses.final-exam.show', $course);
    }
}
