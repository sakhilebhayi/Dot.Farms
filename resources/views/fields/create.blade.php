<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('farms.show', $farm) }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $farm->name }}
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">Add Field to {{ $farm->name }}</h1>

    <form method="POST" action="{{ route('fields.store', $farm) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
            <div>
                <label for="name" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Field name</label>
                <input id="name" name="name" type="text" class="dot-input" value="{{ old('name') }}" required autofocus>
                @error('name')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="code" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Code</label>
                <input id="code" name="code" type="text" class="dot-input" value="{{ old('code') }}" placeholder="e.g. N-1" required>
                @error('code')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="soil_type" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Soil type</label>
                <select id="soil_type" name="soil_type" class="dot-input">
                    <option value="">Unset</option>
                    @foreach(['loam', 'clay', 'sandy', 'silt', 'peat'] as $type)
                        <option value="{{ $type }}" @selected(old('soil_type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                @error('soil_type')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="moisture_zone" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Moisture zone</label>
                <select id="moisture_zone" name="moisture_zone" class="dot-input">
                    <option value="">Unset</option>
                    @foreach(['dry', 'moderate', 'wet'] as $zone)
                        <option value="{{ $zone }}" @selected(old('moisture_zone') === $zone)>{{ ucfirst($zone) }}</option>
                    @endforeach
                </select>
                @error('moisture_zone')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="size_hectares" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Size (hectares)</label>
            <input id="size_hectares" name="size_hectares" type="number" step="0.01" min="0" class="dot-input" value="{{ old('size_hectares') }}">
            @error('size_hectares')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('farms.show', $farm) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Add Field</button>
        </div>
    </form>
</div>
</x-app-layout>
