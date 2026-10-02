<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Ai\Prompts\PromptDefinition;
use App\Ai\Prompts\PromptDiff;
use App\Ai\Prompts\PromptSource;
use App\Ai\Tier;
use App\Http\Controllers\Controller;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\UsageEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Prompts (spec 15.2): the 2.3.2 inventory, what each prompt has cost,
 * and the one place a prompt's text is written (ledger D65).
 *
 * The file owns the contract, so `output` and `vars` are shown and not
 * editable. The editor owns the body and the tier, and a save is a new
 * immutable version rather than an update (15.1.1).
 */
class PromptController extends Controller
{
    /** The window the calls and cost columns cover (15.2.1). */
    private const DAYS = 7;

    /**
     * Prompts whose text streams inside an HTTP request, where the host cuts a
     * response silent for 60 seconds (spec 11, [T10](docs/lessons.md)). A
     * slower model could exceed that before its first token, so until streamed
     * generation moves to the queue these may move only between `cheap` and
     * `mid` (15.2.3, ledger D63).
     *
     * `lesson.system` is on the list although it streams nothing itself: it
     * names the tier the whole lesson session runs at, which is what
     * `lesson.section` and `lesson.qa` inherit by declaring `tier: session`
     * (2.3.3, 15.1.7). Those two have no editable tier of their own.
     */
    private const IN_REQUEST_STREAMING = [
        'interview.goals',
        'interview.knowledge',
        'lesson.system',
    ];

    public function index(): Response
    {
        $usage = $this->sevenDayUsage();

        return Inertia::render('admin/prompts', [
            'days' => self::DAYS,
            'prompts' => Prompt::query()
                ->with('activeVersion')
                ->orderBy('key')
                ->get()
                ->map(fn (Prompt $prompt): array => [
                    'key' => $prompt->key,
                    'output' => $prompt->output,
                    'version' => $prompt->activeVersion->number,
                    'source' => $prompt->activeVersion->source->value,
                    'tier' => $prompt->activeVersion->tier->value,
                    // A var a deploy added that the running text does not use
                    // yet; the model goes without it until an edit (15.2.6).
                    'unused' => $prompt->varsUnusedBy($prompt->activeVersion->body),
                    'calls' => $usage[$prompt->key]['calls'] ?? 0,
                    'cost' => $usage[$prompt->key]['cost'] ?? 0.0,
                ])
                ->all(),
        ]);
    }

    public function show(Request $request, Prompt $prompt): Response
    {
        $prompt->load(['activeVersion', 'versions.author']);

        $versions = $prompt->versions->sortByDesc('number')->values();

        return Inertia::render('admin/prompt', [
            'versions' => $versions->map(fn (PromptVersion $version): array => [
                'number' => $version->number,
                'tier' => $version->tier->value,
                'source' => $version->source->value,
                'author' => $version->author?->name,
                'saved_at' => $version->created_at->toIso8601String(),
                'active' => $version->id === $prompt->active_version_id,
            ])->all(),
            'diff' => $this->diff($request, $prompt),
            'prompt' => [
                'key' => $prompt->key,
                'output' => $prompt->output,
                'vars' => $prompt->vars,
                'version' => $prompt->activeVersion->number,
                'source' => $prompt->activeVersion->source->value,
                'tier' => $prompt->activeVersion->tier->value,
                'body' => $prompt->activeVersion->body,
            ],
            // Empty exactly when the file declares `session` or `none`, which
            // are not tiers a prompt chooses for itself (15.2.3).
            'tiers' => array_map(
                fn (Tier $tier): string => $tier->value,
                $this->editableTiers($prompt),
            ),
        ]);
    }

    /**
     * Re-activate an earlier version (15.2.4).
     *
     * Pre:  the version belongs to this prompt, and its tier is one this
     *       prompt may run at -- a rollback must not be a second door to a
     *       tier 15.2.3 refuses at save.
     * Post: that version is active, nothing is written to `prompt_versions`,
     *       and one `admin_actions` row names both numbers.
     */
    public function activate(Request $request, Prompt $prompt, int $number, ActionLog $log): RedirectResponse
    {
        $prompt->load('activeVersion');

        $was = $prompt->activeVersion;
        $version = $prompt->versions()->where('number', $number)->firstOrFail();
        $tiers = $this->editableTiers($prompt);

        if ($tiers !== [] && ! in_array($version->tier, $tiers, true)) {
            throw ValidationException::withMessages([
                'version' => "Version {$number} runs at [{$version->tier->value}], which this prompt may no longer be saved at.",
            ]);
        }

        DB::transaction(function () use ($request, $prompt, $was, $version, $log): void {
            $prompt->activate($version);

            $log->record(
                $request->user(),
                AdminActionType::PromptRolledBack,
                $prompt,
                payload: [
                    'key' => $prompt->key,
                    'was' => ['version' => $was->number, 'tier' => $was->tier->value],
                    'now' => ['version' => $version->number, 'tier' => $version->tier->value],
                ],
            );
        });

        return to_route('admin.prompts.show', $prompt);
    }

