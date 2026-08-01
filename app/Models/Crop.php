<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team-owned crop catalog entry (e.g. "Maize", "Tomato — Roma"),
 * reused across fields and seasons via CropCycle. Team-scoped directly
 * since a crop definition isn't owned by any single farm.
 */
class Crop extends Model
{
    use HasFactory;

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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    public function displayName(): string
    {
        return $this->variety ? "{$this->name} — {$this->variety}" : $this->name;
    }
}
