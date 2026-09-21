<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Ai\Prompts\PromptDefinition;
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
                    'calls' => $usage[$prompt->key]['calls'] ?? 0,
                    'cost' => $usage[$prompt->key]['cost'] ?? 0.0,
                ])
                ->all(),
        ]);
    }

    public function show(Prompt $prompt): Response
    {
        $prompt->load('activeVersion');

        return Inertia::render('admin/prompt', [
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
     * Save a new body or tier (15.2.2).
     *
     * Pre:  the body interpolates exactly the vars the file declares, and the
     *       tier is one this prompt may move to.
     * Post: one new immutable version, active, and one `admin_actions` row.
     *       No other prompt's rendering changes.
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

        $version = DB::transaction(function () use ($prompt, $active, $body, $tier, $request): PromptVersion {
            $version = $prompt->versions()->create([
                'number' => $active->number + 1,
                'body' => $body,
                'tier' => $tier,
                'source' => PromptSource::Admin,
                'author_id' => $request->user()->id,
            ]);

            $prompt->activate($version);

            return $version;
        });

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

        return to_route('admin.prompts.show', $prompt);
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
        $used = PromptDefinition::placeholders($body);

        $missing = array_values(array_diff($prompt->vars, $used));
        $undeclared = array_values(array_diff($used, $prompt->vars));

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
     */
    /**
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
