<?php

namespace App\Services\Farms;

use App\Enums\CropCycleStatus as Status;
use App\Exceptions\InvalidCycleTransition;
use App\Models\CropCycle;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place a crop cycle's status, planting date and closing facts change.
 *
 * Every public method runs in one transaction that first locks the cycle row,
 * so a record write and the status change it implies either both happen or
 * neither does, and two people acting on one cycle at once are serialised
 * (the lock is a no-op on SQLite, which serialises writers anyway).
 *
 * Rules, all enforced here rather than in controllers:
 *  - planting moves planned -> planted; further plantings are allowed while the
 *    cycle is open; planted_at always equals the earliest live planting;
 *  - harvests are accepted only while planted/growing, and may be partial;
 *  - closing as harvested is an explicit action and needs at least one harvest;
 *  - failed/abandoned need a reason; closed cycles can be re-opened;
 *  - editing or deleting records re-derives the cycle's facts: deleting the
 *    last harvest of a harvested cycle re-opens it, deleting the only planting
 *    of a harvest-less cycle sends it back to planned, and no change may leave
 *    a harvest dated before the earliest planting.
 *
 * Violations throw InvalidCycleTransition (a ValidationException), which the
 * HTTP layer renders as ordinary form errors.
 */
class CropCycleLifecycle
{
    /** @param array{planted_at: mixed, quantity_planted?: mixed, unit: string, method?: ?string, notes?: ?string} $data */
    public function plant(CropCycle $cycle, array $data): PlantingRecord
    {
        return $this->atomically($cycle, function (CropCycle $cycle) use ($data) {
            if ($cycle->status->isClosed()) {
                throw InvalidCycleTransition::because("This cycle is {$cycle->status->value}. Re-open it before recording a planting.");
            }

            $record = $cycle->plantingRecords()->create($data);
            $this->assertPlantingPrecedesHarvests($cycle);
            $this->syncPlantingFacts($cycle);

            return $record;
        });
    }

    /** @param array{planted_at: mixed, quantity_planted?: mixed, unit: string, method?: ?string, notes?: ?string} $data */
    public function updatePlanting(PlantingRecord $record, array $data): PlantingRecord
    {
        return $this->atomically($record->cropCycle, function (CropCycle $cycle) use ($record, $data) {
            $record->update($data);
            $this->assertPlantingPrecedesHarvests($cycle);
            $this->syncPlantingFacts($cycle);

            return $record;
        });
    }

    public function deletePlanting(PlantingRecord $record): void
    {
        $this->atomically($record->cropCycle, function (CropCycle $cycle) use ($record) {
            $record->delete();

            if ($cycle->plantingRecords()->doesntExist() && $cycle->harvestRecords()->exists()) {
                throw InvalidCycleTransition::because('This is the only planting and the cycle has harvests recorded. Delete the harvests first, or edit this planting instead.');
            }

            $this->assertPlantingPrecedesHarvests($cycle);
            $this->syncPlantingFacts($cycle);
        });
    }

    /** @param array{harvested_at: mixed, quantity_harvested: mixed, unit: string, quality_grade?: ?string, notes?: ?string} $data */
    public function recordHarvest(CropCycle $cycle, array $data): HarvestRecord
    {
        return $this->atomically($cycle, function (CropCycle $cycle) use ($data) {
            if (! $cycle->status->acceptsHarvests()) {
                throw InvalidCycleTransition::because(match ($cycle->status) {
                    Status::Planned => 'Record a planting before recording a harvest: this cycle has not been planted yet.',
                    Status::Harvested => 'This cycle is already closed as harvested. Re-open it to add more harvest, or edit an existing harvest to correct it.',
                    default => "This cycle is {$cycle->status->value}, so it cannot take a harvest. Re-open it first.",
                });
            }

            $this->assertHarvestNotBeforePlanting($cycle, $data['harvested_at']);

            return $cycle->harvestRecords()->create($data);
        });
    }

    /** @param array{harvested_at: mixed, quantity_harvested: mixed, unit: string, quality_grade?: ?string, notes?: ?string} $data */
    public function updateHarvest(HarvestRecord $record, array $data): HarvestRecord
    {
        return $this->atomically($record->cropCycle, function (CropCycle $cycle) use ($record, $data) {
            $this->assertHarvestNotBeforePlanting($cycle, $data['harvested_at']);
            $record->update($data);

            return $record;
        });
    }

    public function deleteHarvest(HarvestRecord $record): void
    {
        $this->atomically($record->cropCycle, function (CropCycle $cycle) use ($record) {
            $record->delete();

            // A cycle closed as harvested with nothing harvested is a lie: re-open it.
            if ($cycle->status === Status::Harvested && $cycle->harvestRecords()->doesntExist()) {
                $this->applyStatus($cycle, $this->restingStatus($cycle));
            }
        });
    }

    public function markGrowing(CropCycle $cycle): CropCycle
    {
        return $this->atomically($cycle, function (CropCycle $cycle) {
            $this->assertCanMoveTo($cycle, Status::Growing);
            $cycle->forceFill(['status' => Status::Growing, 'growing_at' => now()])->save();

            return $cycle;
        });
    }

