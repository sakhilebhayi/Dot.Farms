<?php

namespace App\Models;

use App\Models\Concerns\HasTeamChainScope;
use App\Support\Farms\TeamChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field/paddock within a Farm. Carries soil-type and moisture-zone
 * attributes per wiki.md §4. Tenancy is inherited via `farm->team_id`;
 * this table intentionally does not duplicate `team_id`, so
 * HasTeamChainScope applies the farm's team to every query instead.
 */
class Field extends Model
{
    use HasFactory, HasTeamChainScope, SoftDeletes;

    protected $table = 'fields';

    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'soil_type',
        'moisture_zone',
        'size_hectares',
        'status',
    ];

    protected $casts = [
        'size_hectares' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Field $field): void {
            if ($field->isForceDeleting()) {
                return;
            }

            $field->cropCycles()->withoutGlobalScope('team')->get()->each->delete();
        });
    }

    public function scopeForTeam(Builder $query, Team|int|null $team): Builder
    {
        $teamId = TeamChain::idOf($team);

        return $teamId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('fields.farm_id', TeamChain::farmIds($teamId));
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /** What deleting this field would soft-delete beneath it. @return array{cycles: int, harvests: int} */
    public function deletionImpact(): array
    {
        return [
            'cycles' => $this->cropCycles()->count(),
            'harvests' => HarvestRecord::query()->whereIn('crop_cycle_id', $this->cropCycles()->select('crop_cycles.id'))->count(),
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
