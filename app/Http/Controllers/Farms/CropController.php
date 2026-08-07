<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CropController extends Controller
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
        // team scoping is handled by Crop's HasTeamScope global scope
        $crops = Crop::query()
            ->withCount('cropCycles')
            ->orderBy('name')
            ->get();

        return view('crops.index', [
            'crops' => $crops,
        ]);
    }

    public function create(): View
    {
        return view('crops.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->resolveCurrentTeam();
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'typical_cycle_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $crop = Crop::create([
            ...$validated,
            'team_id' => $team->id,
        ]);

        return redirect()->route('crops.index')->with('flash.banner', "Crop \"{$crop->displayName()}\" added.");
    }
}
