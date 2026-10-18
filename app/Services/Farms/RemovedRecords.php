<?php

namespace App\Services\Farms;

use App\Exceptions\RestoreRefused;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\PlantingRecord;
use App\Models\RecordRestoration;
use App\Models\Team;
use App\Models\User;
use App\Support\Farms\RemovedItem;
use App\Support\Farms\RemovedLookup;
use App\Support\Farms\RestoreResult;
use App\Support\Farms\TeamChain;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lists a team's soft-deleted records and brings them back.
 *
 * Reaching removed rows. The global team scopes cannot be used: the chain scope
 * on fields, cycles and records refuses to pass through a deleted parent, which
 * is exactly where removed children live. So every query here switches the
 * scopes off and re-applies the team explicitly, through TeamChain with
 * $withTrashed (farms.team_id is still the only way in). Nothing starts from a
 * caller-supplied id without that team condition, so another company's removed
 * record is simply not found.
 *
 * Restoring. A record cannot come back while an ancestor is still removed
 * (RemovedLookup::blocker names the one to restore first). Restoring a farm,
 * field or cycle also brings back what was removed in the same delete: its
 * descendants whose deleted_at is within CASCADE_WINDOW_SECONDS of its own, which
 * is how a soft-delete cascade stamps them. A child someone had removed earlier on
 * its own stays removed. Cycle facts are re-derived by CropCycleLifecycle, and
 * every restore writes one append-only RecordRestoration row.
 */
class RemovedRecords
{
    /** A cascade stamps parent and children within the same request; this is the slack allowed for that. */
    public const CASCADE_WINDOW_SECONDS = 5;

    public const PER_PAGE = 20;

    public function __construct(private readonly CropCycleLifecycle $lifecycle) {}

    public static function isType(mixed $type): bool
    {
        return is_string($type) && array_key_exists($type, RemovedLookup::TYPES);
    }

    /** Removed rows of one type that belong to $team, scopes off, newest removal not yet ordered. @return Builder<Model> */
    public function query(string $type, Team|int|null $team): Builder
    {
        $model = RemovedLookup::TYPES[$type];
        $teamId = TeamChain::idOf($team);
        $query = $model::withoutGlobalScopes()->onlyTrashed();

        if ($teamId === null) {
            return $query->whereRaw('1 = 0');
        }

        return match ($type) {
            'farm' => $query->where('farms.team_id', $teamId),
            'crop' => $query->where('crops.team_id', $teamId),
            'field' => $query->whereIn('fields.farm_id', TeamChain::farmIds($teamId, true)),
            'cycle' => $query->whereIn('crop_cycles.field_id', TeamChain::fieldIds($teamId, true)),
            'planting' => $query->whereIn('planting_records.crop_cycle_id', TeamChain::cycleIds($teamId, true)),
            'harvest' => $query->whereIn('harvest_records.crop_cycle_id', TeamChain::cycleIds($teamId, true)),
        };
    }

    public function find(string $type, int $id, Team|int|null $team): ?Model
    {
        return $this->query($type, $team)->whereKey($id)->first();
    }

    /** @return array<string, int> removed rows per type, for the filter chips */
    public function counts(Team|int|null $team): array
    {
        $counts = [];
        foreach (array_keys(RemovedLookup::TYPES) as $type) {
            $counts[$type] = $this->query($type, $team)->count();
        }

        return $counts;
    }

    /** One page of removed records across the chosen type (or every type), newest removal first. */
    public function page(Team|int|null $team, ?string $type = null, ?string $search = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $union = null;
        foreach ($type === null ? array_keys(RemovedLookup::TYPES) : [$type] as $each) {
            $table = (new (RemovedLookup::TYPES[$each]))->getTable();
            $query = $this->query($each, $team);
            $this->search($query, $each, $search);

            $part = $query->toBase()
                ->selectRaw("cast('{$each}' as varchar(16)) as type")
                ->addSelect("{$table}.id as id", "{$table}.deleted_at as deleted_at");

            $union = $union === null ? $part : $union->unionAll($part);
        }

        $page = DB::query()->fromSub($union, 'removed')
            ->select('type', 'id', 'deleted_at')
            ->orderByDesc('deleted_at')
            ->orderBy('type')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $page->setCollection($this->hydrate($page->getCollection(), $team));
    }

