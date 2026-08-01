<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FieldController extends Controller
{
    public function create(Farm $farm): View
    {
        Gate::authorize('update', $farm);

        return view('fields.create', [
            'farm' => $farm,
        ]);
    }

    public function store(Request $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('fields', 'code')->where('farm_id', $farm->id),
            ],
            'soil_type' => ['nullable', 'string', 'max:100'],
            'moisture_zone' => ['nullable', 'string', 'max:100'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
        ]);

        $field = $farm->fields()->create([
            ...$validated,
            'status' => 'active',
        ]);

        return redirect()->route('fields.show', [$farm, $field])->with('flash.banner', "Field \"{$field->name}\" added to {$farm->name}.");
    }

    public function show(Farm $farm, Field $field): View
    {
        Gate::authorize('view', $farm);

        abort_unless($field->farm_id === $farm->id, 404);

        $field->load(['cropCycles' => function ($query) {
            $query->with('crop')->latest('planted_at');
        }]);

        return view('fields.show', [
            'farm' => $farm,
            'field' => $field,
        ]);
    }
}
