<?php

namespace App\Services\Farms;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Hard-deletes records that have been soft-deleted since before a cutoff.
 *
 * Raw table queries on purpose: this runs from the console, across every team,
 * and must see (and be able to delete) soft-deleted rows without any model
 * scope or soft-delete event in the way.
 *
 * Rules, bottom-up so a child never outlives the parent it hangs from:
 *  - a planting or harvest is purged when it was removed before the cutoff;
 *  - a cycle, field or farm removed before the cutoff is purged only when nothing
 *    live (not deleted) is left anywhere beneath it; its removed descendants go
 *    with it whatever their own age, because they cannot exist without it;
 *  - a crop removed before the cutoff is purged only when no cycle row, live or
 *    removed, still names it (history keeps naming its crop).
 * A live row is never touched, and a parent that still has a live descendant is
 * kept, so the next run (or a restore) can deal with it.
 *
 * Idempotent: a second run over the same data finds nothing. A dry run executes
 * the same deletes inside a transaction and rolls it back, so its counts are exactly
 * what a real run would delete.
 */
class RemovedPurger
{
    /** Tables in the order their counts are reported. */
    public const TABLES = ['farms', 'fields', 'crops', 'crop_cycles', 'planting_records', 'harvest_records'];

    private const CHUNK = 500;

    /** @return array<string, int> rows deleted per table */
    public function purge(CarbonInterface $cutoff, bool $dryRun = false): array
    {
        $purged = array_fill_keys(self::TABLES, 0);

        DB::beginTransaction();
        try {
            $this->purgeRecords($cutoff, $purged);
            $this->purgeCycles($cutoff, $purged);
            $this->purgeFields($cutoff, $purged);
            $this->purgeFarms($cutoff, $purged);
            $this->purgeCrops($cutoff, $purged);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $purged;
    }

    /** @param array<string, int> $purged */
    private function purgeRecords(CarbonInterface $cutoff, array &$purged): void
    {
        foreach (['planting_records', 'harvest_records'] as $table) {
            $purged[$table] += DB::table($table)->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff)->delete();
        }
    }

    /** @param array<string, int> $purged */
    private function purgeCycles(CarbonInterface $cutoff, array &$purged): void
    {
        $ids = $this->expired('crop_cycles', $cutoff)
            ->whereNotExists(fn (Builder $q) => $q->from('planting_records')->whereColumn('planting_records.crop_cycle_id', 'crop_cycles.id')->whereNull('planting_records.deleted_at'))
            ->whereNotExists(fn (Builder $q) => $q->from('harvest_records')->whereColumn('harvest_records.crop_cycle_id', 'crop_cycles.id')->whereNull('harvest_records.deleted_at'))
            ->pluck('crop_cycles.id')->all();

        $this->deleteCycles($ids, $purged);
    }

    /** @param array<string, int> $purged */
    private function purgeFields(CarbonInterface $cutoff, array &$purged): void
    {
        $ids = $this->expired('fields', $cutoff)
            ->whereNotExists(fn (Builder $q) => $q->from('crop_cycles')->whereColumn('crop_cycles.field_id', 'fields.id')->whereNull('crop_cycles.deleted_at'))
            ->whereNotExists(fn (Builder $q) => $this->liveRecordUnder($q, 'planting_records', 'crop_cycles.field_id = fields.id'))
            ->whereNotExists(fn (Builder $q) => $this->liveRecordUnder($q, 'harvest_records', 'crop_cycles.field_id = fields.id'))
            ->pluck('fields.id')->all();

        $this->deleteFields($ids, $purged);
    }

    /** @param array<string, int> $purged */
    private function purgeFarms(CarbonInterface $cutoff, array &$purged): void
    {
        $ids = $this->expired('farms', $cutoff)
            ->whereNotExists(fn (Builder $q) => $q->from('fields')->whereColumn('fields.farm_id', 'farms.id')->whereNull('fields.deleted_at'))
            ->whereNotExists(fn (Builder $q) => $q->from('crop_cycles')->join('fields', 'fields.id', '=', 'crop_cycles.field_id')->whereColumn('fields.farm_id', 'farms.id')->whereNull('crop_cycles.deleted_at'))
            ->whereNotExists(fn (Builder $q) => $this->liveRecordUnder($q, 'planting_records', 'fields.farm_id = farms.id', true))
            ->whereNotExists(fn (Builder $q) => $this->liveRecordUnder($q, 'harvest_records', 'fields.farm_id = farms.id', true))
            ->pluck('farms.id')->all();

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $fieldIds = DB::table('fields')->whereIn('farm_id', $chunk)->pluck('id')->all();
            $this->deleteFields($fieldIds, $purged);
            $purged['farms'] += DB::table('farms')->whereIn('id', $chunk)->delete();
        }
    }

    /** @param array<string, int> $purged */
    private function purgeCrops(CarbonInterface $cutoff, array &$purged): void
    {
        $purged['crops'] += $this->expired('crops', $cutoff)
            ->whereNotExists(fn (Builder $q) => $q->from('crop_cycles')->whereColumn('crop_cycles.crop_id', 'crops.id'))
            ->delete();
    }

    /**
     * Delete the given cycles and everything still beneath them (all removed by construction).
     *
     * @param  list<int>  $ids
     * @param  array<string, int>  $purged
     */
    private function deleteCycles(array $ids, array &$purged): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $purged['planting_records'] += DB::table('planting_records')->whereIn('crop_cycle_id', $chunk)->delete();
            $purged['harvest_records'] += DB::table('harvest_records')->whereIn('crop_cycle_id', $chunk)->delete();
            $purged['crop_cycles'] += DB::table('crop_cycles')->whereIn('id', $chunk)->delete();
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, int>  $purged
     */
    private function deleteFields(array $ids, array &$purged): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $this->deleteCycles(DB::table('crop_cycles')->whereIn('field_id', $chunk)->pluck('id')->all(), $purged);
            $purged['fields'] += DB::table('fields')->whereIn('id', $chunk)->delete();
        }
    }

    private function expired(string $table, CarbonInterface $cutoff): Builder
    {
        return DB::table($table)->whereNotNull("{$table}.deleted_at")->where("{$table}.deleted_at", '<', $cutoff);
    }

    /** A live record of $table under a cycle whose parent condition is $on (raw, column names only). */
    private function liveRecordUnder(Builder $q, string $table, string $on, bool $throughFields = false): Builder
    {
        $q->from($table)
            ->join('crop_cycles', 'crop_cycles.id', '=', "{$table}.crop_cycle_id")
            ->whereNull("{$table}.deleted_at");

        if ($throughFields) {
            $q->join('fields', 'fields.id', '=', 'crop_cycles.field_id');
        }

        return $q->whereRaw($on);
    }
}
