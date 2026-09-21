<?php

namespace Tests\Feature\Ai;

use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptSource;
use App\Ai\Prompts\PromptSync;
use App\Ai\Prompts\PromptSyncOutcome;
use App\Ai\Tier;
use App\Models\Prompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 15.2.5 -- `prompts:pull` writes active versions back to `prompts/`
 * byte-exact, which is what keeps the repo, the test fixtures and git history
 * holding what is actually running (ledger D65).
 *
 * The command writes files, so it runs against a copy of the real tree rather
 * than the tree itself.
 */
class PromptPullTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/harkness-pull-'.bin2hex(random_bytes(6));

        foreach (glob(base_path('prompts').'/*/*.md') ?: [] as $source) {
            $path = $this->directory.'/'.basename(dirname($source)).'/'.basename($source);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0o755, true);
            }

            copy($source, $path);
        }

        $this->app->instance(PromptFiles::class, new PromptFiles($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*/*.md') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            rmdir($dir);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * The byte-exactness itself: with nothing edited, every file the command
     * writes is the file already in git, down to the byte.
     */
    #[Test]
    public function pulling_an_untouched_registry_rewrites_nothing(): void
    {
        $this->artisan('prompts:pull')
            ->expectsOutputToContain('Every prompt file already holds what is running.')
            ->assertSuccessful();

        foreach (glob(base_path('prompts').'/*/*.md') ?: [] as $source) {
            $copy = $this->directory.'/'.basename(dirname($source)).'/'.basename($source);

            $this->assertFileEquals($source, $copy, basename($source).' was rewritten by a pull that changed nothing.');
        }
    }

    #[Test]
    public function pulling_writes_an_edit_back_into_a_file_the_loader_reads_as_that_version(): void
    {
        $prompt = $this->prompt('explainer.select');

        $edit = $prompt->versions()->create([
            'number' => 2,
            'body' => $prompt->activeVersion->body."\nIn one sentence.\n",
            'tier' => Tier::Mid,
            'source' => PromptSource::Admin,
        ]);

        $prompt->activate($edit);

        $this->artisan('prompts:pull')->assertSuccessful();

        $definition = (new PromptFiles($this->directory))->get('explainer.select');

        $this->assertSame($edit->body, $definition->body);
        $this->assertSame(Tier::Mid, $definition->tier);
        $this->assertSame($prompt->output, $definition->outputContract());
        $this->assertSame($prompt->vars, $definition->vars);
    }

    #[Test]
    public function a_sync_after_a_pull_makes_no_version(): void
    {
        $prompt = $this->prompt('explainer.select');

        $prompt->activate($prompt->versions()->create([
            'number' => 2,
            'body' => $prompt->activeVersion->body."\nIn one sentence.\n",
            'tier' => $prompt->activeVersion->tier,
            'source' => PromptSource::Admin,
        ]));

        $this->artisan('prompts:pull')->assertSuccessful();

        $outcomes = (new PromptSync(new PromptFiles($this->directory)))->run();

        $this->assertSame(PromptSyncOutcome::Registered, $outcomes['explainer.select']);
        $this->assertSame(2, $this->prompt('explainer.select')->versions()->count());
        $this->assertSame(2, $this->prompt('explainer.select')->activeVersion->number);
    }

    private function prompt(string $key): Prompt
    {
        return Prompt::query()->with('activeVersion')->where('key', $key)->sole();
    }
}
