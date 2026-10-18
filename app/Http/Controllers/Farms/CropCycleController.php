<?php

namespace App\Http\Controllers\Farms;

use App\Enums\CropCycleStatus;
use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Services\Farms\UnitTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CropCycleController extends Controller
{
    /** A crop must belong to the farm's own team and not be deleted. */
    private function cropRule(Farm $farm): array
    {
        return ['required', Rule::exists('crops', 'id')->where('team_id', $farm->team_id)->whereNull('deleted_at')];
    }

    public function create(Farm $farm, Field $field): View
    {
        Gate::authorize('create', [CropCycle::class, $field]);

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
        Gate::authorize('create', [CropCycle::class, $field]);

        $validated = $request->validate([
            'crop_id' => $this->cropRule($farm),
            'season' => ['required', 'string', 'max:100'],
            'expected_harvest_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $cropCycle = $field->cropCycles()->create([
            ...$validated,
            'status' => CropCycleStatus::Planned,
        ]);

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Crop cycle planned.');
    }

    public function show(Farm $farm, Field $field, CropCycle $cropCycle): View
    {
        Gate::authorize('view', $cropCycle);

        $cropCycle->load(['crop', 'plantingRecords' => fn ($q) => $q->orderBy('planted_at'), 'harvestRecords' => fn ($q) => $q->orderBy('harvested_at')]);

        $harvestTotals = new UnitTotals;
        foreach ($cropCycle->harvestRecords as $record) {
            $harvestTotals->add($record->unit, $record->quantity_harvested);
        }

        return view('crop-cycles.show', [
            'farm' => $farm,
            'field' => $field,
            'cropCycle' => $cropCycle,
            'harvestTotals' => $harvestTotals,
            'plantingUnits' => Unit::forPlanting(),
            'harvestUnits' => Unit::forHarvest(),
            'canOperate' => Gate::allows('update', $cropCycle),
            'canManage' => Gate::allows('delete', $cropCycle),
            'impact' => $cropCycle->deletionImpact(),
        ]);
    }

    public function edit(Farm $farm, Field $field, CropCycle $cropCycle): View
    {
        Gate::authorize('update', $cropCycle);

        return view('crop-cycles.edit', [
            'farm' => $farm,
            'field' => $field,
            'cropCycle' => $cropCycle->load('crop'),
            'crops' => Crop::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Farm $farm, Field $field, CropCycle $cropCycle): RedirectResponse
    {
        Gate::authorize('update', $cropCycle);

        $rules = [
            'season' => ['required', 'string', 'max:100'],
            'expected_harvest_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];

        // The crop can only change before anything is planted; afterwards the
        // records on the cycle are records of that crop.
        if ($request->filled('crop_id') && (int) $request->input('crop_id') !== $cropCycle->crop_id) {
            if ($cropCycle->status !== CropCycleStatus::Planned) {
                throw ValidationException::withMessages(['crop_id' => 'The crop cannot be changed once planting has started.']);
            }
            $rules['crop_id'] = $this->cropRule($farm);
        }

        $validated = $request->validate($rules);

        $cropCycle->update($validated);

        return redirect()->route('crop-cycles.show', [$farm, $field, $cropCycle])->with('flash.banner', 'Crop cycle updated.');
    }

    public function destroy(Request $request, Farm $farm, Field $field, CropCycle $cropCycle): RedirectResponse
    {
        Gate::authorize('delete', $cropCycle);

        $impact = $cropCycle->deletionImpact();

        if (($impact['plantings'] + $impact['harvests']) > 0 && ! $request->boolean('confirm_cascade')) {
            return back()->withErrors([
                'confirm_cascade' => "Confirm to delete this cycle together with its {$impact['plantings']} planting(s) and {$impact['harvests']} harvest record(s).",
            ]);
        }

        DB::transaction(fn () => $cropCycle->delete());

        return redirect()->route('fields.show', [$farm, $field])->with('flash.banner', 'Crop cycle deleted.');
    }
}
