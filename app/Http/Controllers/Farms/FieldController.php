<?php

namespace App\Http\Controllers\Farms;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FieldController extends Controller
{
    private const STATUSES = ['active', 'fallow', 'retired'];

    /** @return array<string, array<int, mixed>> */
    private function rules(Farm $farm, ?Field $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                // Only live fields reserve a code; a deleted field's code is free again.
                Rule::unique('fields', 'code')->where('farm_id', $farm->id)->whereNull('deleted_at')->ignore($ignore?->id),
            ],
            'soil_type' => ['nullable', 'string', 'max:100'],
            'moisture_zone' => ['nullable', 'string', 'max:100'],
            'size_hectares' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function create(Farm $farm): View
    {
        Gate::authorize('create', [Field::class, $farm]);

        return view('fields.create', [
            'farm' => $farm,
        ]);
    }

    public function store(Request $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('create', [Field::class, $farm]);

        $validated = $request->validate($this->rules($farm));

        $field = $farm->fields()->create([
            ...$validated,
            'status' => 'active',
        ]);

        return redirect()->route('fields.show', [$farm, $field])->with('flash.banner', "Field \"{$field->name}\" added to {$farm->name}.");
    }

    public function show(Farm $farm, Field $field): View
    {
        Gate::authorize('view', $field);

        $field->load(['cropCycles' => function ($query) {
            $query->with('crop')->latest('planted_at');
        }]);

        return view('fields.show', [
            'farm' => $farm,
            'field' => $field,
        ]);
    }

    public function edit(Farm $farm, Field $field): View
    {
        Gate::authorize('update', $field);

        return view('fields.edit', [
            'farm' => $farm,
            'field' => $field,
            'impact' => $field->deletionImpact(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Farm $farm, Field $field): RedirectResponse
    {
        Gate::authorize('update', $field);

        $validated = $request->validate([
            ...$this->rules($farm, $field),
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $field->update($validated);

        return redirect()->route('fields.show', [$farm, $field])->with('flash.banner', 'Field updated.');
    }

    public function destroy(Request $request, Farm $farm, Field $field): RedirectResponse
    {
        Gate::authorize('delete', $field);

        $impact = $field->deletionImpact();

        if ($impact['cycles'] > 0 && ! $request->boolean('confirm_cascade')) {
            return back()->withErrors([
                'confirm_cascade' => "Confirm to delete \"{$field->name}\" together with its {$impact['cycles']} crop cycle(s) and {$impact['harvests']} harvest record(s).",
            ]);
        }

        DB::transaction(fn () => $field->delete());

        return redirect()->route('farms.show', $farm)->with('flash.banner', "Field \"{$field->name}\" deleted.");
    }
}
