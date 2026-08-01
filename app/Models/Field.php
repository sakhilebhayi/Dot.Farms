<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A field/paddock within a Farm. Carries soil-type and moisture-zone
 * attributes per wiki.md §4. Tenancy is inherited via `farm->team_id`;
 * this table intentionally does not duplicate `team_id`.
 */
class Field extends Model
{
    use HasFactory;

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

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
