<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Courses\SkillLevel;
use App\Models\AdminAction;
use App\Models\Course;
use App\Models\Subtopic;
use App\Models\SubtopicRefusal;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 16.1.5 and 16.1.6 -- Admin → Catalog → Demand and Refusals.
 */
class CatalogScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    #[Test]
    public function demand_lists_unlisted_student_subtopics_with_who_typed_them_and_when(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $this->course($first, 'knot-theory', '2026-09-01', withdrawn: true);
        $this->course($first, 'knot-theory', '2026-09-05', level: SkillLevel::Intermediate);
        $this->course($second, 'knot-theory', '2026-09-03');
        $this->course($second, 'graph-theory', '2026-09-04');
        Subtopic::query()->where('slug', 'graph-theory')->sole()->update(['listed' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.catalog.demand'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalog/demand')
                ->has('subtopics', 1)
                ->where('subtopics.0.title', 'knot-theory')
                ->where('subtopics.0.module', 'Math')
                ->where('subtopics.0.courses', 3)
                ->where('subtopics.0.students', 2)
                ->where('subtopics.0.first_seen', '1 Sep 2026')
                ->where('subtopics.0.last_seen', '5 Sep 2026'));
    }

    #[Test]
    public function promoting_lists_the_subtopic_under_its_new_title_and_moves_no_course(): void
    {
        $student = User::factory()->create();
        $course = $this->course($student, 'knot-theory', '2026-09-01');
        $knots = $course->subtopic;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.catalog.promote', $knots), ['title' => 'Knot Theory'])
            ->assertRedirect();

        $knots->refresh();
        $this->assertTrue($knots->listed);
        $this->assertSame('Knot Theory', $knots->title);
        $this->assertSame('knot-theory', $knots->slug);
        $this->assertSame(['math', 'knot-theory', 'knot-theory'], array_values($course->fresh()->only('module_slug', 'subtopic_slug', 'subtopic_title')));

        $logged = AdminAction::query()->sole();
        $this->assertSame(AdminActionType::SubtopicPromoted, $logged->action);
        $this->assertSame($admin->id, $logged->actor_id);
        $this->assertSame($knots->id, $logged->subject_id);
        // jsonb keeps its own key order, so equal rather than identical.
        $this->assertEquals(['title' => ['was' => 'knot-theory', 'now' => 'Knot Theory']], $logged->payload);

        $this->actingAs($student)
            ->get(route('courses.create'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'modules.0.subtopics',
                fn ($subtopics): bool => collect($subtopics)->pluck('title')->contains('Knot Theory'),
            ));
    }

    #[Test]
    public function promoting_with_no_title_keeps_the_one_the_student_typed(): void
    {
        $knots = $this->course(User::factory()->create(), 'knot-theory', '2026-09-01')->subtopic;

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.catalog.promote', $knots), ['title' => '  '])
            ->assertRedirect();

        $this->assertSame('knot-theory', $knots->fresh()->title);
        $this->assertTrue($knots->fresh()->listed);
    }

    #[Test]
    public function only_an_unlisted_student_subtopic_can_be_promoted(): void
    {
        $admin = User::factory()->admin()->create();
        $knots = $this->course(User::factory()->create(), 'knot-theory', '2026-09-01')->subtopic;

        $this->actingAs($admin)->post(route('admin.catalog.promote', $knots))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.catalog.promote', $knots))->assertNotFound();
        $this->actingAs($admin)
            ->post(route('admin.catalog.promote', Subtopic::query()->where('slug', 'algebra')->sole()))
            ->assertNotFound();

        $this->assertSame(1, AdminAction::query()->count());
    }

    #[Test]
    public function refusals_show_what_was_typed_the_verdict_the_models_reason_and_the_student(): void
    {
        $student = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.test']);
        $this->refusal($student, 'ancient history', 'wrong_module', 'history', 'It is a history subject.');
        $this->refusal($student, 'jbahjscaschjcc', 'off_platform', null, 'A random string.');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.catalog.refusals'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalog/refusals')
                ->has('refusals', 2)
                ->where('refusals.0.typed', 'jbahjscaschjcc')
                ->where('refusals.0.suggested', null)
                ->where('refusals.1.typed', 'ancient history')
                ->where('refusals.1.module', 'Language')
                ->where('refusals.1.verdict', 'wrong_module')
                ->where('refusals.1.suggested', 'History')
                ->where('refusals.1.reason', 'It is a history subject.')
                ->where('refusals.1.student', [
                    'name' => 'Ada',
                    'email' => 'ada@example.test',
                    'url' => route('admin.home'),
                ]));
    }

    #[Test]
    public function a_student_reaches_neither_view(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('admin.catalog.demand'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.catalog.refusals'))->assertForbidden();
    }

    private function course(User $student, string $slug, string $at, bool $withdrawn = false, SkillLevel $level = SkillLevel::Introductory): Course
    {
        $this->travelTo($at);

        return Course::factory()->for($student, 'student')->create([
            'module_slug' => 'math',
            'subtopic_slug' => $slug,
            'subtopic_title' => $slug,
            'skill_level' => $level,
            'withdrawn_at' => $withdrawn ? now() : null,
        ]);
    }

    private function refusal(User $student, string $typed, string $verdict, ?string $suggested, string $reason): void
    {
        SubtopicRefusal::query()->create([
            'user_id' => $student->id,
            'typed_name' => $typed,
            'module_slug' => 'language',
            'verdict' => $verdict,
            'suggested_module_slug' => $suggested,
            'reason' => $reason,
        ]);
    }
}
