<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\Prompts\Exceptions\PromptSyncFailed;
use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptSource;
use App\Ai\Prompts\PromptSync;
use App\Ai\Prompts\PromptSyncOutcome;
use App\Ai\Tier;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 15.1.1-15.1.4, 15.1.8, 15.1.9 (ledger D65) -- the file owns the contract, the editor
 * owns the body, and a deploy reads a file's body exactly once.
 *
 * The prompts here are written into a directory of this test's own, so a file
 * can be changed mid-test. The real `prompts/` tree is synced by
 * `PromptRegistrySeeder` and is what every other test renders.
 */
class PromptSyncTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'demo.tuneable';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/harkness-sync-'.bin2hex(random_bytes(6));

        mkdir($this->directory.'/demo', 0o755, true);
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

    #[Test]
    public function a_version_cannot_be_updated(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $version = $this->prompt()->activeVersion;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('a version is immutable');

        $version->body = 'Explain it differently.';
        $version->save();
    }

    #[Test]
    public function the_first_sync_registers_the_contract_and_seeds_version_one(): void
    {
        $this->write('Explain {{ topic }}.');

        $this->assertSame(PromptSyncOutcome::Seeded, $this->sync());

        $prompt = $this->prompt();

        $this->assertSame('markdown', $prompt->output);
        $this->assertSame(['topic'], $prompt->vars);
        $this->assertSame(1, $prompt->activeVersion->number);
        $this->assertSame(PromptSource::File, $prompt->activeVersion->source);
        $this->assertSame(Tier::Mid, $prompt->activeVersion->tier);
        $this->assertNull($prompt->activeVersion->author_id);
    }

    #[Test]
    public function syncing_twice_makes_no_second_version(): void
    {
        $this->write('Explain {{ topic }}.');

        $this->sync();

        $this->assertSame(PromptSyncOutcome::Registered, $this->sync());
        $this->assertSame(1, $this->versions()->count());
    }

    #[Test]
    public function a_file_body_changed_after_version_one_never_reaches_the_registry(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->write('Explain {{ topic }} in two sentences.');

        $this->assertSame(PromptSyncOutcome::Registered, $this->sync());

        $this->assertSame(1, $this->versions()->count());
        $this->assertStringNotContainsString('two sentences', $this->prompt()->activeVersion->body);
    }

    #[Test]
    public function a_deploy_leaves_an_editor_edit_running(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $edit = $this->edit('Explain {{ topic }} as a limerick.');

        $this->write('Explain {{ topic }} slowly.');

        $this->assertSame(PromptSyncOutcome::Registered, $this->sync());

        $this->assertSame($edit->id, $this->prompt()->active_version_id, 'A deploy must not touch the body.');
        $this->assertSame(2, $this->versions()->count());
    }

    #[Test]
    public function the_contract_keeps_importing_after_version_one(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->edit('Explain {{ topic }} for {{ audience }}.');
        $this->write('Explain {{ topic }} for {{ audience }}.', ['topic', 'audience']);

        $this->sync();

        $this->assertSame(['topic', 'audience'], $this->prompt()->vars);
    }

    #[Test]
    public function sync_fails_when_the_file_drops_a_var_the_editors_body_uses(): void
    {
        $this->write('Explain {{ topic }} for {{ audience }}.', ['topic', 'audience']);
        $this->sync();

        $this->edit('Explain {{ topic }} for {{ audience }}, as a limerick.');

        $this->write('Explain {{ topic }}.');

        try {
            $this->sync();
            $this->fail('Sync should refuse a file that drops a var the active version uses.');
        } catch (PromptSyncFailed $e) {
            $this->assertStringContainsString('audience', $e->getMessage());
            $this->assertStringContainsString($this->directory.'/demo/tuneable.md', $e->getMessage());
            $this->assertStringContainsString(self::KEY, $e->getMessage());
        }

        $this->assertSame(['topic', 'audience'], $this->prompt()->vars, 'A refused sync writes nothing.');
        $this->assertSame(2, $this->versions()->count());
    }

    /**
     * The same refusal with nothing edited. Version 1 outlives the file it was
     * seeded from, so a file that drops a variable cleanly -- out of its body
     * and its `vars` together, which the loader accepts -- still strands the
     * seeded body that interpolates it.
     */
    #[Test]
    public function sync_fails_when_the_file_drops_a_var_its_own_seeded_body_uses(): void
    {
        $this->write('Explain {{ topic }} for {{ audience }}.', ['topic', 'audience']);
        $this->sync();

        $this->write('Explain {{ topic }}.', ['topic']);

        $this->expectException(PromptSyncFailed::class);
        $this->expectExceptionMessage('audience');

        $this->sync();
    }

    /**
     * Spec 15.1.8 -- accepted, because only a declared var can be edited into
     * the body, and named, because until then the model goes without it.
     */
    #[Test]
    public function the_command_names_a_var_the_file_added_that_the_running_text_does_not_use(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->write('Explain {{ topic }} for {{ audience }}.', ['topic', 'audience']);

        $this->app->instance(PromptFiles::class, new PromptFiles($this->directory));

        $this->artisan('prompts:sync')
            ->expectsOutputToContain('[demo.tuneable] declares audience')
            ->assertSuccessful();

        $this->assertSame(['topic', 'audience'], $this->prompt()->vars);
    }

    /**
     * Spec 15.1.9 -- the call runs without the new material rather than
     * stopping the feature until the body is edited (ledger D65).
     */
    #[Test]
    public function a_call_still_runs_while_the_running_text_leaves_out_a_declared_var(): void
    {
        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');

        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->write('Explain {{ topic }} for {{ audience }}.', ['topic', 'audience']);
        $this->sync();

        $sent = null;

        GatewayAgent::fake(function (string $message) use (&$sent): string {
            $sent = $message;

            return 'Explained.';
        });

        app(Gateway::class)
            ->prompt(self::KEY, ['topic' => 'tides', 'audience' => 'sailors'])
            ->feature('test')
            ->generate();

        $this->assertSame("Explain tides.\n", $sent);
        $this->assertSame($this->prompt()->active_version_id, UsageEvent::sole()->prompt_version_id);
    }

    /**
     * @param  list<string>  $vars
     */
    private function write(string $body, array $vars = ['topic']): void
    {
        $declared = $vars === [] ? '' : "vars:\n".implode('', array_map(fn (string $v): string => "  - {$v}\n", $vars));

        file_put_contents($this->directory.'/demo/tuneable.md', <<<MD
            ---
            key: demo.tuneable
            tier: mid
            output: markdown
            {$declared}---
            {$body}

            MD);
    }

    /**
     * A live edit, as the editor of 15.2 will make one: a new body, and the
     * file's contract untouched.
     */
    private function edit(string $body): PromptVersion
    {
        $prompt = $this->prompt();

        $version = $prompt->versions()->create([
            'number' => $prompt->activeVersion->number + 1,
            'body' => $body,
            'tier' => $prompt->activeVersion->tier,
            'source' => PromptSource::Admin,
        ]);

        $prompt->activate($version);

        return $version;
    }

    private function sync(): PromptSyncOutcome
    {
        return (new PromptSync(new PromptFiles($this->directory)))->run()[self::KEY];
    }

    private function prompt(): Prompt
    {
        return Prompt::query()->with('activeVersion')->where('key', self::KEY)->sole();
    }

    /**
     * @return HasMany<PromptVersion, Prompt>
     */
    private function versions()
    {
        return $this->prompt()->versions();
    }
}
