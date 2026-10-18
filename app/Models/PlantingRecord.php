<?php

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\HasTeamChainScope;
use App\Support\Farms\TeamChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Operational log of a planting event against a CropCycle (wiki.md §4:
 * "Planting / harvest log | cycle + timestamp").
 *
 * Create, edit and delete through CropCycleLifecycle so the cycle's status
 * and planting date stay consistent with its records.
 */
class PlantingRecord extends Model
{
    use HasFactory, HasTeamChainScope, SoftDeletes;

    protected $fillable = [
        'crop_cycle_id',
        'planted_at',
        'quantity_planted',
        'unit',
        'method',
        'notes',
    ];

    protected $casts = [
        'planted_at' => 'datetime',
        'quantity_planted' => 'decimal:2',
    ];

    public function scopeForTeam(Builder $query, Team|int|null $team): Builder
    {
        $teamId = TeamChain::idOf($team);

        return $teamId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('planting_records.crop_cycle_id', TeamChain::cycleIds($teamId));
    }

    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /** Null for a legacy free-text unit that is not in the standard list. */
    public function unitEnum(): ?Unit
    {
        return Unit::tryFrom((string) $this->unit);
    }
}
