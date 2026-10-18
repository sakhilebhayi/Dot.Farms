<?php

namespace App\Services\Farms;

use App\Models\CropCycle;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The questions a farm asks of its own records, answered with aggregate
 * queries over the team's live rows (everything goes through forTeam(), so a
 * report can never include another team's or a deleted record).
 *
 * Quantities are summed in SQL per unit and combined in PHP by UnitTotals, so
 * only convertible units (kg and t) are ever added together; anything else is
 * reported as its own subtotal.
 *
 * Yield per hectare divides by the area the harvest came from: the planted
 * area when the cycle has plantings recorded in hectares, otherwise the size
 * of the field. With neither there is no figure, not a guess.
 */
class FarmReports
{
    /**
     * One row per crop cycle: what it harvested and what that is per hectare.
     *
     * @return Collection<int, array{season: string, farm: string, field: string, crop: string, cycle: CropCycle, area_ha: ?float, area_basis: ?string, harvested: UnitTotals, per_ha: UnitTotals}>
     */
    public function yieldByFieldSeason(Team|int $team, ?string $season = null): Collection
    {
        $cycles = CropCycle::query()->forTeam($team)
            ->with(['crop', 'field.farm'])
            ->when($season, fn ($q) => $q->where('crop_cycles.season', $season))
            ->get();

        $harvested = $this->harvestTotalsByCycle($team, $cycles->modelKeys());

        $plantedHa = PlantingRecord::query()->forTeam($team)
            ->whereIn('crop_cycle_id', $cycles->modelKeys())
            ->where('unit', 'ha')
            ->groupBy('crop_cycle_id')
            ->selectRaw('crop_cycle_id, SUM(quantity_planted) as hectares')
            ->pluck('hectares', 'crop_cycle_id');

        return $cycles
            ->map(function (CropCycle $cycle) use ($harvested, $plantedHa) {
                $planted = (float) ($plantedHa[$cycle->id] ?? 0);
                $fieldSize = (float) $cycle->field->size_hectares;

                [$area, $basis] = match (true) {
                    $planted > 0 => [$planted, 'planted'],
                    $fieldSize > 0 => [$fieldSize, 'field'],
                    default => [null, null],
                };

                $totals = $harvested[$cycle->id] ?? new UnitTotals;

                return [
                    'season' => $cycle->season,
                    'farm' => $cycle->field->farm->name,
                    'field' => $cycle->field->name,
                    'crop' => $cycle->crop->displayName(),
                    'cycle' => $cycle,
                    'area_ha' => $area,
                    'area_basis' => $basis,
                    'harvested' => $totals,
                    'per_ha' => $totals->perHectare($area),
                ];
            })
            ->sortBy([['season', 'desc'], ['farm', 'asc'], ['field', 'asc'], ['crop', 'asc']])
            ->values();
    }

    /**
     * Harvest totals per crop and calendar month, newest month first.
     *
     * @return Collection<int, array{crop: string, crop_id: int, month: string, label: string, totals: UnitTotals, records: int}>
     */
    public function harvestByCropMonth(Team|int $team, ?HarvestFilters $filters = null): Collection
    {
        $month = $this->monthExpression();

        $query = HarvestRecord::query()->forTeam($team)
            ->join('crop_cycles', 'crop_cycles.id', '=', 'harvest_records.crop_cycle_id')
            ->join('crops', 'crops.id', '=', 'crop_cycles.crop_id');

        $filters?->apply($query);

        $rows = $query
            ->selectRaw("crop_cycles.crop_id as crop_id, crops.name as crop_name, crops.variety as crop_variety, {$month} as month, harvest_records.unit as unit, SUM(harvest_records.quantity_harvested) as total, COUNT(*) as records")
            ->groupBy('crop_cycles.crop_id', 'crops.name', 'crops.variety', 'harvest_records.unit')
            ->groupByRaw($month)
            ->get();

        return $rows
            ->groupBy(fn ($row) => $row->crop_id.'|'.$row->month)
            ->map(function (Collection $group) {
                $first = $group->first();
                $totals = new UnitTotals;
                foreach ($group as $row) {
                    $totals->add($row->unit, $row->total);
                }

                return [
                    'crop' => $first->crop_variety ? "{$first->crop_name} — {$first->crop_variety}" : $first->crop_name,
                    'crop_id' => (int) $first->crop_id,
                    'month' => $first->month,
                    'label' => Carbon::createFromFormat('!Y-m', $first->month)->format('F Y'),
                    'totals' => $totals,
                    'records' => (int) $group->sum('records'),
                ];
            })
            ->sortBy([['month', 'desc'], ['crop', 'asc']])
            ->values();
    }

