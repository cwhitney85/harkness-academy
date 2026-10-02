<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Prompts\PromptSource;
use App\Ai\Tier;
use App\Models\AdminAction;
use App\Models\Prompt;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec 15.2.1-15.2.6 -- Admin → Prompts lists the 2.3.2 inventory with what it
 * has cost, and is the one place a prompt's text is written (ledger D65).
 *
 * The fixture is the real `prompts/` tree, seeded by `PromptRegistrySeeder`
 * ([T18](docs/lessons.md)), so the counts here are the inventory's own.
 */
class PromptsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_screen_renders_every_prompt_with_its_active_version_and_seven_days_of_usage(): void
    {
        $this->served('explainer.select', cost: 0.25);
        $this->served('explainer.select', cost: 0.75);
        $this->served('explainer.select', cost: 9.00, ago: 8);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/prompts')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/prompts')
                ->where('days', 7)
                ->has('prompts', 18)
                // Ordered by key, so `explainer.select` is the fourth of the
                // eighteen and `exam.final` the third.
                ->where('prompts.3.key', 'explainer.select')
                ->where('prompts.3.output', 'markdown')
                ->where('prompts.3.version', 1)
                ->where('prompts.3.source', 'file')
                ->where('prompts.3.tier', 'cheap')
                // The eight-day-old call is outside the window.
                ->where('prompts.3.calls', 2)
                ->where('prompts.3.cost', 1)
                ->where('prompts.2.calls', 0));
    }

    #[Test]
    public function one_prompt_shows_its_contract_body_and_editable_tiers(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/prompt')
                ->where('prompt.key', 'explainer.select')
                ->where('prompt.vars', ['selection', 'surrounding_chunk', 'subtopic', 'skill_level'])
                ->where('prompt.version', 1)
                ->where('tiers', ['cheap', 'mid', 'top'])
                ->where('prompt.body', fn (string $body): bool => str_contains($body, '{{ selection }}')));
    }

    #[Test]
    public function saving_a_body_creates_a_version_activates_it_and_logs_it(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        $this->actingAs($admin)
            ->put('/admin/prompts/explainer.select', [
                'body' => $this->body($prompt, 'In one sentence.'),
                'tier' => 'cheap',
            ])
            ->assertRedirect('/admin/prompts/explainer.select')
            ->assertSessionHasNoErrors();

        $active = $this->prompt('explainer.select')->activeVersion;

        $this->assertSame(2, $active->number);
        $this->assertSame(PromptSource::Admin, $active->source);
        $this->assertSame($admin->id, $active->author_id);
        $this->assertStringContainsString('In one sentence.', $active->body);

        $this->assertStringContainsString(
            'In one sentence.',
            app(PromptRepository::class)->get('explainer.select')->body,
            'The repository cache was not cleared by the save.',
        );

        $logged = AdminAction::query()->sole();

        $this->assertSame(AdminActionType::PromptEdited, $logged->action);
        $this->assertSame('explainer.select', $logged->payload['key']);
        $this->assertSame(1, $logged->payload['was']['version']);
        $this->assertSame(2, $logged->payload['now']['version']);
    }

    #[Test]
    public function saving_a_tier_moves_the_tier_and_leaves_the_body_alone(): void
    {
        $prompt = $this->prompt('explainer.select');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'mid',
            ])
            ->assertSessionHasNoErrors();

        $active = $this->prompt('explainer.select')->activeVersion;

        $this->assertSame(Tier::Mid, $active->tier);
        $this->assertSame($prompt->activeVersion->body, $active->body);
    }

    #[Test]
    public function a_body_that_drops_a_declared_var_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => 'Explain {{ selection }} for a {{ skill_level }} student.',
                'tier' => 'cheap',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $this->prompt('explainer.select')->versions()->count());
        $this->assertSame(0, AdminAction::query()->count());
    }

    #[Test]
    public function a_body_that_uses_an_undeclared_var_is_refused(): void
    {
        $prompt = $this->prompt('explainer.select');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => $this->body($prompt, 'Also consider {{ homework_doc }}.'),
                'tier' => 'cheap',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $prompt->versions()->count());
    }

    /**
     * Spec 15.2.3 -- `interview.knowledge` streams inside the request, where
     * the host's 60-second silence ceiling applies (ledger D63).
     */
    #[Test]
    public function the_editor_refuses_top_for_a_prompt_that_streams_inside_the_request(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('interview.knowledge');

        $this->actingAs($admin)->get('/admin/prompts/interview.knowledge')
            ->assertInertia(fn (Assert $page) => $page->where('tiers', ['cheap', 'mid']));

        $this->actingAs($admin)
            ->put('/admin/prompts/interview.knowledge', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'top',
            ])
            ->assertSessionHasErrors('tier');

        $this->actingAs($admin)
            ->put('/admin/prompts/interview.knowledge', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'cheap',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Spec 15.2.3 -- `lesson.section` declares `tier: session`, which is not a
     * tier it chooses for itself: it inherits `lesson.system`'s.
     */
    #[Test]
    public function a_session_tier_prompt_has_no_editable_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('lesson.section');

        $this->actingAs($admin)->get('/admin/prompts/lesson.section')
            ->assertInertia(fn (Assert $page) => $page->where('tiers', []));

        $this->actingAs($admin)
            ->put('/admin/prompts/lesson.section', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'mid',
            ])
            ->assertSessionHasErrors('tier');

        $this->actingAs($admin)
            ->put('/admin/prompts/lesson.section', ['body' => $this->body($prompt, 'Keep it short.')])
            ->assertSessionHasNoErrors();

        $this->assertSame(Tier::Session, $this->prompt('lesson.section')->activeVersion->tier);
    }

    /**
     * Spec 15.2.3 -- `lesson.system` carries the tier the streamed lesson
     * session runs at, so it is held to the same ceiling.
     */
    #[Test]
    public function the_lesson_system_prompt_cannot_move_the_session_to_top(): void
    {
        $prompt = $this->prompt('lesson.system');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/lesson.system', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'top',
            ])
            ->assertSessionHasErrors('tier');
    }

    #[Test]
    public function the_history_lists_every_version_newest_first_with_who_saved_it(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'In one sentence.'),
            'tier' => 'cheap',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page
                ->has('versions', 2)
                ->where('versions.0.number', 2)
                ->where('versions.0.active', true)
                ->where('versions.0.author', $admin->name)
                ->where('versions.0.source', 'admin')
                ->where('versions.1.number', 1)
                ->where('versions.1.active', false)
                ->where('versions.1.author', null)
                ->where('versions.1.source', 'file'));
    }

    #[Test]
    public function the_diff_defaults_to_the_active_version_against_the_one_before_it(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        $this->actingAs($admin)->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page->where('diff', null), 'One version is nothing to compare.');

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'In one sentence.'),
            'tier' => 'cheap',
        ]);

        $this->actingAs($admin)->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page
                ->where('diff.from', 1)
                ->where('diff.to', 2)
                ->where('diff.lines', fn (Collection $lines): bool => $lines->contains(
                    fn (array $line): bool => $line === ['marker' => 'add', 'text' => 'In one sentence.'],
                )));
    }

    #[Test]
    public function any_two_versions_can_be_diffed(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['Second.', 'Third.'] as $addition) {
            $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
                'body' => $this->body($this->prompt('explainer.select'), $addition),
                'tier' => 'cheap',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($admin)->get('/admin/prompts/explainer.select?from=1&to=3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('diff.from', 1)
                ->where('diff.to', 3)
                // Both edits, although neither is in v1 or in the other's
                // version: a diff across a gap is not a diff of neighbours.
                ->where('diff.lines', fn (Collection $lines): bool => $lines
                    ->where('marker', 'add')
                    ->pluck('text')
                    ->intersect(['Second.', 'Third.'])
                    ->count() === 2));

        $this->actingAs($admin)->get('/admin/prompts/explainer.select?from=1&to=9')->assertNotFound();
    }

    /**
     * Spec 15.2.4's done-condition. A call reads its version id from
     * `PromptRepository`, which the gateway hands to every `PendingCall` and
     * which is what lands in `usage_events.prompt_version_id` (15.1.6).
     */
    #[Test]
    public function rolling_back_makes_the_next_call_name_the_older_version(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');
        $first = $prompt->activeVersion;

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'In one sentence.'),
            'tier' => 'cheap',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/admin/prompts/explainer.select/versions/1')
            ->assertRedirect('/admin/prompts/explainer.select')
            ->assertSessionHasNoErrors();

        $repository = app(PromptRepository::class);

        $this->assertSame($first->id, $repository->version('explainer.select'));
        $this->assertSame($first->body, $repository->get('explainer.select')->body);
        $this->assertSame(2, $this->prompt('explainer.select')->versions()->count(), 'A rollback writes no version.');

        $logged = AdminAction::query()->where('action', AdminActionType::PromptRolledBack)->sole();

        $this->assertSame(2, $logged->payload['was']['version']);
        $this->assertSame(1, $logged->payload['now']['version']);

        // The diff still has something to show, although what is running is
        // now the oldest version there is.
        $this->actingAs($admin)->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page
                ->where('diff.from', 1)
                ->where('diff.to', 2)
                ->where('versions.1.active', true));
    }

    #[Test]
    public function a_save_after_a_rollback_is_numbered_past_the_newest_version(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'In one sentence.'),
            'tier' => 'cheap',
        ])->assertSessionHasNoErrors();

        $second = $this->prompt('explainer.select')->activeVersion;

        $this->actingAs($admin)->post('/admin/prompts/explainer.select/versions/1')->assertSessionHasNoErrors();

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'In two sentences.'),
            'tier' => 'cheap',
        ])->assertSessionHasNoErrors();

        $active = $this->prompt('explainer.select')->activeVersion;

        $this->assertSame(3, $active->number);
        $this->assertStringContainsString('In two sentences.', $active->body);
        $this->assertSame($second->body, $second->fresh()->body, 'Version 2 is untouched.');
    }

    #[Test]
    public function a_save_whose_log_row_fails_writes_no_version(): void
    {
        $prompt = $this->prompt('explainer.select');

        AdminAction::creating(fn () => throw new RuntimeException('The action log is down.'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs(User::factory()->admin()->create())->put('/admin/prompts/explainer.select', [
                'body' => $this->body($prompt, 'In one sentence.'),
                'tier' => 'cheap',
            ]);
            $this->fail('The save should have failed with its log row.');
        } catch (RuntimeException $e) {
            $this->assertSame('The action log is down.', $e->getMessage());
        }

        $this->assertSame(1, $this->prompt('explainer.select')->versions()->count());
        $this->assertSame(1, $this->prompt('explainer.select')->activeVersion->number);
    }

    /**
     * Spec 15.2.6 -- a deploy that adds a var to a file leaves the running
     * text without it; the list says so until an edit uses it.
     */
    #[Test]
    public function a_prompt_whose_running_text_leaves_out_a_declared_var_is_marked_until_an_edit_uses_it(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        // What `prompts:sync` writes when the file declares one more var.
        $prompt->update(['vars' => [...$prompt->vars, 'goals']]);

        $this->actingAs($admin)->get('/admin/prompts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('prompts.3.key', 'explainer.select')
                ->where('prompts.3.unused', ['goals'])
                ->where('prompts.2.unused', []));

        $this->actingAs($admin)->put('/admin/prompts/explainer.select', [
            'body' => $this->body($prompt, 'Mind {{ goals }}.'),
            'tier' => 'cheap',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/admin/prompts')
            ->assertInertia(fn (Assert $page) => $page->where('prompts.3.unused', []));
    }

    /**
     * Spec 15.2.3 must not have a second door: a prompt that has since joined
     * the in-request list cannot be rolled back onto a tier it may no longer
     * be saved at.
     */
    #[Test]
    public function a_rollback_is_refused_when_the_versions_tier_is_no_longer_allowed(): void
    {
        $prompt = $this->prompt('interview.knowledge');

        // As if this prompt had run at `top` before it began streaming inside
        // the request; the editor itself would refuse to write this.
        $prompt->versions()->create([
            'number' => 2,
            'body' => $prompt->activeVersion->body,
            'tier' => Tier::Top,
            'source' => PromptSource::Admin,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/prompts/interview.knowledge/versions/2')
            ->assertSessionHasErrors('version');

        $this->assertSame(1, $this->prompt('interview.knowledge')->activeVersion->number);
    }

    private function prompt(string $key): Prompt
    {
        return Prompt::query()->with('activeVersion')->where('key', $key)->sole();
    }

    /**
     * The active body with a line added, so a save changes the text without
     * touching which variables it interpolates.
     */
    private function body(Prompt $prompt, string $addition): string
    {
        return $prompt->activeVersion->body."\n".$addition."\n";
    }

    private function served(string $key, float $cost, int $ago = 0): void
    {
        UsageEvent::query()->create([
            'feature' => 'lesson',
            'prompt_key' => $key,
            'provider' => 'anthropic',
            'tier' => 'cheap',
            'model' => Tier::Cheap->model(),
            'outcome' => 'ok',
            'latency_ms' => 100,
            'cost_usd' => $cost,
            'created_at' => now()->subDays($ago),
        ]);
    }
}
