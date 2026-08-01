<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('farms.show', $farm) }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $farm->name }}
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">Edit Farm</h1>

    <form method="POST" action="{{ route('farms.update', $farm) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf
        @method('PUT')

        <div>
            <label for="name" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Farm name</label>
            <input id="name" name="name" type="text" class="dot-input" value="{{ old('name', $farm->name) }}" required>
            @error('name')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="location" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Location</label>
            <input id="location" name="location" type="text" class="dot-input" value="{{ old('location', $farm->location) }}">
            @error('location')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="size_hectares" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Size (hectares)</label>
            <input id="size_hectares" name="size_hectares" type="number" step="0.01" min="0" class="dot-input" value="{{ old('size_hectares', $farm->size_hectares) }}">
            @error('size_hectares')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Status</label>
            <select id="status" name="status" class="dot-input">
                <option value="active" @selected(old('status', $farm->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $farm->status) === 'inactive')>Inactive</option>
            </select>
            @error('status')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="notes" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes', $farm->notes) }}</textarea>
            @error('notes')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('farms.show', $farm) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</x-app-layout>