    /**
     * Bring a removed record back, with what was removed alongside it.
     *
     * @throws RestoreRefused when an ancestor is still removed or the cycle rules say no
     * @throws ModelNotFoundException when the record is not (or no longer) removed for this team
     */
    public function restore(Model $record, string $type, Team $team, User $by): RestoreResult
    {
        try {
            return DB::transaction(function () use ($record, $type, $team, $by) {
                $locked = $this->query($type, $team)->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                $lookup = new RemovedLookup;

                if ($blocker = $lookup->blocker($type, $locked)) {
                    throw RestoreRefused::parentRemoved(RemovedLookup::LABELS[$type], $this->title($type, $locked, $lookup), $blocker->label(), $blocker->name);
                }

                $label = $this->title($type, $locked, $lookup);
                $result = match ($type) {
                    'farm', 'field', 'cycle' => $this->restoreBranch($type, $locked, $label),
                    'crop' => $this->restoreSingle($type, $locked, $label),
                    'planting' => $this->restoreSingle($type, $locked, $label, fn () => $this->lifecycle->restorePlanting($locked)),
                    'harvest' => $this->restoreSingle($type, $locked, $label, fn () => $this->lifecycle->restoreHarvest($locked)),
                };

                RecordRestoration::create([
                    'team_id' => $team->id,
                    'user_id' => $by->id,
                    'record_type' => $type,
                    'record_id' => $locked->getKey(),
                    'record_label' => mb_substr($label, 0, 255),
                    'restored_with' => $result->restoredWith(),
                ]);

                return $result;
            });
        } catch (ValidationException $e) {
            // A cycle rule (InvalidCycleTransition) reads best under the same key as every other refusal.
            throw $e instanceof RestoreRefused ? $e : RestoreRefused::because($e->validator->errors()->first());
        }
    }

    private function restoreSingle(string $type, Model $record, string $label, ?\Closure $through = null): RestoreResult
    {
        if ($through) {
            $through();
        } else {
            $record->restore();
        }

        return new RestoreResult($type, $label);
    }

    private function restoreBranch(string $type, Model $root, string $label): RestoreResult
    {
        $anchor = $root->deleted_at;
        $fields = $cycles = $records = $skipped = 0;
        $cycleIds = [];

        if ($type === 'field') {
            $this->assertCodeFree($root);
        }

        $root->restore();

        $fieldIds = match ($type) {
            'farm' => $this->restoreWithin(Field::class, 'farm_id', [$root->id], $anchor, $fields),
            'field' => [$root->id],
            default => [],
        };

        if ($type === 'cycle') {
            $cycleIds = [$root->id];
        } else {
            $trashed = $this->withinWindow(CropCycle::withoutGlobalScopes()->onlyTrashed()->whereIn('field_id', $fieldIds), $anchor)->get();
            $cropGone = Crop::withoutGlobalScopes()->onlyTrashed()->whereIn('id', $trashed->pluck('crop_id')->unique())->pluck('id')->all();

            foreach ($trashed as $cycle) {
                if (in_array($cycle->crop_id, $cropGone, true)) {
                    $skipped++;

                    continue;
                }
                $cycle->restore();
                $cycleIds[] = $cycle->id;
                $cycles++;
            }
        }

        foreach ([PlantingRecord::class, HarvestRecord::class] as $model) {
            $records += $this->withinWindow($model::withoutGlobalScopes()->onlyTrashed()->whereIn('crop_cycle_id', $cycleIds), $anchor)->restore();
        }

        foreach (CropCycle::withoutGlobalScopes()->whereIn('id', $cycleIds)->get() as $cycle) {
            $this->lifecycle->reconcile($cycle);
        }

        return new RestoreResult($type, $label, $fields, $cycles, $records, $skipped);
    }

