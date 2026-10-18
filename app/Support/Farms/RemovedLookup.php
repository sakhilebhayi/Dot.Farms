<?php

namespace App\Support\Farms;

use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds the parents of removed records, deleted or not.
 *
 * Every lookup switches ALL global scopes off (that drops both the soft-delete
 * filter and the team-chain filter, which refuses to pass through a deleted
 * parent), so a removed field is found under its removed farm. That is safe
 * because it is only ever asked about the parents of rows that RemovedRecords
 * already selected through the team's own chain; it never starts from an id
 * the caller supplied.
 */
final class RemovedLookup
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'farm' => Farm::class,
        'field' => Field::class,
        'crop' => Crop::class,
        'cycle' => CropCycle::class,
        'planting' => PlantingRecord::class,
        'harvest' => HarvestRecord::class,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'farm' => 'farm',
        'field' => 'field',
        'crop' => 'crop',
        'cycle' => 'crop cycle',
        'planting' => 'planting record',
        'harvest' => 'harvest record',
    ];

    /** @var array<string, array<int, Model>> */
    private array $memo = ['farm' => [], 'field' => [], 'crop' => [], 'cycle' => []];

    /** Load the ancestors of a page of records in a handful of queries instead of one per row. @param iterable<Model> $records */
    public function preload(iterable $records): void
    {
        $cycleIds = $fieldIds = $farmIds = $cropIds = [];
        foreach ($records as $record) {
            match (true) {
                $record instanceof PlantingRecord, $record instanceof HarvestRecord => $cycleIds[] = $record->crop_cycle_id,
                $record instanceof CropCycle => $cycleIds[] = $record->id,
                $record instanceof Field => $fieldIds[] = $record->id,
                $record instanceof Farm => $farmIds[] = $record->id,
                default => null,
            };
        }

        $this->fill('cycle', CropCycle::class, $cycleIds);
        $cycles = array_intersect_key($this->memo['cycle'], array_flip($cycleIds));
        $this->fill('crop', Crop::class, array_map(fn ($c) => $c->crop_id, $cycles));
        $this->fill('field', Field::class, [...$fieldIds, ...array_map(fn ($c) => $c->field_id, $cycles)]);
        $fields = array_intersect_key($this->memo['field'], array_flip([...$fieldIds, ...array_map(fn ($c) => $c->field_id, $cycles)]));
        $this->fill('farm', Farm::class, [...$farmIds, ...array_map(fn ($f) => $f->farm_id, $fields)]);
    }

    public function farm(?int $id): ?Farm
    {
        return $this->one('farm', Farm::class, $id);
    }

    public function field(?int $id): ?Field
    {
        return $this->one('field', Field::class, $id);
    }

    public function crop(?int $id): ?Crop
    {
        return $this->one('crop', Crop::class, $id);
    }

    public function cycle(?int $id): ?CropCycle
    {
        return $this->one('cycle', CropCycle::class, $id);
    }

    /**
     * The removed ancestor to restore first, or null when every parent is live.
     * The topmost removed ancestor wins (farm, then field, then cycle), because
     * restoring it brings back what was removed with it; a cycle's crop is the
     * one parent outside that chain and is named last.
     */
    public function blocker(string $type, Model $record): ?RemovedAncestor
    {
        $cycle = match ($type) {
            'planting', 'harvest' => $this->cycle($record->crop_cycle_id),
            'cycle' => $record,
            default => null,
        };
        $field = match ($type) {
            'field' => $record,
            'cycle', 'planting', 'harvest' => $this->field($cycle?->field_id),
            default => null,
        };
        $farm = $field === null ? null : $this->farm($field->farm_id);

        if ($farm?->trashed()) {
            return new RemovedAncestor('farm', $farm, $farm->name);
        }

        if ($type !== 'field' && $field?->trashed()) {
            return new RemovedAncestor('field', $field, $field->name);
        }

        if (($type === 'planting' || $type === 'harvest') && $cycle?->trashed()) {
            return new RemovedAncestor('cycle', $cycle, $this->cycleTitle($cycle));
        }

        if ($type === 'cycle') {
            $crop = $this->crop($record->crop_id);

            if ($crop?->trashed()) {
                return new RemovedAncestor('crop', $crop, $crop->displayName());
            }
        }

        return null;
    }

    public function cycleTitle(CropCycle $cycle): string
    {
        $crop = $this->crop($cycle->crop_id);

        return ($crop?->displayName() ?? 'Crop').' · '.$cycle->season;
    }

    /** "Farm › Field › Crop · Season" for whatever is above the record. */
    public function path(string $type, Model $record): string
    {
        $cycle = match ($type) {
            'planting', 'harvest' => $this->cycle($record->crop_cycle_id),
            'cycle' => $record,
            default => null,
        };
        $field = $type === 'field' ? $record : $this->field($cycle?->field_id);
        $farm = $this->farm($field?->farm_id);

        return implode(' › ', array_filter([
            $farm?->name,
            $type === 'field' ? null : $field?->name,
            in_array($type, ['planting', 'harvest'], true) && $cycle ? $this->cycleTitle($cycle) : null,
        ]));
    }

    /** @param list<int|null> $ids @param class-string<Model> $model */
    private function fill(string $key, string $model, array $ids): void
    {
        $missing = array_values(array_diff(array_unique(array_filter($ids)), array_keys($this->memo[$key])));
        if ($missing === []) {
            return;
        }

        foreach ($model::withoutGlobalScopes()->whereIn('id', $missing)->get() as $row) {
            $this->memo[$key][$row->id] = $row;
        }
    }

    /** @param class-string<Model> $model */
    private function one(string $key, string $model, ?int $id): ?Model
    {
        if ($id === null) {
            return null;
        }

        return $this->memo[$key][$id] ??= $model::withoutGlobalScopes()->find($id);
    }
}
