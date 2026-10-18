<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'typical_cycle_days' => ['nullable', 'integer', 'min:1'],
        ];
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
        Gate::authorize('create', Crop::class);

        return view('crops.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Crop::class);

        $team = $this->resolveCurrentTeam();
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate($this->rules());

        $crop = Crop::create([
            ...$validated,
            'team_id' => $team->id,
        ]);

        return redirect()->route('crops.index')->with('flash.banner', "Crop \"{$crop->displayName()}\" added.");
    }

    public function edit(Crop $crop): View
    {
        Gate::authorize('update', $crop);

        return view('crops.edit', ['crop' => $crop]);
    }

    public function update(Request $request, Crop $crop): RedirectResponse
    {
        Gate::authorize('update', $crop);

        $crop->update($request->validate($this->rules()));

        return redirect()->route('crops.index')->with('flash.banner', "Crop \"{$crop->displayName()}\" updated.");
    }

    /** A crop that cycles use is never removed: retire the cycles first, or keep the crop. */
    public function destroy(Crop $crop): RedirectResponse
    {
        Gate::authorize('delete', $crop);

        if ($crop->isInUse()) {
            return back()->withErrors([
                'crop' => "\"{$crop->displayName()}\" is in use by crop cycles and cannot be deleted. Delete or re-plan those cycles first.",
            ]);
        }

        DB::transaction(fn () => $crop->delete());

        return redirect()->route('crops.index')->with('flash.banner', "Crop \"{$crop->displayName()}\" deleted.");
    }
}
