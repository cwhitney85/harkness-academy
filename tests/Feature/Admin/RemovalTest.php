<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Admin\Exceptions\RestoreRefused;
use App\Admin\Removals;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\CreateCourse;
use App\Courses\SkillLevel;
use App\Lessons\Checkpoints\CheckpointGenerator;
use App\Lessons\Checkpoints\WriteCheckpoint;
use App\Lessons\Pipeline\SummarizeLesson;
use App\Memory\MemoryTree;
use App\Models\AdminAction;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Lesson;
use App\Models\LessonPipelineStep;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 16.2.2–16.2.4: what a removal hides, what a restore brings back, and
 * that hidden means hidden.
 */
class RemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function removing_a_course_hides_it_and_its_own_documents_and_leaves_the_subtopics_knowledge(): void
    {
        $course = $this->courseWithDocuments();

        $this->removals()->remove($this->admin, $course, 'test data');

        $this->assertNull(Course::query()->find($course->id));
        $this->assertNull($this->tree()->get($course->student, CourseCriteria::pathFor($course)));
        $this->assertSame([$course->workingKnowledgePath()], $this->tree()->list($course->student));
        $this->assertNotNull(Subtopic::query()->find($course->subtopic_id));
    }

    #[Test]
    public function removing_a_subtopic_hides_every_students_courses_their_documents_and_its_knowledge(): void
    {
        $mine = $this->courseWithDocuments();
        $theirs = $this->courseWithDocuments();
        $elsewhere = $this->courseWithDocuments(subtopic: 'geometry');

        $this->removals()->remove($this->admin, $mine->subtopic, 'misfiled');

        foreach ([$mine, $theirs] as $course) {
            $this->assertNull(Course::query()->find($course->id));
            $this->assertSame([], $this->tree()->list($course->student));
        }

        $this->assertNull(Subtopic::query()->find($mine->subtopic_id));
        $this->assertNotNull(Course::query()->find($elsewhere->id));
        $this->assertCount(2, $this->tree()->list($elsewhere->student));
    }

    #[Test]
    public function removing_a_module_hides_its_subtopics_their_courses_and_everything_they_wrote(): void
    {
        $algebra = $this->courseWithDocuments();
        $geometry = $this->courseWithDocuments(subtopic: 'geometry');
        $spanish = $this->courseWithDocuments(module: 'language', subtopic: 'spanish');

        $this->removals()->remove($this->admin, $algebra->subtopic->module, 'retired');

        $this->assertNull(Module::query()->find($algebra->subtopic->module_id));
        $this->assertSame(0, Subtopic::query()->whereIn('id', [$algebra->subtopic_id, $geometry->subtopic_id])->count());
        $this->assertSame(0, Course::query()->whereIn('id', [$algebra->id, $geometry->id])->count());
        $this->assertSame([], $this->tree()->list($algebra->student));
        $this->assertSame([], $this->tree()->list($geometry->student));

        $this->assertNotNull(Course::query()->find($spanish->id));
        $this->assertCount(2, $this->tree()->list($spanish->student));
    }

    #[Test]
    public function restoring_a_parent_leaves_a_child_with_its_own_removal(): void
    {
        $a = $this->courseWithDocuments();

        $this->removals()->remove($this->admin, $a, 'A on its own');
        $subtopic = $this->removals()->remove($this->admin, $a->subtopic, 'the subtopic');

        $this->removals()->restore($this->admin, $subtopic, 'back');

        $this->assertNotNull(Subtopic::query()->find($a->subtopic_id));
        $this->assertNull(Course::query()->find($a->id));
        $this->assertNull($this->tree()->get($a->student, CourseCriteria::pathFor($a)));
        $this->assertSame([$a->workingKnowledgePath()], $this->tree()->list($a->student));
    }

    #[Test]
    public function a_restore_brings_back_exactly_what_its_removal_hid_and_both_are_logged(): void
    {
        $course = $this->courseWithDocuments();
        $before = $this->tree()->list($course->student);

        $removal = $this->removals()->remove($this->admin, $course->subtopic, 'misfiled');
        $this->removals()->restore($this->admin, $removal, 'not misfiled after all');

        $this->assertNotNull(Course::query()->find($course->id));
        $this->assertSame($before, $this->tree()->list($course->student));

        $this->assertSame(
            [AdminActionType::Removed, AdminActionType::Restored],
            AdminAction::query()->orderBy('id')->pluck('action')->all(),
        );
        $this->assertTrue(AdminAction::query()->where('reason', 'misfiled')->sole()->subject->is($course->subtopic));
    }

    #[Test]
    public function a_removed_courses_screens_are_a_404_to_its_student(): void
    {
        $course = $this->courseWithDocuments(status: CourseStatus::Active);
        CourseSyllabus::factory()->for($course)->approved()->create();
        $lesson = Lesson::factory()->for($course)->create();

        $this->actingAs($course->student)->get(route('lessons.show', $lesson))->assertOk();

        $this->removals()->remove($this->admin, $course, 'test data');

        $this->actingAs($course->student)->get(route('lessons.show', $lesson))->assertNotFound();
        $this->actingAs($course->student)->get(route('courses.syllabus.show', $course))->assertNotFound();
        $this->actingAs($course->student)->get(route('courses.final-exam.show', $course))->assertNotFound();
    }

    #[Test]
    public function a_removed_document_is_not_read_by_version_either(): void
    {
        $course = $this->courseWithDocuments();
        $criteria = CourseCriteria::pathFor($course);

        $this->removals()->remove($this->admin, $course, 'test data');

        $this->assertNull($this->tree()->document($course->student, $criteria));
    }

    #[Test]
    public function the_picker_neither_offers_nor_accepts_a_removed_subtopic(): void
    {
        $course = $this->courseWithDocuments();
        $subtopic = $course->subtopic;
        $subtopic->forceFill(['listed' => true])->save();
        $student = User::factory()->create();
        $offered = fn (): bool => collect($this->actingAs($student)->get(route('courses.create'))->inertiaProps('modules'))
            ->flatMap(fn (array $module) => $module['subtopics'])
            ->contains(fn (array $offered) => $offered['id'] === $subtopic->id);

        $this->assertTrue($offered());

        $this->removals()->remove($this->admin, $subtopic, 'retired');

        $this->assertFalse($offered());

        $this->actingAs($student)
            ->post(route('courses.store'), ['source' => 'catalog', 'subtopic_id' => $subtopic->id, 'level' => 101])
            ->assertSessionHasErrors('subtopic_id');
    }

    #[Test]
    public function a_student_whose_open_course_was_removed_starts_afresh_as_the_next_attempt(): void
    {
        $removed = $this->courseWithDocuments();

        $this->removals()->remove($this->admin, $removed, 'test data');

        $fresh = app(CreateCourse::class)->fromCatalog($removed->student, $removed->subtopic, SkillLevel::Introductory);

        $this->assertSame(2, $fresh->attempt);
        $this->assertTrue($fresh->isOpen());
        $this->assertSame('# Solid: something', $this->tree()->get($fresh->student, $fresh->workingKnowledgePath()));
    }

    #[Test]
    public function a_course_cannot_be_restored_while_another_holds_its_subtopic_and_both_outcomes_are_logged(): void
    {
        $first = $this->courseWithDocuments();
        $removal = $this->removals()->remove($this->admin, $first, 'test data');
        $second = app(CreateCourse::class)->fromCatalog($first->student, $first->subtopic, SkillLevel::Introductory);

        try {
            $this->removals()->restore($this->admin, $removal, 'wanted back');
            $this->fail('The restore should have been refused.');
        } catch (RestoreRefused $e) {
            $this->assertStringContainsString("Course #{$second->id}", $e->getMessage());
            $this->assertStringContainsString('attempt 2', $e->getMessage());
        }

        $this->assertNull(Course::query()->find($first->id));

        app(CourseTransitions::class)->withdraw($second);
        $this->removals()->restore($this->admin, $removal, 'wanted back');

        $this->assertTrue(Course::query()->findOrFail($first->id)->isOpen());
        $this->assertSame(
            [AdminActionType::Removed, AdminActionType::RestoreRefused, AdminActionType::Restored],
            AdminAction::query()->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame($removal->id, AdminAction::query()->where('action', AdminActionType::RestoreRefused)->sole()->payload['removal_id']);
    }

    #[Test]
    public function nothing_is_restored_under_a_parent_that_is_still_removed(): void
    {
        $course = $this->courseWithDocuments();
        $alone = $this->removals()->remove($this->admin, $course, 'on its own');
        $this->removals()->remove($this->admin, $course->subtopic, 'the subtopic');

        $this->expectException(RestoreRefused::class);
        $this->expectExceptionMessage('Its subtopic, Algebra, is removed.');

        try {
            $this->removals()->restore($this->admin, $alone, 'wanted back');
        } finally {
            $this->assertNull(Course::query()->find($course->id));
        }
    }

    #[Test]
    public function a_lessons_queued_work_for_a_removed_course_exits_quietly(): void
    {
        $course = $this->courseWithDocuments(status: CourseStatus::Active);
        CourseSyllabus::factory()->for($course)->approved()->create();
        $lesson = Lesson::factory()->for($course)->create();

        $this->removals()->remove($this->admin, $course, 'test data');

        (new WriteCheckpoint($lesson->id, 1))->handle(app(CheckpointGenerator::class));
        (new WriteCheckpoint($lesson->id, 1))->failed(null);
        app()->call([new SummarizeLesson($lesson->id), 'handle']);

        $this->assertSame(0, UsageEvent::query()->count());
        $this->assertSame(0, LessonPipelineStep::query()->count());
    }

    private function courseWithDocuments(string $module = 'math', string $subtopic = 'algebra', CourseStatus $status = CourseStatus::GoalsInterview): Course
    {
        $course = Course::factory()->status($status)->create([
            'module_slug' => $module,
            'subtopic_slug' => $subtopic,
            'subtopic_title' => ucfirst($subtopic),
        ]);

        $this->tree()->put($course, $course->workingKnowledgePath(), '# Solid: something');
        $this->tree()->put($course, CourseCriteria::pathFor($course), '## Goals');

        return $course;
    }

    private function removals(): Removals
    {
        return app(Removals::class);
    }

    private function tree(): MemoryTree
    {
        return app(MemoryTree::class);
    }
}
