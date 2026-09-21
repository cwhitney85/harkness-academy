<?php

namespace Tests\Feature\Ai;

use App\Ai\Prompts\Exceptions\PromptSyncFailed;
use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptSource;
use App\Ai\Prompts\PromptSync;
use App\Ai\Prompts\PromptSyncOutcome;
use App\Ai\Tier;
use App\Models\Prompt;
use App\Models\PromptVersion;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 15.1.1-15.1.4 (ledger D54) -- files are the reviewed baseline, the
 * database is what runs, and a deploy never overrides a live edit.
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
    public function the_first_sync_registers_the_contract_and_activates_version_one(): void
    {
        $this->write('Explain {{ topic }}.');

        $this->assertSame(PromptSyncOutcome::Activated, $this->sync());

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

        $this->assertSame(PromptSyncOutcome::Unchanged, $this->sync());
        $this->assertSame(1, $this->versions()->count());
    }

    #[Test]
    public function one_file_change_makes_exactly_one_version(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->write('Explain {{ topic }} in two sentences.');

        $this->assertSame(PromptSyncOutcome::Activated, $this->sync());
        $this->assertSame(PromptSyncOutcome::Unchanged, $this->sync());

        $this->assertSame(2, $this->versions()->count());
        $this->assertSame(2, $this->prompt()->activeVersion->number);
        $this->assertStringContainsString('two sentences', $this->prompt()->activeVersion->body);
    }

    #[Test]
    public function a_file_version_takes_over_from_a_file_version(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $this->write('Explain {{ topic }} slowly.');
        $this->sync();

        $this->assertFalse($this->prompt()->hasNewerFileVersion());
        $this->assertStringContainsString('slowly', $this->prompt()->activeVersion->body);
    }

    #[Test]
    public function a_file_version_waits_behind_a_live_edit_and_is_flagged(): void
    {
        $this->write('Explain {{ topic }}.');
        $this->sync();

        $edit = $this->edit('Explain {{ topic }} as a limerick.');

        $this->write('Explain {{ topic }} slowly.');

        $this->assertSame(PromptSyncOutcome::Waiting, $this->sync());

        $prompt = $this->prompt();

        $this->assertSame($edit->id, $prompt->active_version_id, 'A deploy must not override a live edit.');
        $this->assertSame(3, $this->versions()->count());
        $this->assertTrue($prompt->hasNewerFileVersion());
    }

    #[Test]
    public function sync_fails_when_the_file_drops_a_var_the_live_edit_uses(): void
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
