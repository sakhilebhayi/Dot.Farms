<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FarmController extends Controller
{
    /**
     * A user can reach this controller's action methods (via a
     * previously-loaded form) after being removed from their last team,
     * at which point currentTeam is genuinely null. See wiki.md Change
     * Log 2026-08-04.
     */
    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    public function index(Request $request): View
    {
        // team scoping is handled by Farm's HasTeamScope global scope
        $farms = Farm::query()
            ->withCount('fields')
            ->orderBy('name')
            ->get();

        return view('farms.index', [
            'farms' => $farms,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Farm::class);

        return view('farms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Farm::class);

        $team = $this->resolveCurrentTeam();
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $farm = Farm::create([
            ...$validated,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        return redirect()->route('farms.show', $farm)->with('flash.banner', "Farm \"{$farm->name}\" created.");
    }

    public function show(Farm $farm): View
    {
        Gate::authorize('view', $farm);

        $farm->load(['fields' => function ($query) {
            $query->withCount('cropCycles')->orderBy('name');
        }]);

        $recentHarvests = HarvestRecord::query()
            ->whereHas('cropCycle.field', fn ($q) => $q->where('farm_id', $farm->id))
            ->with(['cropCycle.crop', 'cropCycle.field'])
            ->latest('harvested_at')
            ->take(5)
            ->get();

        return view('farms.show', [
            'farm' => $farm,
            'recentHarvests' => $recentHarvests,
        ]);
    }

    public function edit(Farm $farm): View
    {
        Gate::authorize('update', $farm);

        return view('farms.edit', [
            'farm' => $farm,
        ]);
    }

    public function update(Request $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $farm->update($validated);

        return redirect()->route('farms.show', $farm)->with('flash.banner', 'Farm updated.');
    }
}
