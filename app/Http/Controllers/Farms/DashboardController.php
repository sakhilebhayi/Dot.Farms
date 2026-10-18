<?php

namespace App\Http\Controllers\Farms;

use App\Enums\CropCycleStatus;
use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Services\Farms\FarmReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const WINDOW_DAYS = 30;

    public function __invoke(Request $request, FarmReports $reports): View|RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return redirect()->route('teams.create');
        }

        $inSeason = CropCycle::query()->forTeam($team)->whereIn('crop_cycles.status', [CropCycleStatus::Planted->value, CropCycleStatus::Growing->value]);

        return view('dashboard', [
            'farmCount' => Farm::query()->forTeam($team)->count(),
            'activeFieldCount' => Field::query()->forTeam($team)->where('fields.status', 'active')->count(),
            'cropsInSeasonCount' => (clone $inSeason)->count(),
            'overdueCount' => (clone $inSeason)->where('crop_cycles.expected_harvest_at', '<', now())->count(),
            'windowDays' => self::WINDOW_DAYS,
            'window' => $reports->harvestTotalSince($team, now()->subDays(self::WINDOW_DAYS)),
            'recentHarvests' => HarvestRecord::query()->forTeam($team)
                ->with(['cropCycle.crop', 'cropCycle.field.farm'])
                ->latest('harvested_at')
                ->latest('id')
                ->take(6)
                ->get(),
        ]);
    }
}
