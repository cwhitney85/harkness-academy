<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Admin\Removals;
use App\Models\AdminAction;
use App\Models\Course;
use App\Models\Module;
use App\Models\Removal;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 16.5 -- Admin → Catalog's module and subtopic tree, Remove and
 * Restore through it, and retitling a subtopic.
 */
class CatalogTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Colin']);
    }

    #[Test]
    public function the_tree_shows_every_subtopic_with_its_courses_unlisted_ones_marked(): void
    {
        $this->course('algebra');
        $this->course('algebra');
        $this->course('knot-theory');

        $response = $this->actingAs($this->admin)->get(route('admin.catalog.index'));

        $response->assertInertia(fn (Assert $page) => $page->component('admin/catalog/index'));
        $math = collect(collect($response->inertiaProps('modules'))->firstWhere('title', 'Math')['subtopics'])->keyBy('slug');
        $this->assertSame([2, true], [$math['algebra']['courses'], $math['algebra']['listed']]);
        $this->assertSame([1, false], [$math['knot-theory']['courses'], $math['knot-theory']['listed']]);
    }

    #[Test]
    public function a_subtopic_removed_from_the_tree_is_in_removed_and_restoring_it_brings_it_back(): void
    {
        $course = $this->course('algebra');
        $algebra = $course->subtopic;

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.subtopics.remove', $algebra), ['reason' => 'misfiled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Course::query()->find($course->id));
        $this->assertFalse($this->inTree('algebra'));

        $this->actingAs($this->admin)
            ->get(route('admin.catalog.removed'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalog/removed')
                ->has('removals', 1)
                ->where('removals.0.id', Removal::query()->sole()->id)
                ->where('removals.0.kind', 'subtopic')
                ->where('removals.0.title', 'Algebra')
                ->where('removals.0.context', 'Math')
                ->where('removals.0.by', 'Colin')
                ->where('removals.0.reason', 'misfiled')
                ->where('removals.0.courses', 1));

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.restore', Removal::query()->sole()), ['reason' => 'filed right after all'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Course::query()->find($course->id));
        $this->assertTrue($this->inTree('algebra'));
        $this->actingAs($this->admin)
            ->get(route('admin.catalog.removed'))
            ->assertInertia(fn (Assert $page) => $page->has('removals', 0));

        $this->assertSame(
            [[AdminActionType::Removed, 'misfiled'], [AdminActionType::Restored, 'filed right after all']],
            AdminAction::query()->orderBy('id')->get()->map(fn (AdminAction $row): array => [$row->action, $row->reason])->all(),
        );
    }

    #[Test]
    public function a_module_removed_from_the_tree_takes_its_subtopics_and_counts_their_courses(): void
    {
        $this->course('algebra');
        $this->course('knot-theory');
        $math = Module::query()->where('slug', 'math')->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.modules.remove', $math), ['reason' => 'retiring it'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.catalog.index'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'modules',
                fn ($modules): bool => ! collect($modules)->pluck('title')->contains('Math'),
            ));

        $this->actingAs($this->admin)
            ->get(route('admin.catalog.removed'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('removals', 1)
                ->where('removals.0.kind', 'module')
                ->where('removals.0.context', null)
                ->where('removals.0.courses', 2));
    }

    #[Test]
    public function remove_and_restore_each_need_a_reason(): void
    {
        $algebra = Subtopic::query()->where('slug', 'algebra')->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.subtopics.remove', $algebra), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertTrue($this->inTree('algebra'));

        $removal = app(Removals::class)->remove($this->admin, $algebra, 'misfiled');

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.restore', $removal))
            ->assertSessionHasErrors('reason');

        $this->assertFalse($this->inTree('algebra'));
        $this->assertSame(1, AdminAction::query()->count());
    }

    #[Test]
    public function a_refused_restore_names_what_stands_in_the_way_and_changes_nothing(): void
    {
        $algebra = Subtopic::query()->where('slug', 'algebra')->sole();
        $removals = app(Removals::class);
        $own = $removals->remove($this->admin, $algebra, 'misfiled');
        $removals->remove($this->admin, $algebra->module, 'retiring it');

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.restore', $own), ['reason' => 'want it back'])
            ->assertSessionHasErrors(['restore' => 'Its module, Math, is removed. Restore that first.']);

        $this->assertNull(Subtopic::query()->find($algebra->id));
        $this->assertSame(AdminActionType::RestoreRefused, AdminAction::query()->latest('id')->first()->action);
    }

    #[Test]
    public function a_removal_already_restored_cannot_be_restored_again(): void
    {
        $removals = app(Removals::class);
        $removal = $removals->remove($this->admin, Subtopic::query()->where('slug', 'algebra')->sole(), 'misfiled');
        $removals->restore($this->admin, $removal, 'back');

        $this->actingAs($this->admin)
            ->post(route('admin.catalog.restore', $removal), ['reason' => 'again'])
            ->assertNotFound();
    }

    #[Test]
    public function a_removed_course_is_listed_by_its_level_attempt_and_student(): void
    {
        $course = $this->course('algebra', User::factory()->create(['name' => 'Ada']));
        app(Removals::class)->remove($this->admin, $course, 'test data');

        $this->actingAs($this->admin)
            ->get(route('admin.catalog.removed'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('removals.0.kind', 'course')
                ->where('removals.0.title', 'Algebra')
                ->where('removals.0.context', '101 attempt 1, Ada')
                ->where('removals.0.courses', 1));
    }

    /**
     * The demo sweep is the one hard delete (D68): a removal of a demo's
     * course outlives the course, and hides nothing any more.
     */
    #[Test]
    public function a_removal_whose_course_the_demo_sweep_deleted_is_not_listed(): void
    {
        $demo = User::factory()->create(['plan' => 'demo', 'created_at' => now()->subYear()]);
        $removals = app(Removals::class);

        $removals->remove($this->admin, $this->course('algebra', $demo), 'demo noise');
        $kept = $removals->remove($this->admin, $this->course('geometry'), 'test data');

        $this->artisan('demo:sweep')->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(route('admin.catalog.removed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('removals', 1)
                ->where('removals.0.id', $kept->id));
    }

    #[Test]
    public function retitling_a_subtopic_keeps_its_slug_and_its_courses_paths_and_is_logged(): void
    {
        $course = $this->course('algebra');
        $algebra = $course->subtopic;

        $this->actingAs($this->admin)
            ->put(route('admin.catalog.retitle', $algebra), ['title' => 'Elementary Algebra'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['Elementary Algebra', 'algebra'], array_values($algebra->fresh()->only('title', 'slug')));
        $this->assertSame($course->materialsPrefix(), $course->fresh()->materialsPrefix());

        $logged = AdminAction::query()->sole();
        $this->assertSame(AdminActionType::SubtopicRetitled, $logged->action);
        $this->assertEquals(['title' => ['was' => 'Algebra', 'now' => 'Elementary Algebra']], $logged->payload);

        $this->actingAs($this->admin)
            ->put(route('admin.catalog.retitle', $algebra), ['title' => ' '])
            ->assertSessionHasErrors('title');
    }

    private function course(string $slug, ?User $student = null): Course
    {
        return Course::factory()->for($student ?? User::factory()->create(), 'student')->create([
            'module_slug' => 'math',
            'subtopic_slug' => $slug,
            'subtopic_title' => $slug === 'algebra' ? 'Algebra' : $slug,
        ]);
    }

    private function inTree(string $slug): bool
    {
        return collect($this->actingAs($this->admin)->get(route('admin.catalog.index'))->inertiaProps('modules'))
            ->pluck('subtopics')->flatten(1)->pluck('slug')->contains($slug);
    }
}
