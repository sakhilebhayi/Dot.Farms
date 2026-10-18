<?php

namespace App\Http\Controllers\Farms;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Models\HarvestRecord;
use App\Services\Farms\HarvestFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the team's harvest records, honouring the same filters as
 * the harvest list. Streams row by row from a cursor, so a large history does
 * not have to fit in memory.
 */
class HarvestExportController extends Controller
{
    public const HEADER = [
        'harvest_id', 'harvested_on', 'farm', 'field_code', 'field', 'crop', 'variety',
        'season', 'quantity', 'unit', 'quantity_kg', 'quality_grade', 'notes', 'recorded_at',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('export', HarvestRecord::class);

        $team = $request->user()->currentTeam;
        $filters = HarvestFilters::fromRequest($request);

        $query = $filters->apply(HarvestRecord::query()->forTeam($team))
            ->join('crop_cycles', 'crop_cycles.id', '=', 'harvest_records.crop_cycle_id')
            ->join('fields', 'fields.id', '=', 'crop_cycles.field_id')
            ->join('farms', 'farms.id', '=', 'fields.farm_id')
            ->join('crops', 'crops.id', '=', 'crop_cycles.crop_id')
            ->select([
                'harvest_records.id', 'harvest_records.harvested_at', 'harvest_records.quantity_harvested',
                'harvest_records.unit', 'harvest_records.quality_grade', 'harvest_records.notes', 'harvest_records.created_at',
                'farms.name as farm_name', 'fields.code as field_code', 'fields.name as field_name',
                'crops.name as crop_name', 'crops.variety as crop_variety', 'crop_cycles.season as season',
            ])
            ->orderBy('harvest_records.harvested_at')
            ->orderBy('harvest_records.id');

        $filename = 'dot-farms-harvests-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, self::HEADER, ',', '"', '');

            foreach ($query->cursor() as $row) {
                $unit = Unit::tryFrom((string) $row->unit);
                $kg = $unit && $unit->family() === 'weight' ? (float) $row->quantity_harvested * $unit->factorToBase() : null;

                fputcsv($out, [
                    $row->id,
                    substr((string) $row->harvested_at, 0, 10),
                    self::safe($row->farm_name),
                    self::safe($row->field_code),
                    self::safe($row->field_name),
                    self::safe($row->crop_name),
                    self::safe($row->crop_variety),
                    self::safe($row->season),
                    $row->quantity_harvested,
                    self::safe($row->unit),
                    $kg === null ? '' : rtrim(rtrim(number_format($kg, 3, '.', ''), '0'), '.'),
                    self::safe($row->quality_grade),
                    self::safe($row->notes),
                    substr((string) $row->created_at, 0, 19),
                ], ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection: a text cell must never start as a formula. */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
