@php($cycle = $harvestRecord->cropCycle)
<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('harvests.show', $harvestRecord) }}" style="font-size:0.78rem;color:#a1a1aa;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to the harvest
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 0.25rem;">Correct Harvest</h1>
    <p class="dot-muted" style="font-size:0.78rem;margin:0 0 1.5rem;">{{ $cycle->crop->displayName() }} · {{ $cycle->field->farm->name }} · {{ $cycle->field->name }} · {{ $cycle->season }}</p>
    <x-form-errors />

    <form method="POST" action="{{ route('harvests.update', $harvestRecord) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf
        @method('PUT')

        <div class="r-stack" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="harvested_at" class="dot-label">Harvested on</label>
                <input id="harvested_at" name="harvested_at" type="date" class="dot-input" value="{{ old('harvested_at', $harvestRecord->harvested_at->toDateString()) }}" required>
            </div>
            <div>
                <label for="quantity_harvested" class="dot-label">Quantity</label>
                <input id="quantity_harvested" name="quantity_harvested" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_harvested', $harvestRecord->quantity_harvested) }}" required>
            </div>
            <div>
                <label for="unit" class="dot-label">Unit</label>
                <select id="unit" name="unit" class="dot-input">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit', $harvestRecord->unit) === $unit->value)>{{ $unit->fullName() }}</option>
                    @endforeach
                    @unless(in_array($harvestRecord->unit, \App\Enums\Unit::harvestValues(), true))
                        <option value="" disabled selected>{{ $harvestRecord->unit }} (not a standard unit: pick one)</option>
                    @endunless
                </select>
            </div>
        </div>

        <div>
            <label for="quality_grade" class="dot-label">Quality grade</label>
            <select id="quality_grade" name="quality_grade" class="dot-input">
                <option value="">Unset</option>
                @foreach(['A', 'B', 'C'] as $grade)
                    <option value="{{ $grade }}" @selected(old('quality_grade', $harvestRecord->quality_grade) === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="notes" class="dot-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes', $harvestRecord->notes) }}</textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('harvests.show', $harvestRecord) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</x-app-layout>
