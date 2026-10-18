<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * A team-owned crop catalog entry (e.g. "Maize", "Tomato — Roma"),
 * reused across fields and seasons via CropCycle. Team-scoped directly
 * since a crop definition isn't owned by any single farm.
 *
 * HasTeamScope keeps every query against this table scoped to the
 * current team by default (see app/Models/Concerns/HasTeamScope.php).
 *
 * A crop that cycles use cannot be removed: delete() is refused while live
 * cycles reference it, and forceDelete() is refused while any cycle, live or
 * soft-deleted, ever did. History keeps naming its crop.
 */
class Crop extends Model
{
    use HasFactory, HasTeamScope, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'variety',
        'category',
        'typical_cycle_days',
    ];

    protected $casts = [
        'typical_cycle_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Crop $crop): void {
            $blocked = $crop->isForceDeleting() ? $crop->hasAnyCycleHistory() : $crop->isInUse();

            if ($blocked) {
                throw new RuntimeException("Crop \"{$crop->displayName()}\" is in use by crop cycles and cannot be removed.");
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /** True while any live (not deleted) crop cycle references this crop. */
    public function isInUse(): bool
    {
        return CropCycle::withoutGlobalScope('team')->where('crop_id', $this->id)->exists();
    }

    /** True if any cycle, including soft-deleted ones, ever referenced this crop. */
    public function hasAnyCycleHistory(): bool
    {
        return CropCycle::withoutGlobalScope('team')->withTrashed()->where('crop_id', $this->id)->exists();
    }

    public function displayName(): string
    {
        return $this->variety ? "{$this->name} — {$this->variety}" : $this->name;
    }
}
