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
 * Operational log of a harvest event against a CropCycle (wiki.md §4:
 * "Planting / harvest log | cycle + timestamp"; also the ground-truth
 * source for the "Yield record" entity at MVP scope — a dedicated
 * yield/forecast-verification model is out of scope for this pass, see
 * wiki.md roadmap).
 *
 * Committing a harvest record is the trigger point wiki.md §2 and §5
 * describe as handing off the commercial lifecycle to Dot.Emall /
 * Dot.Billing (`agriculture.harvest.recorded`). No outbound event is
 * actually published yet — that integration is future work.
 *
 * Create, edit and delete through CropCycleLifecycle so the cycle's status
 * stays consistent with its records.
 */
class HarvestRecord extends Model
{
    use HasFactory, HasTeamChainScope, SoftDeletes;

    protected $fillable = [
        'crop_cycle_id',
        'harvested_at',
        'quantity_harvested',
        'unit',
        'quality_grade',
        'notes',
    ];

    protected $casts = [
        'harvested_at' => 'datetime',
        'quantity_harvested' => 'decimal:2',
    ];

    public function scopeForTeam(Builder $query, Team|int|null $team): Builder
    {
        $teamId = TeamChain::idOf($team);

        return $teamId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('harvest_records.crop_cycle_id', TeamChain::cycleIds($teamId));
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
