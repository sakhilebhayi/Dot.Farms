<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Tenant root of the agriculture domain (wiki.md §4). Every field, crop
 * cycle, planting record, and harvest record hangs off a Farm, and a
 * Farm belongs to exactly one Team — that is the tenant boundary.
 */
class Farm extends Model
{
    use HasFactory;

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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
