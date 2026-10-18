<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('crop-cycles.show', [$farm, $field, $cropCycle]) }}" style="font-size:0.78rem;color:#a1a1aa;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to the cycle
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">Edit Crop Cycle</h1>
    <x-form-errors />

    @php $planned = $cropCycle->status === \App\Enums\CropCycleStatus::Planned; @endphp
    <form method="POST" action="{{ route('crop-cycles.update', [$farm, $field, $cropCycle]) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf
        @method('PUT')

        <div>
            <label for="crop_id" class="dot-label">Crop</label>
            @if($planned)
                <select id="crop_id" name="crop_id" class="dot-input" required>
                    @foreach($crops as $crop)
                        <option value="{{ $crop->id }}" @selected((int) old('crop_id', $cropCycle->crop_id) === $crop->id)>{{ $crop->displayName() }}</option>
                    @endforeach
                </select>
            @else
                <div style="font-size:13px;color:#d4d4d8;">{{ $cropCycle->crop->displayName() }}</div>
                <p class="dot-muted" style="font-size:11.5px;margin:0.25rem 0 0;">The crop is fixed once planting has started, because the records on this cycle belong to it.</p>
            @endif
        </div>

        <div>
            <label for="season" class="dot-label">Season</label>
            <input id="season" name="season" type="text" class="dot-input" value="{{ old('season', $cropCycle->season) }}" required>
        </div>

        <div>
            <label for="expected_harvest_at" class="dot-label">Expected harvest date</label>
            <input id="expected_harvest_at" name="expected_harvest_at" type="date" class="dot-input" value="{{ old('expected_harvest_at', $cropCycle->expected_harvest_at?->toDateString()) }}">
        </div>

        <div>
            <label for="notes" class="dot-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes', $cropCycle->notes) }}</textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('crop-cycles.show', [$farm, $field, $cropCycle]) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</x-app-layout>
