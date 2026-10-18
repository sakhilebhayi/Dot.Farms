<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\HarvestRecord;
use App\Services\Farms\FarmReports;
use App\Services\Farms\HarvestFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, FarmReports $reports): View
    {
        Gate::authorize('viewAny', HarvestRecord::class);

        $team = $request->user()->currentTeam;
        $filters = HarvestFilters::fromRequest($request);

        $yields = $reports->yieldByFieldSeason($team, $filters->season);
        $byMonth = $reports->harvestByCropMonth($team, new HarvestFilters(season: $filters->season));
        $outstanding = $reports->outstandingCycles($team, $filters->season);

        return view('reports.index', [
            'season' => $filters->season,
            'seasons' => HarvestFilters::seasonsFor($team->id),
            'yieldsBySeason' => $yields->groupBy('season'),
            'byMonth' => $byMonth,
            'outstanding' => $outstanding,
            'hasAnything' => $yields->isNotEmpty() || $byMonth->isNotEmpty() || $outstanding->isNotEmpty(),
            'hasHarvests' => $byMonth->isNotEmpty(),
        ]);
    }
}
