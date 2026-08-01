<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlantingRecordController extends Controller
{
    /**
     * Record a planting event against a crop cycle and advance its
     * status. Kept as a single store action (no dedicated index/show
     * views) since planting records are always viewed in the context
     * of their crop cycle — see crop-cycles.show.
     */
    public function store(Request $request, Farm $farm, Field $field, CropCycle $cropCycle): RedirectResponse
    {
        Gate::authorize('update', $farm);
        abort_unless($field->farm_id === $farm->id, 404);
        abort_unless($cropCycle->field_id === $field->id, 404);

        $validated = $request->validate([
            'planted_at' => ['required', 'date'],
            'quantity_planted' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $cropCycle->plantingRecords()->create($validated);

        $cropCycle->update([
            'status' => CropCycle::STATUS_PLANTED,
            'planted_at' => $validated['planted_at'],
        ]);

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Planting recorded.');
    }
}
