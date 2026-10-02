<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Usage (spec 17.3): what calls cost, how each task performs on each
 * model, and every call, over one date range.
 *
 * Every view leaves out calls an admin made acting as a student unless asked
 * to count them (17.3.3). A task is a prompt (ledger D52: a call names a task,
 * the task a tier, the tier a model); rows group by the `model` string, which
 * every row has, rather than `ai_model_id`, which rows from before the catalog
 * (14.1) do not.
 */
class UsageController extends Controller
{
    /** How far back a view looks when no range is given. */
    private const DAYS = 30;

    /**
     * Cost by day, by feature and by plan (17.3.1). A row written before
     * usage rows kept the plan (14.3.4) is counted under `unrecorded`.
     */
    public function overview(Request $request): Response
    {
        [$range, $events] = $this->range($request);

        return Inertia::render('admin/usage/overview', [
            'range' => $range,
            'total' => (float) (clone $events)->sum('cost_usd'),
            'days' => $this->costBy($events, 'created_at::date')->sortBy('key')->values(),
            'features' => $this->costBy($events, 'feature')->sortByDesc('cost')->values(),
            'plans' => $this->costBy($events, "coalesce(plan, 'unrecorded')")->sortByDesc('cost')->values(),
        ]);
    }

    /**
     * Per task × model (17.3.2). Latency and tokens are over calls that were
     * answered: a failed call's latency is time to the error, and it has no
     * tokens. A call's input is its uncached, cache-read and cache-write
     * tokens together, which the provider reports apart and prices apart.
     *
     * The keys are compared bytewise (`collate "C"`): sorting a million rows
     * by three text columns under the database's language collation was half
     * of what this took (17.3.5). A task that moved tier on the same model is
     * a row per tier.
     */
    public function performance(Request $request): Response
    {
        [$range, $events] = $this->range($request);

        $answered = "outcome is distinct from 'failed'";
        $input = 'input_tokens + cached_input_tokens + cache_write_tokens';

        $rows = $events->toBase()
            ->selectRaw('prompt_key collate "C" as prompt_key, tier collate "C" as tier, model collate "C" as model')
            ->selectRaw('count(*) as calls')
            ->selectRaw("percentile_cont(0.5) within group (order by latency_ms) filter (where {$answered}) as median")
            ->selectRaw("percentile_cont(0.95) within group (order by latency_ms) filter (where {$answered}) as p95")
            ->selectRaw("avg({$input}) filter (where {$answered}) as input")
            ->selectRaw("avg(output_tokens) filter (where {$answered}) as output")
            ->selectRaw("sum(cached_input_tokens)::float / nullif(sum({$input}), 0) as cache_share")
            ->selectRaw('sum(cost_usd) / count(*) as cost_per_call')
            ->selectRaw("count(*) filter (where outcome = 'failed')::float / count(*) as failure_rate")
            ->selectRaw("count(*) filter (where outcome = 'failed_over')::float / count(*) as failover_rate")
            ->groupByRaw('1, 2, 3')
            ->orderByRaw('1, 3, 2')
            ->get();

        return Inertia::render('admin/usage/performance', [
            'range' => $range,
            'rows' => $rows->map(fn (object $row): array => [
                'task' => $row->prompt_key,
                'model' => $row->model,
                'tier' => $row->tier,
                'calls' => (int) $row->calls,
                'median' => $row->median === null ? null : (int) round((float) $row->median),
                'p95' => $row->p95 === null ? null : (int) round((float) $row->p95),
                'input' => $row->input === null ? null : (int) round((float) $row->input),
                'output' => $row->output === null ? null : (int) round((float) $row->output),
                'cache_share' => $row->cache_share === null ? null : round((float) $row->cache_share, 4),
                'cost_per_call' => round((float) $row->cost_per_call, 8),
                'failure_rate' => round((float) $row->failure_rate, 4),
                'failover_rate' => round((float) $row->failover_rate, 4),
            ])->all(),
        ]);
    }

    /**
     * Every call, newest first, each linking to its student, course and
     * lesson (17.3.4).
     */
    public function events(Request $request): Response
    {
        [$range, $events] = $this->range($request);

        $filters = $request->validate([
            'feature' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'outcome' => ['nullable', 'in:ok,failed,failed_over'],
        ]);

        $page = (clone $events)
            ->with(['user' => fn ($user) => $user->withTrashed()->select('id', 'name'), 'actingUser:id,name', 'lesson:id,position'])
            ->when($filters['feature'] ?? null, fn (Builder $query, string $feature) => $query->where('feature', $feature))
            ->when($filters['model'] ?? null, fn (Builder $query, string $model) => $query->where('model', $model))
            ->when($filters['outcome'] ?? null, fn (Builder $query, string $outcome) => $query->where('outcome', $outcome))
            ->latest('id')
            ->simplePaginate(50)
            ->withQueryString()
            ->through(fn (UsageEvent $event): array => [
                'id' => $event->id,
                'when' => $event->created_at->format('j M H:i:s'),
                'user' => $event->user === null ? null : ['id' => $event->user->id, 'name' => $event->user->name],
                'course' => $event->course_id,
                'lesson' => $event->lesson?->position,
                'feature' => $event->feature,
                'prompt' => $event->prompt_key,
                'model' => $event->model,
                'input' => $event->input_tokens + $event->cached_input_tokens + $event->cache_write_tokens,
                'output' => $event->output_tokens,
                'latency' => $event->latency_ms,
                'cost' => (float) $event->cost_usd,
                'outcome' => $event->outcome,
                'acting' => $event->actingUser?->name,
            ]);

        return Inertia::render('admin/usage/events', [
            'range' => $range,
            'events' => $page,
            'filters' => [
                'feature' => $filters['feature'] ?? null,
                'model' => $filters['model'] ?? null,
                'outcome' => $filters['outcome'] ?? null,
            ],
            'options' => [
                'features' => (clone $events)->distinct()->orderBy('feature')->pluck('feature'),
                'models' => (clone $events)->distinct()->orderBy('model')->pluck('model'),
            ],
        ]);
    }

    /**
     * Calls and cost grouped by one SQL expression, for the overview.
     *
     * @param  Builder<UsageEvent>  $events
     * @param  literal-string  $key
     * @return Collection<int, array{key: string, calls: int, cost: float}>
     */
    private function costBy(Builder $events, string $key): Collection
    {
        return (clone $events)
            ->toBase()
            ->selectRaw("{$key} as key, count(*) as calls, sum(cost_usd) as cost")
            ->groupByRaw($key)
            ->get()
            ->map(fn (object $row): array => ['key' => (string) $row->key, 'calls' => (int) $row->calls, 'cost' => (float) $row->cost]);
    }

    /**
     * The date range and act-as toggle every view shares, and the calls they
     * select: from the start of `from` to the end of `to`, both inclusive.
     *
     * @return array{0: array{from: string, to: string, acting: bool}, 1: Builder<UsageEvent>}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'acting' => ['nullable', 'boolean'],
        ]);

        $to = Carbon::parse($validated['to'] ?? today());
        $from = Carbon::parse($validated['from'] ?? $to->copy()->subDays(self::DAYS - 1));
        $acting = (bool) ($validated['acting'] ?? false);

        return [
            ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'acting' => $acting],
            UsageEvent::query()
                ->where('created_at', '>=', $from->copy()->startOfDay())
                ->where('created_at', '<', $to->copy()->addDay()->startOfDay())
                ->when(! $acting, fn (Builder $query) => $query->whereNull('acting_user_id')),
        ];
    }
}
