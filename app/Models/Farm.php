<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant root of the agriculture domain (wiki.md §4). Every field, crop
 * cycle, planting record, and harvest record hangs off a Farm, and a
 * Farm belongs to exactly one Team — that is the tenant boundary.
 *
 * HasTeamScope keeps every query against this table scoped to the
 * current team by default (see app/Models/Concerns/HasTeamScope.php).
 *
 * Deleting a farm is soft and cascades softly: its fields, their crop
 * cycles and those cycles' planting/harvest records are soft-deleted in the
 * same transaction the caller opened (see FarmController::destroy).
 */
class Farm extends Model
{
    use HasFactory, HasTeamScope, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'location',
        'size_hectares',
        'status',
        'notes',
    ];

    protected $casts = [
        'size_hectares' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Farm $farm): void {
            if ($farm->isForceDeleting()) {
                return;
            }

            $farm->fields()->withoutGlobalScope('team')->get()->each->delete();
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function cropCycles(): HasManyThrough
    {
        return $this->hasManyThrough(CropCycle::class, Field::class);
    }

    /** What deleting this farm would soft-delete beneath it. @return array{fields: int, cycles: int, harvests: int} */
    public function deletionImpact(): array
    {
        $fieldIds = $this->fields()->select('fields.id');
        $cycleIds = CropCycle::query()->whereIn('field_id', $fieldIds)->select('crop_cycles.id');

        return [
            'fields' => $this->fields()->count(),
            'cycles' => CropCycle::query()->whereIn('field_id', $fieldIds)->count(),
            'harvests' => HarvestRecord::query()->whereIn('crop_cycle_id', $cycleIds)->count(),
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
