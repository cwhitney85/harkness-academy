<?php

namespace Tests\Feature\Admin;

use App\Admin\Removals;
use App\Ai\Tier;
use App\Models\AdminAction;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Subtopic;
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

        // One module per write: the routes run in one database, and slugs are unique.
        $module = fn (string $slug) => Module::query()->create(['slug' => $slug, 'title' => $slug, 'position' => 0]);
        $subtopic = fn (string $slug) => Subtopic::findOrCreateFor($module($slug), 'algebra', 'Algebra');

        return [
            'admin.catalog.retitle' => function (self $test) use ($subtopic) {
                $algebra = $subtopic('retitled');

                return fn () => $test->put("/admin/catalog/subtopics/{$algebra->id}", ['title' => 'Elementary Algebra']);
            },
            'admin.catalog.modules.remove' => function (self $test) use ($module) {
                $removed = $module('removed');

                return fn () => $test->post("/admin/catalog/modules/{$removed->id}/remove", ['reason' => 'A reason']);
            },
            'admin.catalog.subtopics.remove' => function (self $test) use ($subtopic) {
                $algebra = $subtopic('subtopic-removed');

                return fn () => $test->post("/admin/catalog/subtopics/{$algebra->id}/remove", ['reason' => 'A reason']);
            },
            'admin.catalog.restore' => function (self $test) use ($subtopic) {
                $removal = app(Removals::class)->remove(User::factory()->admin()->create(), $subtopic('restored'), 'A reason');

                return fn () => $test->post("/admin/catalog/removals/{$removal->id}/restore", ['reason' => 'A reason']);
            },
            'admin.catalog.promote' => function (self $test) {
                $knots = Subtopic::findOrCreateFor(Module::query()->create(['slug' => 'math', 'title' => 'Math', 'position' => 0]), 'knot-theory', 'Knot Theory');

                return fn () => $test->post("/admin/catalog/subtopics/{$knots->id}/promote");
            },
            'admin.models.update' => fn (self $test) => fn () => $test->put(
                '/admin/models/'.Tier::Cheap->primary()['id'],
                ['input_per_mtok' => 2, 'output_per_mtok' => 10, 'cache_read_per_mtok' => 0.2, 'cache_write_per_mtok' => 2.5],
            ),
            'admin.prompts.update' => fn (self $test) => fn () => $test->put(
                '/admin/prompts/explainer.select',
                ['body' => Prompt::query()->with('activeVersion')->where('key', 'explainer.select')->sole()->activeVersion->body, 'tier' => 'cheap'],
            ),
            'admin.prompts.activate' => function (self $test) {
                $prompt = Prompt::query()->with('activeVersion')->where('key', 'explainer.select')->sole();

                $test->put('/admin/prompts/explainer.select', [
                    'body' => $prompt->activeVersion->body."\nOne more line.\n",
                    'tier' => 'cheap',
                ]);

                return fn () => $test->post('/admin/prompts/explainer.select/versions/1');
            },
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
