<?php

namespace App\Http\Controllers\Farms;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\PlantingRecord;
use App\Services\Farms\CropCycleLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantingRecordController extends Controller
{
    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'planted_at' => ['required', 'date'],
            'quantity_planted' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', Rule::in(Unit::plantingValues())],
            'method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Record a planting event against a crop cycle and advance its status.
     * Plantings are listed on the crop cycle page; there is no index/show of
     * their own. The record and the status change commit together in
     * CropCycleLifecycle.
     */
    public function store(Request $request, Farm $farm, Field $field, CropCycle $cropCycle, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('recordPlanting', $cropCycle);

        $lifecycle->plant($cropCycle, $request->validate($this->rules()));

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Planting recorded.');
    }

    public function edit(Farm $farm, Field $field, CropCycle $cropCycle, PlantingRecord $plantingRecord): View
    {
        Gate::authorize('update', $plantingRecord);

        return view('plantings.edit', [
            'farm' => $farm,
            'field' => $field,
            'cropCycle' => $cropCycle->load('crop'),
            'plantingRecord' => $plantingRecord,
            'units' => Unit::forPlanting(),
        ]);
    }

    public function update(Request $request, Farm $farm, Field $field, CropCycle $cropCycle, PlantingRecord $plantingRecord, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('update', $plantingRecord);

        $lifecycle->updatePlanting($plantingRecord, $request->validate($this->rules()));

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Planting updated.');
    }

    public function destroy(Farm $farm, Field $field, CropCycle $cropCycle, PlantingRecord $plantingRecord, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('delete', $plantingRecord);

        $lifecycle->deletePlanting($plantingRecord);

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Planting deleted.');
    }
}
