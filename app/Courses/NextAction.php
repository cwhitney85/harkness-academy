<?php

namespace App\Courses;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

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

            // The rollup for the module this course sat under (8.10.2). A
            // course whose catalog row has gone has no rollup to land on, and
            // the dashboard is where its card sits either way.
            CourseStatus::Completed => $course->module instanceof Module
                ? route('modules.show', $course->module)
                : route('dashboard'),
        };
    }

    /**
     * The lowest lesson still owed, or what follows the last one (spec 8.6).
     */
    private static function activeCourse(Course $course): string
    {
        $lesson = $course->lessons()
            ->where('status', '!=', LessonStatus::Completed->value)
            ->first();

        return $lesson instanceof Lesson
            ? route('lessons.show', $lesson)
            : self::afterLastLesson($course);
    }

    /**
     * Where a course goes once its last lesson is done: the final exam -- or,
     * for a demo course, the offer to keep it, because the demo ends there and
     * has no exam (spec 12.4.1, 12.6.2). S7, S8 and the dashboard all read it
     * here, so the three cannot point different ways.
     */
    public static function afterLastLesson(Course $course): string
    {
        return $course->student->isDemo()
            ? route('demo.account.create')
            : route('courses.final-exam.show', $course);
    }
}