    /** Close the cycle as harvested. Needs at least one harvest; partial harvests alone never close it. */
    public function close(CropCycle $cycle): CropCycle
    {
        return $this->atomically($cycle, function (CropCycle $cycle) {
            $this->assertCanMoveTo($cycle, Status::Harvested);

            if ($cycle->harvestRecords()->doesntExist()) {
                throw InvalidCycleTransition::because('Record at least one harvest before closing this cycle as harvested. If nothing was harvested, mark it failed or abandoned instead.');
            }

            $cycle->forceFill(['status' => Status::Harvested, 'closed_at' => now(), 'end_reason' => null])->save();

            return $cycle;
        });
    }

    public function fail(CropCycle $cycle, ?string $reason): CropCycle
    {
        return $this->end($cycle, Status::Failed, $reason);
    }

    public function abandon(CropCycle $cycle, ?string $reason): CropCycle
    {
        return $this->end($cycle, Status::Abandoned, $reason);
    }

    /** Re-open a closed cycle to the open state its recorded facts support. */
    public function reopen(CropCycle $cycle): CropCycle
    {
        return $this->atomically($cycle, function (CropCycle $cycle) {
            if ($cycle->status->isOpen()) {
                throw InvalidCycleTransition::because('This cycle is already open.');
            }

            $this->applyStatus($cycle, $this->restingStatus($cycle));

            return $cycle;
        });
    }

    /** The open status the facts support: never planted -> planned; had a growing stamp -> growing; else planted. */
    public function restingStatus(CropCycle $cycle): Status
    {
        if ($cycle->plantingRecords()->doesntExist()) {
            return Status::Planned;
        }

        return $cycle->growing_at !== null ? Status::Growing : Status::Planted;
    }

    private function end(CropCycle $cycle, Status $to, ?string $reason): CropCycle
    {
        return $this->atomically($cycle, function (CropCycle $cycle) use ($to, $reason) {
            $reason = trim((string) $reason);
            if ($reason === '') {
                throw InvalidCycleTransition::because("Say why this cycle was marked {$to->value}.", 'reason');
            }

            $this->assertCanMoveTo($cycle, $to);
            $cycle->forceFill(['status' => $to, 'closed_at' => now(), 'end_reason' => $reason])->save();

            return $cycle;
        });
    }

    private function applyStatus(CropCycle $cycle, Status $status): void
    {
        $cycle->forceFill([
            'status' => $status,
            'closed_at' => null,
            'end_reason' => null,
            'growing_at' => $status === Status::Growing ? $cycle->growing_at : null,
        ])->save();
    }

    private function assertCanMoveTo(CropCycle $cycle, Status $to): void
    {
        if (! $cycle->status->canTransitionTo($to)) {
            throw InvalidCycleTransition::because("A {$cycle->status->value} cycle cannot be marked {$to->value}.");
        }
    }

    /** Re-derive planted_at and the planned/planted pair from the live planting records. */
    private function syncPlantingFacts(CropCycle $cycle): void
    {
        $earliest = $cycle->plantingRecords()->min('planted_at');
        $plantedAt = $earliest ? Carbon::parse($earliest) : null;

        $cycle->planted_at = $plantedAt;

        if ($plantedAt && $cycle->status === Status::Planned) {
            $cycle->status = Status::Planted;
        } elseif (! $plantedAt && $cycle->status->isInSeason()) {
            $cycle->status = Status::Planned;
            $cycle->growing_at = null;
        }

        $cycle->save();
    }

    private function assertPlantingPrecedesHarvests(CropCycle $cycle): void
    {
        $firstPlanting = $cycle->plantingRecords()->min('planted_at');
        $firstHarvest = $cycle->harvestRecords()->min('harvested_at');

        if ($firstPlanting && $firstHarvest && Carbon::parse($firstPlanting)->startOfDay()->gt(Carbon::parse($firstHarvest)->startOfDay())) {
            throw InvalidCycleTransition::because('A planting cannot be dated after a harvest already recorded on this cycle ('.Carbon::parse($firstHarvest)->format('M j, Y').').', 'planted_at');
        }
    }

    private function assertHarvestNotBeforePlanting(CropCycle $cycle, mixed $harvestedAt): void
    {
        $firstPlanting = $cycle->plantingRecords()->min('planted_at');

        if ($firstPlanting && Carbon::parse($harvestedAt)->startOfDay()->lt(Carbon::parse($firstPlanting)->startOfDay())) {
            throw InvalidCycleTransition::because('A harvest cannot be dated before the planting ('.Carbon::parse($firstPlanting)->format('M j, Y').').', 'harvested_at');
        }
    }

    /**
     * Run $work in a transaction against the locked, freshly read cycle, then
     * bring the caller's instance up to date.
     *
     * @template T
     *
     * @param  callable(CropCycle): T  $work
     * @return T
     */
    private function atomically(CropCycle $cycle, callable $work): mixed
    {
        $result = DB::transaction(function () use ($cycle, $work) {
            $locked = CropCycle::withoutGlobalScope('team')->whereKey($cycle->getKey())->lockForUpdate()->firstOrFail();

            return $work($locked);
        });

        $cycle->refresh();

        return $result;
    }
}
