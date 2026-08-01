<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The planting -> harvest lifecycle for one crop on one field in one
 * season (wiki.md §4: "Crop cycle | field + season + crop"). `season`
 * is a first-class column here — see the note in the
 * 2026_08_01_100002_create_agriculture_tables migration for why.
 */
class CropCycle extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_PLANTED = 'planted';

    public const STATUS_GROWING = 'growing';

    public const STATUS_HARVESTED = 'harvested';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'field_id',
        'crop_id',
        'season',
        'status',
        'planted_at',
        'expected_harvest_at',
        'notes',
    ];

    protected $casts = [
        'planted_at' => 'datetime',
        'expected_harvest_at' => 'datetime',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function plantingRecords(): HasMany
    {
        return $this->hasMany(PlantingRecord::class);
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    /** In-season means it's been planted but not yet fully harvested or marked failed. */
    public function isInSeason(): bool
    {
        return in_array($this->status, [self::STATUS_PLANTED, self::STATUS_GROWING], true);
    }
}
