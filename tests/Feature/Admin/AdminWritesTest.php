<?php

namespace Tests\Feature\Admin;

use App\Ai\Tier;
use App\Models\AdminAction;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 13.2.2 -- every non-GET `/admin*` route writes exactly one
 * `admin_actions` row.
 *
 * A route with no entry in `writes()` fails here, so each package that adds
 * an admin write adds the request that exercises it.
 */
class AdminWritesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_admin_write_records_exactly_one_action(): void
    {
        $writes = $this->writes();
        $routes = array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => str_starts_with($route->uri(), 'admin')
                && array_diff($route->methods(), ['GET', 'HEAD']) !== [],
        );

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $name = $route->getName();

            $this->assertArrayHasKey($name, $writes, "[{$name}] writes as an admin and has no request here.");

            $this->flushSession();
            $admin = User::factory()->admin()->create();
            $student = User::factory()->create();

            $write = $writes[$name]($this->actingAs($admin), $student);

            $before = AdminAction::query()->count();
            $write();

            $this->assertSame($before + 1, AdminAction::query()->count(), "[{$name}] did not write exactly one row.");
        }
    }

    /**
     * Each admin write, by route name: set up what it needs, and return the
     * request itself, which is all the count is taken around.
     *
     * @return array<string, Closure(self, User): Closure(): mixed>
     */
    private function writes(): array
    {
        $viewing = fn (self $test, User $student) => $test->post("/admin/users/{$student->id}/view-as");

        return [
            'admin.models.update' => fn (self $test) => fn () => $test->put(
                '/admin/models/'.Tier::Cheap->primary()['id'],
                ['input_per_mtok' => 2, 'output_per_mtok' => 10, 'cache_read_per_mtok' => 0.2, 'cache_write_per_mtok' => 2.5],
            ),
            'admin.users.view-as' => fn (self $test, User $student) => fn () => $viewing($test, $student),
            'admin.view-as.step-up' => function (self $test, User $student) use ($viewing) {
                $viewing($test, $student);

                return fn () => $test->post('/admin/view-as/step-up', ['reason' => 'A reason']);
            },
            'admin.view-as.exit' => function (self $test, User $student) use ($viewing) {
                $viewing($test, $student);

                return fn () => $test->delete('/admin/view-as');
            },
        ];
    }
}
