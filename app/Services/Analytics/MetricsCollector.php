<?php

namespace App\Services\Analytics;

use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Services\Farms\UnitTotals;
use Carbon\CarbonInterface;

/**
 * The aggregate Dot.Farms numbers one company publishes to Dot.Analytics, under Analytics' catalogue keys for
 * the `farms` platform (config/metrics.php there). Counts and one weight total only: never a farm, field, crop
 * or any other name, and no row.
 *
 * Pure queries, scoped to the company explicitly. The team scopes on these models apply to whoever is signed in
 * (and are skipped with nobody signed in), so every query here opts out of exactly that scope and filters with
 * forTeam(), which walks field -> farm -> team and refuses to pass through a deleted farm, field or cycle. The
 * result never depends on who, if anyone, is signed in. Soft-deleted rows are left out by the models' own scope.
 * The 30 day window is the 30 x 24 hours ending at $asOf, the same window the dashboard uses.
 *
 * Reported:
 *   active_cycles      crop cycles in the ground: planted or growing (as the dashboard's "crops in season").
 *                      Planned cycles are not counted: nothing has been planted yet.
 *   harvests_30d       harvest records dated in the window, in any unit
 *   harvested_kg_30d   kilograms harvested in the window. Only kg and t (converted, 1 t = 1000 kg) are counted;
 *                      bags, crates, bunches, litres, heads and unrecognised units are never converted or
 *                      added in, exactly as UnitTotals keeps them apart everywhere else in the app.
 */
class MetricsCollector
{
    /** @return array{active_cycles: int, harvests_30d: int, harvested_kg_30d: float} */
    public function collect(Team|int $team, ?CarbonInterface $asOf = null): array
    {
        $now = ($asOf ? $asOf->copy() : now())->utc();
        $since = $now->copy()->subDays(30)->setTimezone((string) config('app.timezone', 'UTC'));
        $until = $now->copy()->setTimezone((string) config('app.timezone', 'UTC'));

        $harvests = fn () => HarvestRecord::query()->withoutGlobalScope('team')->forTeam($team)
            ->where('harvest_records.harvested_at', '>=', $since)
            ->where('harvest_records.harvested_at', '<=', $until);

        $weight = new UnitTotals;
        $harvests()
            ->groupBy('harvest_records.unit')
            ->selectRaw('harvest_records.unit as unit, SUM(harvest_records.quantity_harvested) as total')
            ->get()
            ->each(fn ($row) => $weight->add($row->unit, $row->total));

        return [
            'active_cycles' => CropCycle::query()->withoutGlobalScope('team')->forTeam($team)
                ->whereIn('crop_cycles.status', [CropCycleStatus::Planted->value, CropCycleStatus::Growing->value])
                ->count(),
            'harvests_30d' => $harvests()->count(),
            'harvested_kg_30d' => round($weight->amountFor('weight'), 2),
        ];
    }
}