    /**
     * Restore the trashed $model rows under $parentIds that were removed with the parent.
     *
     * @param  class-string<Model>  $model
     * @param  list<int>  $parentIds
     * @return list<int>
     */
    private function restoreWithin(string $model, string $parentColumn, array $parentIds, Carbon $anchor, int &$count): array
    {
        $ids = [];
        foreach ($this->withinWindow($model::withoutGlobalScopes()->onlyTrashed()->whereIn($parentColumn, $parentIds), $anchor)->get() as $row) {
            $row->restore();
            $ids[] = $row->id;
            $count++;
        }

        return $ids;
    }

    private function withinWindow(Builder $query, Carbon $anchor): Builder
    {
        return $query->whereBetween('deleted_at', [
            $anchor->copy()->subSeconds(self::CASCADE_WINDOW_SECONDS),
            $anchor->copy()->addSeconds(self::CASCADE_WINDOW_SECONDS),
        ]);
    }

    /** A field's code is unique among its farm's live fields, so a restore can collide with a field made since. */
    private function assertCodeFree(Field $field): void
    {
        $holder = Field::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('farm_id', $field->farm_id)
            ->where('code', $field->code)
            ->whereKeyNot($field->id)
            ->first();

        if ($holder) {
            throw RestoreRefused::because("The field \"{$field->name}\" cannot be restored: the code \"{$field->code}\" is now used by the field \"{$holder->name}\" on the same farm. Rename or remove that field first.");
        }
    }

    private function search(Builder $query, string $type, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $table = (new (RemovedLookup::TYPES[$type]))->getTable();
        $columns = [
            'farm' => ['name', 'location'],
            'field' => ['name', 'code'],
            'crop' => ['name', 'variety', 'category'],
            'cycle' => ['season', 'notes'],
            'planting' => ['method', 'notes', 'unit'],
            'harvest' => ['quality_grade', 'notes', 'unit'],
        ][$type];

        $query->where(function (Builder $q) use ($columns, $table, $like, $type) {
            foreach ($columns as $column) {
                $q->orWhereRaw("lower({$table}.{$column}) like ? escape '!'", [$like]);
            }

            if ($type === 'cycle') {
                $q->orWhereIn('crop_cycles.crop_id', Crop::withoutGlobalScopes()->select('crops.id')
                    ->whereRaw("lower(crops.name) like ? escape '!'", [$like])
                    ->orWhereRaw("lower(crops.variety) like ? escape '!'", [$like]));
            }
        });
    }

    /**
     * Turn the raw (type, id, deleted_at) rows of a page into items with their words and blocker.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, RemovedItem>
     */
    private function hydrate($rows, Team|int|null $team)
    {
        $models = [];
        foreach ($rows->groupBy('type') as $type => $group) {
            $models[$type] = $this->query($type, $team)->whereIn((new (RemovedLookup::TYPES[$type]))->getTable().'.id', $group->pluck('id')->all())->get()->keyBy('id');
        }

        $lookup = new RemovedLookup;
        $lookup->preload(collect($models)->flatten());

        return $rows->map(function ($row) use ($models, $lookup) {
            $record = $models[$row->type][$row->id] ?? null;

            return $record === null ? null : new RemovedItem(
                $row->type,
                $record,
                $this->title($row->type, $record, $lookup),
                $lookup->path($row->type, $record),
                $record->deleted_at,
                $lookup->blocker($row->type, $record),
            );
        })->filter()->values();
    }

    /** The words that name a record on the screen and in the audit row. */
    public function title(string $type, Model $record, RemovedLookup $lookup): string
    {
        return match ($type) {
            'farm', 'field' => $record->name,
            'crop' => $record->displayName(),
            'cycle' => $lookup->cycleTitle($record),
            'planting' => 'Planting, '.$record->planted_at?->format('M j, Y').($record->quantity_planted !== null ? ' · '.$this->quantity($record->quantity_planted, $record->unit) : ''),
            'harvest' => 'Harvest, '.$record->harvested_at?->format('M j, Y').' · '.$this->quantity($record->quantity_harvested, $record->unit),
        };
    }

    private function quantity(mixed $amount, ?string $unit): string
    {
        return rtrim(rtrim(number_format((float) $amount, 2), '0'), '.').' '.$unit;
    }
}
