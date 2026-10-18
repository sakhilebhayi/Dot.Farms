<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('crop-cycles.show', [$farm, $field, $cropCycle]) }}" style="font-size:0.78rem;color:#a1a1aa;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $cropCycle->crop->displayName() }}
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 0.25rem;">Edit Planting</h1>
    <p class="dot-muted" style="font-size:0.78rem;margin:0 0 1.5rem;">{{ $farm->name }} · {{ $field->name }} · {{ $cropCycle->season }}</p>
    <x-form-errors />

    <form method="POST" action="{{ route('plantings.update', [$farm, $field, $cropCycle, $plantingRecord]) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf
        @method('PUT')

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="planted_at" class="dot-label">Planted on</label>
                <input id="planted_at" name="planted_at" type="date" class="dot-input" value="{{ old('planted_at', $plantingRecord->planted_at->toDateString()) }}" required>
            </div>
            <div>
                <label for="quantity_planted" class="dot-label">Quantity</label>
                <input id="quantity_planted" name="quantity_planted" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_planted', $plantingRecord->quantity_planted) }}">
            </div>
            <div>
                <label for="unit" class="dot-label">Unit</label>
                <select id="unit" name="unit" class="dot-input">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit', $plantingRecord->unit) === $unit->value)>{{ $unit->fullName() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="method" class="dot-label">Method</label>
            <select id="method" name="method" class="dot-input">
                <option value="">Unset</option>
                @foreach(['direct-seed' => 'Direct-seed', 'transplant' => 'Transplant', 'broadcast' => 'Broadcast'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('method', $plantingRecord->method) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="notes" class="dot-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes', $plantingRecord->notes) }}</textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('crop-cycles.show', [$farm, $field, $cropCycle]) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</x-app-layout>
