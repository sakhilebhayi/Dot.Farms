<?php

namespace App\Http\Controllers\Farms;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Services\Farms\CropCycleLifecycle;
use App\Services\Farms\HarvestFilters;
use App\Services\Farms\UnitTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HarvestRecordController extends Controller
{
    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'harvested_at' => ['required', 'date'],
            'quantity_harvested' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', Rule::in(Unit::harvestValues())],
            'quality_grade' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Team-wide list of harvest records across all farms, newest first, with
     * the totals of exactly the records the filters match.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HarvestRecord::class);

        $team = $request->user()->currentTeam;
        $filters = HarvestFilters::fromRequest($request);

        $base = $filters->apply(HarvestRecord::query()->forTeam($team));

        $totals = new UnitTotals;
        (clone $base)->reorder()->selectRaw('harvest_records.unit as unit, SUM(harvest_records.quantity_harvested) as total, COUNT(*) as records')
            ->groupBy('harvest_records.unit')->get()
            ->each(function ($row) use ($totals) {
                $totals->add($row->unit, $row->total);
            });

        $harvestRecords = (clone $base)
            ->with(['cropCycle.crop', 'cropCycle.field.farm'])
            ->latest('harvested_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('harvests.index', [
            'harvestRecords' => $harvestRecords,
            'totals' => $totals,
            'recordCount' => $harvestRecords->total(),
            'filters' => $filters,
            'seasons' => HarvestFilters::seasonsFor($team->id),
            'crops' => Crop::orderBy('name')->get(),
            'farms' => Farm::orderBy('name')->get(),
        ]);
    }

    public function show(HarvestRecord $harvestRecord): View
    {
        Gate::authorize('view', $harvestRecord);

        $harvestRecord->load(['cropCycle.crop', 'cropCycle.field.farm']);

        return view('harvests.show', [
            'harvestRecord' => $harvestRecord,
            'canOperate' => Gate::allows('update', $harvestRecord),
            'canManage' => Gate::allows('delete', $harvestRecord),
        ]);
    }

    /**
     * Record a harvest event against a crop cycle. This is the MVP stand-in
     * for the `agriculture.harvest.recorded` event described in wiki.md §5 —
     * no outbound event publishing yet. A harvest is partial by default: it
     * does not close the cycle (closing is an explicit action), and it is
     * only accepted while the cycle is planted or growing.
     */
    public function store(Request $request, Farm $farm, Field $field, CropCycle $cropCycle, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('recordHarvest', $cropCycle);

        $lifecycle->recordHarvest($cropCycle, $request->validate($this->rules()));

        return redirect()
            ->route('crop-cycles.show', [$farm, $field, $cropCycle])
            ->with('flash.banner', 'Harvest recorded.');
    }

    public function edit(HarvestRecord $harvestRecord): View
    {
        Gate::authorize('update', $harvestRecord);

        $harvestRecord->load(['cropCycle.crop', 'cropCycle.field.farm']);

        return view('harvests.edit', [
            'harvestRecord' => $harvestRecord,
            'units' => Unit::forHarvest(),
        ]);
    }

    public function update(Request $request, HarvestRecord $harvestRecord, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('update', $harvestRecord);

        $lifecycle->updateHarvest($harvestRecord, $request->validate($this->rules()));

        return redirect()
            ->route('crop-cycles.show', $this->cycleRoute($harvestRecord))
            ->with('flash.banner', 'Harvest updated.');
    }

    public function destroy(HarvestRecord $harvestRecord, CropCycleLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('delete', $harvestRecord);

        $route = $this->cycleRoute($harvestRecord);
        $lifecycle->deleteHarvest($harvestRecord);

        return redirect()
            ->route('crop-cycles.show', $route)
            ->with('flash.banner', 'Harvest deleted.');
    }

    /** @return array{0: Farm, 1: Field, 2: CropCycle} */
    private function cycleRoute(HarvestRecord $harvestRecord): array
    {
        $harvestRecord->loadMissing('cropCycle.field.farm');
        $cycle = $harvestRecord->cropCycle;

        return [$cycle->field->farm, $cycle->field, $cycle];
    }
}
