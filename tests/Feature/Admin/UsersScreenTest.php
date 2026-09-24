<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Admin\Removals;
use App\Memory\MemoryTree;
use App\Models\AdminAction;
use App\Models\Course;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 17.1 -- Admin → Users: every account with what it has cost, one
 * account's page, and changing its plan.
 */
class UsersScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_list_counts_courses_and_cost_as_sql_does_leaving_out_act_as(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $ada = User::factory()->create(['name' => 'Ada']);
        $bo = User::factory()->create(['name' => 'Bo']);

        $algebra = Course::factory()->for($ada, 'student')->create();
        $removed = Course::factory()->for($ada, 'student')->create(['subtopic_slug' => 'geometry', 'subtopic_title' => 'Geometry']);
        app(Removals::class)->remove($admin, $removed, 'test data');

        UsageEvent::factory()->for($ada)->create(['course_id' => $algebra->id, 'cost_usd' => 0.25]);
        UsageEvent::factory()->for($ada)->create(['course_id' => $removed->id, 'cost_usd' => 0.5]);
        UsageEvent::factory()->for($ada)->actingAs($admin->id)->create(['cost_usd' => 4]);

        $sql = collect(DB::select(<<<'SQL'
            select u.name,
                   (select count(*) from courses c where c.user_id = u.id) as courses,
                   (select coalesce(sum(cost_usd), 0) from usage_events e where e.user_id = u.id and e.acting_user_id is null) as cost
            from users u order by u.name
        SQL));

        $this->actingAs($admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 3)
            // Loosely: JSON carries a whole-dollar cost as an int.
            ->where('users.data', fn ($users) => collect($users)->map(fn ($user) => [$user['name'], $user['courses'], $user['cost']])->all()
                == $sql->map(fn ($row) => [$row->name, (int) $row->courses, (float) $row->cost])->all())
            ->where('users.data.0.courses', 2)
            ->where('users.data.0.cost', 0.75)
            ->where('users.data.2.cost', 0));
    }

    #[Test]
    public function the_list_is_searched_by_name_or_email_and_filtered_by_plan(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        User::factory()->create(['name' => 'Bo', 'email' => 'bo@ANALYTICAL.test']);
        User::factory()->create(['name' => 'Guest'])->forceFill(['plan' => 'demo'])->save();

        $names = fn (string $query) => $this->actingAs($admin)->get("/admin?{$query}")->inertiaProps('users.data');

        $this->assertSame(['Ada Lovelace'], array_column($names('search=lovelace'), 'name'));
        $this->assertSame(['Bo'], array_column($names('search=analytical'), 'name'));
        $this->assertSame(['Guest'], array_column($names('plan=demo'), 'name'));
        $this->actingAs($admin)->get('/admin?plan=gold')->assertSessionHasErrors('plan');
    }

    #[Test]
    public function an_account_the_student_deleted_is_listed_marked_and_keeps_its_page(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $gone = User::factory()->create(['name' => 'Gone']);
        $gone->delete();

        $this->actingAs($admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.name', 'Gone')
            ->where('users.data.1.deleted', true));

        $this->actingAs($admin)->get("/admin/users/{$gone->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.deleted', now()->format('j M Y')));
    }

    #[Test]
    public function the_page_shows_courses_removed_ones_marked_and_cost_by_feature(): void
    {
        $admin = User::factory()->admin()->create();
        $ada = User::factory()->create();
        $algebra = Course::factory()->for($ada, 'student')->create();
        $removed = Course::factory()->for($ada, 'student')->create(['subtopic_slug' => 'geometry', 'subtopic_title' => 'Geometry']);
        app(Removals::class)->remove($admin, $removed, 'test data');

        UsageEvent::factory()->for($ada)->create(['course_id' => $algebra->id, 'feature' => 'lesson.section', 'cost_usd' => 0.25]);
        UsageEvent::factory()->for($ada)->create(['course_id' => $algebra->id, 'feature' => 'lesson.section', 'cost_usd' => 0.25]);
        UsageEvent::factory()->for($ada)->create(['course_id' => $removed->id, 'feature' => 'course.syllabus', 'cost_usd' => 1]);
        UsageEvent::factory()->for($ada)->actingAs($admin->id)->create(['course_id' => $algebra->id, 'feature' => 'lesson.qa', 'cost_usd' => 9]);
        UsageEvent::factory()->for($ada)->create(['feature' => 'subtopic.check', 'cost_usd' => 0.125]);

        $this->actingAs($admin)->get("/admin/users/{$ada->id}")->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('plans', ['standard', 'demo'])
            ->has('courses', 2)
            ->where('courses.0.title', 'Geometry')
            ->where('courses.0.removed', true)
            ->where('courses.0.cost', 1)
            ->where('courses.1.removed', false)
            ->where('courses.1.cost', 0.5)
            ->where('cost.total', 1.625)
            ->where('cost.unattributed', 0.125)
            ->where('cost.features', [
                ['feature' => 'course.syllabus', 'calls' => 1, 'cost' => 1],
                ['feature' => 'lesson.section', 'calls' => 2, 'cost' => 0.5],
                ['feature' => 'subtopic.check', 'calls' => 1, 'cost' => 0.125],
            ]));
    }

    #[Test]
    public function each_memory_version_names_the_course_that_wrote_it_and_any_acting_admin(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Colin']);
        $first = Course::factory()->withdrawn()->create(['subtopic_title' => 'Algebra']);
        $second = Course::factory()->for($first->student, 'student')->create(['attempt' => 2]);
        $tree = app(MemoryTree::class);

        $tree->put($first, $first->workingKnowledgePath(), 'after attempt 1');
        Context::add('acting_user_id', $admin->id);
        $tree->put($second, $second->workingKnowledgePath(), 'after attempt 2');
        Context::forget('acting_user_id');

        $this->actingAs($admin)->get("/admin/users/{$first->user_id}")->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.path', $first->workingKnowledgePath())
            ->where('documents.0.removed', false)
            ->where('documents.0.versions.0.course', 'Algebra 101, attempt 1')
            ->where('documents.0.versions.0.admin', null)
            ->where('documents.0.versions.1.course', 'Algebra 101, attempt 2')
            ->where('documents.0.versions.1.admin', 'Colin')
            ->where('documents.0.versions.1.content', 'after attempt 2'));
    }

    #[Test]
    public function changing_a_plan_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create();
        $guest->forceFill(['plan' => 'demo'])->save();

        $this->actingAs($admin)->put("/admin/users/{$guest->id}/plan", ['plan' => 'standard'])->assertRedirect();

        $this->assertSame('standard', $guest->fresh()->plan);
        $this->assertDatabaseHas(AdminAction::class, [
            'actor_id' => $admin->id,
            'action' => AdminActionType::PlanChanged->value,
            'subject_type' => User::class,
            'subject_id' => $guest->id,
        ]);
        $this->assertEquals(['was' => 'demo', 'now' => 'standard'], AdminAction::query()->sole()->payload['plan']);
    }

    #[Test]
    public function a_plan_not_in_config_or_the_one_it_has_is_refused_and_writes_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$student->id}/plan", ['plan' => 'gold'])->assertSessionHasErrors('plan');
        $this->actingAs($admin)->put("/admin/users/{$student->id}/plan", ['plan' => 'standard'])->assertSessionHasErrors('plan');

        $this->assertSame('standard', $student->fresh()->plan);
        $this->assertSame(0, AdminAction::query()->count());
    }
}
