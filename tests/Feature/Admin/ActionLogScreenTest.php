<?php

namespace Tests\Feature\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Admin\Removals;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Spec 13.2.3 -- Admin → Log lists actions, filterable by actor, action and subject. */
class ActionLogScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_action_type_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['name' => 'Maria Alvarez']);

        foreach (AdminActionType::cases() as $type) {
            app(ActionLog::class)->record($admin, $type, $student, 'a reason');
        }

        $this->actingAs($admin)->get('/admin/log')->assertInertia(fn (Assert $page) => $page
            ->component('admin/log')
            ->has('actions.data', count(AdminActionType::cases()))
            ->where('actions.data.0.subject.name', 'Maria Alvarez')
            ->where('actions.data.0.actor', $admin->name)
            ->where('options.actions', array_column(AdminActionType::cases(), 'value')));
    }

    /**
     * A row names what it was about after that thing is removed or deleted:
     * the log is the record of it.
     */
    #[Test]
    public function a_removed_or_deleted_subject_and_a_deleted_admin_keep_their_names(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Former Admin']);
        $student = User::factory()->create(['name' => 'Maria Alvarez']);
        $course = Course::factory()->for($student, 'student')->create(['subtopic_title' => 'Algebra']);
        $log = app(ActionLog::class);

        $log->record($admin, AdminActionType::PlanChanged, $student);
        app(Removals::class)->remove($admin, $course, 'test data');

        $student->delete();
        $admin->delete();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/log')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('actions.data.0.subject.name', 'Algebra')
                ->where('actions.data.0.actor', 'Former Admin')
                ->where('actions.data.1.subject.name', 'Maria Alvarez')
                ->where('options.actors.0.name', 'Former Admin'));
    }

    #[Test]
    public function a_subject_the_demo_sweep_deleted_is_labelled_a_deleted_demo(): void
    {
        $admin = User::factory()->admin()->create();
        $demo = User::factory()->create(['plan' => 'demo', 'created_at' => now()->subYear()]);

        app(ActionLog::class)->record($admin, AdminActionType::ViewAsStarted, $demo);

        $this->artisan('demo:sweep')->assertSuccessful();

        $this->actingAs($admin)->get('/admin/log')
            ->assertInertia(fn (Assert $page) => $page->where('actions.data.0.subject.name', "deleted demo #{$demo->id}"));
    }

    #[Test]
    public function the_log_filters_by_actor_action_and_subject(): void
    {
        $colin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $student = User::factory()->create();
        $log = app(ActionLog::class);

        $log->record($colin, AdminActionType::ViewAsStarted, $student);
        $log->record($colin, AdminActionType::ViewAsExited, $student);
        $log->record($other, AdminActionType::ViewAsStarted);

        $this->actingAs($colin)->get("/admin/log?actor={$other->id}")
            ->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));

        $this->actingAs($colin)->get('/admin/log?action=view_as.started')
            ->assertInertia(fn (Assert $page) => $page->has('actions.data', 2));

        $this->actingAs($colin)->get('/admin/log?subject='.urlencode(User::class))
            ->assertInertia(fn (Assert $page) => $page
                ->has('actions.data', 2)
                ->where('options.subjects', [User::class]));
    }
}
