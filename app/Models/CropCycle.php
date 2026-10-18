<?php

namespace App\Models;

use App\Enums\CropCycleStatus;
use App\Models\Concerns\HasTeamChainScope;
use App\Support\Farms\TeamChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The planting -> harvest lifecycle for one crop on one field in one
 * season (wiki.md §4: "Crop cycle | field + season + crop"). `season`
 * is a first-class column here — see the note in the
 * 2026_08_01_100002_create_agriculture_tables migration for why.
 *
 * `status` is a CropCycleStatus and must only change through
 * App\Services\Farms\CropCycleLifecycle, which owns the state machine and
 * wraps every record + status change in one transaction.
 */
class CropCycle extends Model
{
    use HasFactory, HasTeamChainScope, SoftDeletes;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PLANTED = 'planted';

    public const STATUS_GROWING = 'growing';

    public const STATUS_HARVESTED = 'harvested';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'field_id',
        'crop_id',
        'season',
        'status',
        'planted_at',
        'growing_at',
        'expected_harvest_at',
        'closed_at',
        'end_reason',
        'notes',
    ];

    protected $casts = [
        'status' => CropCycleStatus::class,
        'planted_at' => 'datetime',
        'growing_at' => 'datetime',
        'expected_harvest_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CropCycle $cycle): void {
            if ($cycle->isForceDeleting()) {
                return;
            }

            $cycle->plantingRecords()->withoutGlobalScope('team')->get()->each->delete();
            $cycle->harvestRecords()->withoutGlobalScope('team')->get()->each->delete();
        });
    }

    public function scopeForTeam(Builder $query, Team|int|null $team): Builder
    {
        $teamId = TeamChain::idOf($team);

        return $teamId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('crop_cycles.field_id', TeamChain::fieldIds($teamId));
    }

    /** Cycles that are not yet closed (planned, planted or growing). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('crop_cycles.status', CropCycleStatus::openValues());
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /** A crop may be soft-deleted once no live cycle uses it; history keeps naming it. */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class)->withTrashed();
    }

    public function plantingRecords(): HasMany
    {
        return $this->hasMany(PlantingRecord::class);
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    /** What deleting this cycle would soft-delete beneath it. @return array{plantings: int, harvests: int} */
    public function deletionImpact(): array
    {
        return ['plantings' => $this->plantingRecords()->count(), 'harvests' => $this->harvestRecords()->count()];
    }

    /** In-season means it's been planted but not yet closed (planted or growing). */
    public function isInSeason(): bool
    {
        return $this->status->isInSeason();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /** Past its expected harvest date and still open. */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->expected_harvest_at !== null
            && $this->expected_harvest_at->isPast();
    }
}
