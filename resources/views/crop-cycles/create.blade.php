<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('fields.show', [$farm, $field]) }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $field->name }}
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">Plan Crop Cycle on {{ $field->name }}</h1>

    @if($crops->isEmpty())
        <div class="dot-card" style="padding:1.5rem;">
            <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 1rem;">Your crop catalog is empty — add a crop before planning a cycle.</p>
            <a href="{{ route('crops.create') }}" class="dot-btn dot-btn-primary">Add a crop</a>
        </div>
    @else
        <form method="POST" action="{{ route('crop-cycles.store', [$farm, $field]) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
            @csrf

            <div>
                <label for="crop_id" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Crop</label>
                <select id="crop_id" name="crop_id" class="dot-input" required>
                    <option value="">Select a crop</option>
                    @foreach($crops as $crop)
                        <option value="{{ $crop->id }}" @selected((string) old('crop_id') === (string) $crop->id)>{{ $crop->displayName() }}</option>
                    @endforeach
                </select>
                @error('crop_id')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="season" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Season</label>
                <input id="season" name="season" type="text" class="dot-input" value="{{ old('season', now()->year.' Summer') }}" placeholder="e.g. 2026 Summer" required>
                @error('season')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="expected_harvest_at" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Expected harvest date</label>
                <input id="expected_harvest_at" name="expected_harvest_at" type="date" class="dot-input" value="{{ old('expected_harvest_at') }}">
                @error('expected_harvest_at')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="notes" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes') }}</textarea>
                @error('notes')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>

            <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
                <a href="{{ route('fields.show', [$farm, $field]) }}" class="dot-btn dot-btn-ghost">Cancel</a>
                <button type="submit" class="dot-btn dot-btn-primary">Plan Cycle</button>
            </div>
        </form>
    @endif
</div>
</x-app-layout>
