<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('farms.index') }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to farms
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">New Farm</h1>

    <form method="POST" action="{{ route('farms.store') }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf

        <div>
            <label for="name" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Farm name</label>
            <input id="name" name="name" type="text" class="dot-input" value="{{ old('name') }}" required autofocus>
            @error('name')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="location" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Location</label>
            <input id="location" name="location" type="text" class="dot-input" value="{{ old('location') }}" placeholder="e.g. Vaalharts, Northern Cape">
            @error('location')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="size_hectares" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Size (hectares)</label>
            <input id="size_hectares" name="size_hectares" type="number" step="0.01" min="0" class="dot-input" value="{{ old('size_hectares') }}">
            @error('size_hectares')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="notes" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="dot-input">{{ old('notes') }}</textarea>
            @error('notes')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('farms.index') }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Create Farm</button>
        </div>
    </form>
</div>
</x-app-layout>
