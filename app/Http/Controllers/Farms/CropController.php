<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CropController extends Controller
{
    public function index(Request $request): View
    {
        $crops = Crop::query()
            ->where('team_id', $request->user()->currentTeam->id)
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'variety' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'typical_cycle_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $crop = Crop::create([
            ...$validated,
            'team_id' => $request->user()->currentTeam->id,
        ]);

        return redirect()->route('crops.index')->with('flash.banner', "Crop \"{$crop->displayName()}\" added.");
    }
}