    /**
     * Open cycles (planned, planted, growing), overdue ones first, each with
     * what it has already harvested.
     *
     * @return Collection<int, array{cycle: CropCycle, overdue: bool, days_in_ground: ?int, harvested: UnitTotals}>
     */
    public function outstandingCycles(Team|int $team, ?string $season = null): Collection
    {
        $cycles = CropCycle::query()->forTeam($team)->open()
            ->when($season, fn ($q) => $q->where('crop_cycles.season', $season))
            ->with(['crop', 'field.farm'])
            ->orderByRaw('crop_cycles.expected_harvest_at is null')
            ->orderBy('crop_cycles.expected_harvest_at')
            ->get();

        $harvested = $this->harvestTotalsByCycle($team, $cycles->modelKeys());

        return $cycles
            ->map(fn (CropCycle $cycle) => [
                'cycle' => $cycle,
                'overdue' => $cycle->isOverdue(),
                'days_in_ground' => $cycle->planted_at ? (int) $cycle->planted_at->diffInDays(now(), true) : null,
                'harvested' => $harvested[$cycle->id] ?? new UnitTotals,
            ])
            ->sortBy(fn ($row) => $row['overdue'] ? 0 : 1)
            ->values();
    }

    /**
     * Everything harvested since a moment, as a total and a record count.
     *
     * @return array{totals: UnitTotals, records: int}
     */
    public function harvestTotalSince(Team|int $team, CarbonInterface $since): array
    {
        $rows = HarvestRecord::query()->forTeam($team)
            ->where('harvest_records.harvested_at', '>=', $since)
            ->groupBy('harvest_records.unit')
            ->selectRaw('harvest_records.unit as unit, SUM(harvest_records.quantity_harvested) as total, COUNT(*) as records')
            ->get();

        $totals = new UnitTotals;
        foreach ($rows as $row) {
            $totals->add($row->unit, $row->total);
        }

        return ['totals' => $totals, 'records' => (int) $rows->sum('records')];
    }

    /**
     * @param  array<int, int>  $cycleIds
     * @return array<int, UnitTotals> keyed by crop cycle id
     */
    private function harvestTotalsByCycle(Team|int $team, array $cycleIds): array
    {
        if ($cycleIds === []) {
            return [];
        }

        $totals = [];
        HarvestRecord::query()->forTeam($team)
            ->whereIn('harvest_records.crop_cycle_id', $cycleIds)
            ->groupBy('harvest_records.crop_cycle_id', 'harvest_records.unit')
            ->selectRaw('harvest_records.crop_cycle_id as crop_cycle_id, harvest_records.unit as unit, SUM(harvest_records.quantity_harvested) as total')
            ->get()
            ->each(function ($row) use (&$totals) {
                ($totals[$row->crop_cycle_id] ??= new UnitTotals)->add($row->unit, $row->total);
            });

        return $totals;
    }

    /** "YYYY-MM" of harvested_at, in the SQL dialect of the connection (SQLite for tests, PostgreSQL in production). */
    private function monthExpression(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char(harvest_records.harvested_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(harvest_records.harvested_at, '%Y-%m')",
            default => "strftime('%Y-%m', harvest_records.harvested_at)",
        };
    }
}
