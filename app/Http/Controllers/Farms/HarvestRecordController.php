<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HarvestRecordController extends Controller
{
    /**
     * Team-wide list of harvest records across all farms, newest first.
     */
    public function index(Request $request): View
    {
        // whereHas builds its subquery against Farm, so Farm's HasTeamScope
        // global scope already limits this to the current team -- no need
        // to add an explicit team_id constraint here.
        $harvestRecords = HarvestRecord::query()
            ->whereHas('cropCycle.field.farm')
            ->with(['cropCycle.crop', 'cropCycle.field.farm'])
            ->latest('harvested_at')
            ->paginate(20);

        return view('harvests.index', [
            'harvestRecords' => $harvestRecords,
        ]);
    }

    public function show(HarvestRecord $harvestRecord): View
    {
        $harvestRecord->load(['cropCycle.crop', 'cropCycle.field.farm']);

        Gate::authorize('view', $harvestRecord->cropCycle->field->farm);

        return view('harvests.show', [
            'harvestRecord' => $harvestRecord,
        ]);
    }

    /**
     * Record a harvest event against a crop cycle and mark it harvested.
     * This is the MVP stand-in for the `agriculture.harvest.recorded`
     * event described in wiki.md §5 — no outbound event publishing yet.
     */
    public function store(Request $request, Farm $farm, Field $field, CropCycle $cropCycle): RedirectResponse
    {
        Gate::authorize('update', $farm);
        abort_unless($field->farm_id === $farm->id, 404);
        abort_unless($cropCycle->field_id === $field->id, 404);

        $validated = $request->validate([
            'harvested_at' => ['required', 'date'],
            'quantity_harvested' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'quality_grade' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $cropCycle->harvestRecords()->create($validated);

        $cropCycle->update(['status' => CropCycle::STATUS_HARVESTED]);

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Harvest recorded.');
    }
}
