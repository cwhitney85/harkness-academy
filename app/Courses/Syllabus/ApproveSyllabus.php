<?php

namespace App\Courses\Syllabus;

use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\LessonStatus;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Support\Facades\DB;

/**
 * The student says yes, and the course becomes real (spec 4.5.5).
 *
 * One transaction, four effects: lesson rows, materialised GUIDANCE.md for
 * each of them, the approval stamp, and the move to `active`. A kill anywhere
 * inside leaves none of them -- the memory tree is Postgres too (ledger D3a),
 * so materialisation is inside the same transaction rather than merely near it.
 *
 * Materialisation is safe because an approved syllabus never changes: approval
 * only ever stamps `approved_at`, and a revision is a new row (4.5.4.1). If
 * that ever stops being true, these files start lying and this decision needs
 * revisiting.
 */
final class ApproveSyllabus
{
    public function __construct(
        private readonly MemoryTree $memory,
        private readonly CourseTransitions $transitions,
    ) {}

    /**
     * Pre: the course is open, stands at `syllabus_draft`, and `$syllabus` is
     *      one of its own unapproved generations.
     * Post: N lesson rows with lesson 1 `available` and the rest `locked`, one
     *       GUIDANCE.md per lesson, `approved_at` stamped, course `active`.
     *
     * @throws CourseNotOpen|SyllabusNotDraftable
     */
    public function __invoke(Course $course, CourseSyllabus $syllabus): Course
    {
        if (! $course->isOpen()) {
            throw CourseNotOpen::for($course);
        }

        if ($syllabus->course_id !== $course->id) {
            throw SyllabusNotDraftable::because($course, "syllabus [{$syllabus->id}] belongs to another course.");
        }

        if ($course->approvedSyllabus()->exists()) {
            throw SyllabusNotDraftable::because($course, 'its syllabus is already approved.');
        }

        if ($course->status !== CourseStatus::SyllabusDraft) {
            throw SyllabusNotDraftable::because($course, "it stands at [{$course->status->value}], not [syllabus_draft].");
        }

        // Checked before the transaction opens rather than discovered halfway
        // through it: a syllabus whose exam draws on concepts no lesson
        // teaches must never become a course (spec 4.5.2).
        SyllabusGenerator::assertCoherent($syllabus->syllabus_json);

        DB::transaction(function () use ($course, $syllabus): void {
            foreach ($syllabus->syllabus_json['lessons'] as $lesson) {
                $position = (int) $lesson['position'];

                $course->lessons()->create([
                    'position' => $position,
                    'status' => LessonStatus::forPosition($position),
                    'guidance_path' => LessonGuidance::pathFor($course, $lesson),
                ]);

                $this->memory->put(
                    $course,
                    LessonGuidance::pathFor($course, $lesson),
                    GuidanceDocument::render($syllabus->syllabus_json, $lesson),
                );
            }

            $syllabus->forceFill(['approved_at' => now()])->save();

            $this->transitions->to($course, CourseStatus::Active);
        });

        return $course->refresh();
    }
}
