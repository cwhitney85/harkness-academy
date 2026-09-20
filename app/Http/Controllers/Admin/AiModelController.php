<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Ai\Catalog;
use App\Ai\Tier;
use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\UsageEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Admin → Models (spec 14.4): the catalog, what each model has served and cost
 * over the last seven days, and the tiers that route calls to it.
 *
 * The models are read from `ai_models` rather than through `App\Ai\Catalog`,
 * because this screen edits them and shows `display_name` and `active`, which
 * the cached arrays do not carry ([lessons T16](docs/lessons.md)). The tiers
 * are read through the catalog, so the screen shows the routing a call gets
 * now, not a row no call has read yet (14.1.3).
 */
class AiModelController extends Controller
{
    /** The window the calls and cost columns cover (14.4.1). */
    private const DAYS = 7;

    public function index(): Response
    {
        $models = AiModel::query()->orderBy('id')->get();
        $names = $models->mapWithKeys(fn (AiModel $model): array => [$model->id => $model->display_name]);

        $usage = UsageEvent::query()
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->whereNotNull('ai_model_id')
            ->groupBy('ai_model_id')
            ->selectRaw('ai_model_id, count(*) as calls, coalesce(sum(cost_usd), 0) as cost')
            ->get()
            ->keyBy('ai_model_id');

        return Inertia::render('admin/models', [
            'days' => self::DAYS,
            'models' => $models->map(fn (AiModel $model): array => [
                'id' => $model->id,
                'name' => $model->display_name,
                'provider' => $model->provider,
                'model' => $model->model,
                'active' => $model->active,
                'prices' => [
                    'input_per_mtok' => (float) $model->input_per_mtok,
                    'output_per_mtok' => (float) $model->output_per_mtok,
                    'cache_read_per_mtok' => (float) $model->cache_read_per_mtok,
                    'cache_write_per_mtok' => (float) $model->cache_write_per_mtok,
                ],
                'calls' => (int) ($usage[$model->id]->calls ?? 0),
                'cost' => (float) ($usage[$model->id]->cost ?? 0),
            ])->all(),
            'tiers' => collect(Tier::cases())
                ->filter(fn (Tier $tier): bool => $tier->isBillable())
                ->map(function (Tier $tier) use ($names): array {
                    // Pre: the three billable tiers each have a row -- the
                    // seeding migration writes them and nothing drops one.
                    $row = Catalog::tier($tier->value) ?? throw new RuntimeException(
                        "Tier [{$tier->value}] has no row in ai_tiers."
                    );

                    $name = fn (int $id): string => $names[$id] ?? "#{$id}";

                    return [
                        'name' => $tier->value,
                        'model' => $name($row['model']),
                        'backups' => array_map($name, $row['backups']),
                        'timeout' => $row['timeout'],
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * Reprice a model (14.4.2).
     *
     * Post: one `admin_actions` row holding the prices before and after, the
     * catalog cache cleared by the model's own write hook, and no
     * `usage_events` row touched -- a call's cost is stamped when its row is
     * written, so a price edit reaches later calls only.
     */
    public function update(Request $request, AiModel $model, ActionLog $log): RedirectResponse
    {
        $price = ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:999999'];

        $prices = $request->validate([
            'input_per_mtok' => $price,
            'output_per_mtok' => $price,
            'cache_read_per_mtok' => $price,
            'cache_write_per_mtok' => $price,
        ]);

        $columns = array_keys($prices);
        $was = $model->only($columns);

        $model->update($prices);

        $log->record(
            $request->user(),
            AdminActionType::AiModelRepriced,
            $model,
            payload: ['was' => $was, 'now' => $model->only($columns)],
        );

        return back();
    }
}
