<?php

namespace Tests\Feature\Memory;

use App\Memory\MemoryTree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 3.4 -- the memory inspector prints a tree or a file.
 */
class MemoryShowCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prints_the_tree_when_no_path_is_given(): void
    {
        $user = User::factory()->create();

        foreach ([
            'math/algebra/101/COURSE_CRITERIA.md',
            'math/algebra/WORKING_KNOWLEDGE.md',
            'math/geometry/WORKING_KNOWLEDGE.md',
        ] as $path) {
            $this->tree()->put($user, $path, 'x');
        }

        $this->artisan('memory:show', ['user' => $user->email])
            ->expectsOutput('math')
            ->expectsOutput('  algebra')
            ->expectsOutput('    101')
            ->expectsOutput('      COURSE_CRITERIA.md')
            ->expectsOutput('    WORKING_KNOWLEDGE.md')
            ->expectsOutput('  geometry')
            ->expectsOutput('    WORKING_KNOWLEDGE.md')
            ->assertSuccessful();
    }

    #[Test]
    public function it_prints_one_document_when_a_path_is_given(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, 'math/algebra/WORKING_KNOWLEDGE.md', "## Solid\n- fractions");

        $this->artisan('memory:show', [
            'user' => (string) $user->id,
            'path' => 'math/algebra/WORKING_KNOWLEDGE.md',
        ])
            ->expectsOutput("## Solid\n- fractions")
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_the_document_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->artisan('memory:show', ['user' => $user->email, 'path' => 'math/none.md'])
            ->assertFailed();
    }

    #[Test]
    public function it_fails_on_an_unknown_user(): void
    {
        $this->artisan('memory:show', ['user' => 'nobody@example.com'])->assertFailed();
    }

    #[Test]
    public function it_fails_on_an_invalid_path_rather_than_reporting_it_missing(): void
    {
        $user = User::factory()->create();

        $this->artisan('memory:show', ['user' => $user->email, 'path' => '../../etc/passwd'])
            ->assertFailed();
    }

    private function tree(): MemoryTree
    {
        return app(MemoryTree::class);
    }
}
