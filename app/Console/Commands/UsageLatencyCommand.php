<?php

namespace App\Console\Commands;

use App\Ai\Catalog;
use App\Models\UsageEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * What every prompt actually costs in wall-clock time (spec 2.2.1).
 *
 * 2.2's done-condition reads the report "from SQL alone", which is true of
 * any environment with a psql prompt in it. The deployed one has none: Laravel
 * Cloud offers a Commands tab and a credentials modal, and the numbers are
 * wanted in the environment where the ceilings are real. So the report is a
 * command, and it reads `usage_events` rather than adding a column to it.
 *
 * The ceiling column is the point. A streamed response that says nothing for
 * longer than the host allows between bytes is cut mid-flight, and the tier's
 * own timeout is the app's belief about how long the same call may take. When
 * the tier is the larger of the two, the platform kills work the app is still
 * waiting on, and the student is told nothing useful by the layer that gave up
 * first -- which is trap T10, twice.
 */
class UsageLatencyCommand extends Command
{
    protected $signature = 'usage:latency {--ceiling=60 : Seconds the host allows between bytes of a response}';

    protected $description = 'Spec 2.2.1: per-prompt latency from usage_events, against the tier and host ceilings';

    public function handle(): int
    {
        $ceilingMs = (int) $this->option('ceiling') * 1000;

        $rows = UsageEvent::query()
            ->select('prompt_key', 'tier')
            ->selectRaw('count(*) as calls')
            ->selectRaw('round(avg(latency_ms)) as avg_ms')
            ->selectRaw('max(latency_ms) as max_ms')
            ->groupBy('prompt_key', 'tier')
            ->orderByDesc(DB::raw('max(latency_ms)'))
            // A row per (prompt, tier) is a total, not a usage event, so it is
            // read as the record it is rather than hydrated into a model
            // that has no `calls` or `max_ms`.
            ->toBase()
            ->get();

        if ($rows->isEmpty()) {
            $this->components->warn('No usage events recorded yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Prompt', 'Tier', 'Calls', 'Avg', 'Max', 'Tier cap', 'Against ceiling'],
            $rows->map(fn (stdClass $row): array => [
                $row->prompt_key,
                $row->tier,
                $row->calls,
                $this->seconds((int) $row->avg_ms),
                $this->seconds((int) $row->max_ms),
                $this->seconds($this->tierCapMs($row->tier)),
                $this->verdict((int) $row->max_ms, $ceilingMs),
            ])->all(),
        );

        $this->newLine();
        $this->components->info(sprintf(
            'Ceiling is %ds. A tier cap above it is a call the host can kill while the app still believes in it.',
            $ceilingMs / 1000,
        ));

        return self::SUCCESS;
    }

    /**
     * The tier's own timeout, in milliseconds.
     *
     * `session` resolves per call site rather than in the catalog, so it has
     * no cap of its own to print; the rows carry the tier the call ran at.
     */
    private function tierCapMs(string $tier): int
    {
        return (Catalog::tier($tier)['timeout'] ?? 0) * 1000;
    }

    private function seconds(int $ms): string
    {
        return $ms === 0 ? '—' : number_format($ms / 1000, 1).'s';
    }

    private function verdict(int $maxMs, int $ceilingMs): string
    {
        if ($maxMs >= $ceilingMs) {
            return '<fg=red>over</>';
        }

        // Within a fifth of the ceiling is close enough that the next slow
        // call is the one that gets cut.
        return $maxMs >= $ceilingMs * 0.8
            ? '<fg=yellow>close</>'
            : '<fg=green>clear</>';
    }
}