    /**
     * Save a new body or tier (15.2.2).
     *
     * Pre:  the body interpolates exactly the vars the file declares, and the
     *       tier is one this prompt may move to.
     * Post: one new immutable version, numbered past every earlier one and
     *       active, and one `admin_actions` row, committed together. No other
     *       prompt's rendering changes.
     */
    public function update(Request $request, Prompt $prompt, ActionLog $log): RedirectResponse
    {
        $prompt->load('activeVersion');

        $active = $prompt->activeVersion;
        $tiers = $this->editableTiers($prompt);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
            'tier' => $tiers === []
                ? ['prohibited']
                : ['required', Rule::in(array_map(fn (Tier $tier): string => $tier->value, $tiers))],
        ], [
            'tier.prohibited' => "[{$active->tier->value}] is not a tier a prompt chooses for itself, so this prompt's tier cannot be edited.",
        ]);

        $body = rtrim($validated['body'])."\n";

        $this->assertBodyMatchesTheContract($prompt, $body);

        $tier = isset($validated['tier']) ? Tier::from($validated['tier']) : $active->tier;

        DB::transaction(function () use ($prompt, $active, $body, $tier, $request, $log): void {
            $version = $prompt->versions()->create([
                // Past the highest, not the active: after a rollback the
                // active version is not the newest (15.2.4).
                'number' => 1 + (int) $prompt->versions()->max('number'),
                'body' => $body,
                'tier' => $tier,
                'source' => PromptSource::Admin,
                'author_id' => $request->user()->id,
            ]);

            $prompt->activate($version);

            $log->record(
                $request->user(),
                AdminActionType::PromptEdited,
                $prompt,
                payload: [
                    'key' => $prompt->key,
                    'was' => ['version' => $active->number, 'tier' => $active->tier->value],
                    'now' => ['version' => $version->number, 'tier' => $version->tier->value],
                ],
            );
        });

        return to_route('admin.prompts.show', $prompt);
    }

    /**
     * The diff the screen is showing (15.2.4), or null where there is nothing
     * to compare.
     *
     * `from` and `to` are version numbers, defaulting to the two newest --
     * the most recent change, which is the comparison worth seeing without
     * being asked. Not the active version and its neighbour: a prompt rolled
     * back to its oldest version would have nothing below it to compare
     * against, and the history table already marks which one is running.
     *
     * A number this prompt has no version for is a 404 rather than a silent
     * fallback: the screen's own controls never send one.
     *
     * @return array{from: int, to: int, lines: list<array{marker: string, text: string}>}|null
     */
    private function diff(Request $request, Prompt $prompt): ?array
    {
        $numbers = $prompt->versions->pluck('number')->sort()->values();

        if ($numbers->count() < 2) {
            return null;
        }

        $to = $request->integer('to', $numbers->last());
        $from = $request->integer('from', $numbers[$numbers->count() - 2]);

        $before = $prompt->versions->firstWhere('number', $from);
        $after = $prompt->versions->firstWhere('number', $to);

        abort_if($before === null || $after === null, 404);

        return [
            'from' => $from,
            'to' => $to,
            'lines' => PromptDiff::between($before->body, $after->body),
        ];
    }

    /**
     * Spec 15.2.2: a missing or undeclared var is refused at save.
     *
     * Both directions matter and for different reasons. An undeclared
     * `{{ placeholder }}` would be left in the prompt text verbatim, because
     * `render()` holds the caller to the declared vars and not the body. A
     * declared var the body drops is worse: the call site still passes the
     * material, the render still succeeds, and the model silently never sees
     * it.
     *
     * @throws ValidationException
     */
    private function assertBodyMatchesTheContract(Prompt $prompt, string $body): void
    {
        $missing = $prompt->varsUnusedBy($body);
        $undeclared = array_values(array_diff(PromptDefinition::placeholders($body), $prompt->vars));

        $problems = [];

        if ($missing !== []) {
            $problems[] = 'it does not use '.$this->join($missing).', which the prompt file declares';
        }

        if ($undeclared !== []) {
            $problems[] = 'it uses '.$this->join($undeclared).', which the prompt file does not declare -- add them in `prompts/` and deploy, or take them out of the body';
        }

        if ($problems !== []) {
            throw ValidationException::withMessages([
                'body' => 'This body does not match the prompt\'s contract: '.implode('; and ', $problems).'.',
            ]);
        }
    }

    /**
     * The tiers this prompt may be saved at, empty where it has no tier of its
     * own (15.2.3).
     *
     * @return list<Tier>
     */
    private function editableTiers(Prompt $prompt): array
    {
        if (! $prompt->activeVersion->tier->isBillable()) {
            return [];
        }

        return in_array($prompt->key, self::IN_REQUEST_STREAMING, true)
            ? [Tier::Cheap, Tier::Mid]
            : [Tier::Cheap, Tier::Mid, Tier::Top];
    }

    /**
     * Seven-day calls and cost per prompt key (15.2.1).
     *
     * Keyed by `usage_events.prompt_key` rather than through the version,
     * because the column is what 2.2 has always written and a prompt's cost is
     * the cost of every version of it.
     *
     * @return array<string, array{calls: int, cost: float}>
     */
    private function sevenDayUsage(): array
    {
        $rows = UsageEvent::query()
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->groupBy('prompt_key')
            ->selectRaw('prompt_key, count(*) as calls, coalesce(sum(cost_usd), 0) as cost')
            ->toBase()
            ->get();

        $usage = [];

        foreach ($rows as $row) {
            // Aggregates, not usage events, so the rows stay arrays rather
            // than being hydrated into models that would not be any call.
            $row = (array) $row;

            $usage[(string) $row['prompt_key']] = [
                'calls' => (int) $row['calls'],
                'cost' => (float) $row['cost'],
            ];
        }

        return $usage;
    }

    /**
     * @param  list<string>  $names
     */
    private function join(array $names): string
    {
        sort($names);

        return '`'.implode('`, `', $names).'`';
    }
}
