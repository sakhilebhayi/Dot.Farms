<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CropCycleController extends Controller
{
    public function create(Farm $farm, Field $field): View
    {
        Gate::authorize('update', $farm);
        abort_unless($field->farm_id === $farm->id, 404);

        // $farm is only reachable here if it's in the current team (Farm's
        // HasTeamScope), so Crop's own HasTeamScope already limits this to
        // the same team -- no need to re-check team_id explicitly.
        $crops = Crop::orderBy('name')->get();

        return view('crop-cycles.create', [
            'farm' => $farm,
            'field' => $field,
            'crops' => $crops,
        ]);
    }

    public function store(Request $request, Farm $farm, Field $field): RedirectResponse
    {
        Gate::authorize('update', $farm);
        abort_unless($field->farm_id === $farm->id, 404);

        $validated = $request->validate([
            'crop_id' => ['required', 'exists:crops,id'],
            'season' => ['required', 'string', 'max:100'],
            'expected_harvest_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Same reasoning as create(): Crop's HasTeamScope already limits
        // this lookup to the current (== $farm's) team.
        abort_unless(
            Crop::where('id', $validated['crop_id'])->exists(),
            404
        );

        $cropCycle = $field->cropCycles()->create([
            ...$validated,
            'status' => CropCycle::STATUS_PLANNED,
        ]);

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Crop cycle planned.');
    }

    public function show(Farm $farm, Field $field, CropCycle $cropCycle): View
    {
        Gate::authorize('view', $farm);
        abort_unless($field->farm_id === $farm->id, 404);
        abort_unless($cropCycle->field_id === $field->id, 404);

        $cropCycle->load(['crop', 'plantingRecords', 'harvestRecords']);

        return view('crop-cycles.show', [
            'farm' => $farm,
            'field' => $field,
            'cropCycle' => $cropCycle,
        ]);
    }
}
