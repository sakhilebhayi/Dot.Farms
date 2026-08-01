<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operational log of a planting event against a CropCycle (wiki.md §4:
 * "Planting / harvest log | cycle + timestamp").
 */
class PlantingRecord extends Model
{
    use HasFactory;

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

    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }
}
