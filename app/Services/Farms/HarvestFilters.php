<?php

namespace App\Services\Farms;

use App\Models\CropCycle;
use App\Models\HarvestRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The filters shared by the harvest list, its totals and the CSV export, so
 * the three always agree about which records they are talking about.
 */
final class HarvestFilters
{
    public function __construct(
        public readonly ?string $season = null,
        public readonly ?int $cropId = null,
        public readonly ?int $farmId = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $valid = $request->validate([
            'season' => ['nullable', 'string', 'max:100'],
            'crop_id' => ['nullable', 'integer'],
            'farm_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return new self(
            $valid['season'] ?? null ?: null,
            isset($valid['crop_id']) ? (int) $valid['crop_id'] : null,
            isset($valid['farm_id']) ? (int) $valid['farm_id'] : null,
            $valid['from'] ?? null ?: null,
            $valid['to'] ?? null ?: null,
        );
    }

    public function isActive(): bool
    {
        return $this->toQuery() !== [];
    }

    /** @return array<string, string|int> only the filters that are set, for building links. */
    public function toQuery(): array
    {
        return array_filter([
            'season' => $this->season,
            'crop_id' => $this->cropId,
            'farm_id' => $this->farmId,
            'from' => $this->from,
            'to' => $this->to,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @param  Builder<HarvestRecord>  $query */
    public function apply(Builder $query): Builder
    {
        if ($this->season !== null || $this->cropId !== null || $this->farmId !== null) {
            $query->whereIn('harvest_records.crop_cycle_id', function ($sub) {
                $sub->from('crop_cycles')->select('crop_cycles.id')
                    ->when($this->season !== null, fn ($q) => $q->where('crop_cycles.season', $this->season))
                    ->when($this->cropId !== null, fn ($q) => $q->where('crop_cycles.crop_id', $this->cropId))
                    ->when($this->farmId !== null, fn ($q) => $q->whereIn(
                        'crop_cycles.field_id',
                        fn ($f) => $f->from('fields')->select('fields.id')->where('fields.farm_id', $this->farmId)
                    ));
            });
        }

        return $query
            ->when($this->from !== null, fn ($q) => $q->where('harvest_records.harvested_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== null, fn ($q) => $q->where('harvest_records.harvested_at', '<=', $this->to.' 23:59:59'));
    }

    /** Distinct seasons the team has cycles for, newest first. @return list<string> */
    public static function seasonsFor(int $teamId): array
    {
        return CropCycle::query()->forTeam($teamId)->distinct()->orderByDesc('season')->pluck('season')->all();
    }
}
