<?php

namespace Tests\Feature\Admin;

use App\Admin\Removals;
use App\Ai\Tier;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Spec 13.1.2, 13.1.5 -- one gate on every `/admin*` route and every admin controller, and a second factor. */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_admin_gate_is_on_every_admin_route(): void
    {
        $routes = $this->adminRoutes();

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('can:admin', $route->gatherMiddleware(), "[{$route->uri()}] has no admin gate.");
        }
    }

    #[Test]
    public function a_student_is_refused_every_admin_route(): void
    {
        $student = User::factory()->withTwoFactor()->create();

        // A bound parameter must resolve, or the route 404s before the gate
        // runs; each admin route's parameter names a key that exists here. A
        // prompt is bound by its key rather than its id (15.2.1).
        $ids = [
            'user' => $student->id,
            'model' => Tier::Cheap->primary()['id'],
            'prompt' => 'explainer.select',
            'number' => 1,
            'module' => ($math = Module::query()->create(['slug' => 'math', 'title' => 'Math', 'position' => 0]))->id,
            'subtopic' => Subtopic::findOrCreateFor($math, 'knot-theory', 'Knot Theory')->id,
            'removal' => app(Removals::class)->remove(
                User::factory()->admin()->create(),
                Subtopic::findOrCreateFor($math, 'graph-theory', 'Graph Theory'),
                'A reason',
            )->id,
        ];

        foreach ($this->adminRoutes() as $route) {
            $method = $route->methods()[0];
            $uri = '/'.preg_replace_callback(
                '/\{([^}?]+)\??\}/',
                fn (array $m): string => (string) ($ids[$m[1]] ?? throw new \LogicException("No id for [{$m[1]}].")),
                $route->uri(),
            );

            $this->actingAs($student)->call($method, $uri)
                ->assertForbidden();
        }
    }

    #[Test]
    public function an_admin_without_a_second_factor_is_sent_to_set_one_up(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.edit'));
    }

    #[Test]
    public function an_admin_with_a_confirmed_two_factor_code_gets_in(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    #[Test]
    public function an_unconfirmed_two_factor_code_is_not_a_second_factor(): void
    {
        $admin = User::factory()->admin()->create(['two_factor_confirmed_at' => null]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.edit'));
    }

    #[Test]
    public function an_admin_with_a_passkey_gets_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('passkeys')->insert([
            'user_id' => $admin->id,
            'name' => 'Laptop',
            'credential_id' => 'credential-1',
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    /**
     * Every `/admin*` route, and every route an admin controller answers
     * wherever it is mounted -- the door test lets only admin controllers read
     * removed memory (16.3.4), which holds only while each is gated.
     *
     * @return list<Route>
     */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => $route->uri() === 'admin'
                || str_starts_with($route->uri(), 'admin/')
                || str_starts_with(ltrim($route->getControllerClass() ?? '', '\\'), 'App\\Http\\Controllers\\Admin\\'),
        ));
    }
}
