<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('crops.index') }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to crops
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">New Crop</h1>

    <form method="POST" action="{{ route('crops.store') }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="name" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Name</label>
                <input id="name" name="name" type="text" class="dot-input" value="{{ old('name') }}" placeholder="e.g. Maize" required autofocus>
                @error('name')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="variety" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Variety</label>
                <input id="variety" name="variety" type="text" class="dot-input" value="{{ old('variety') }}" placeholder="e.g. Yellow dent">
                @error('variety')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="category" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Category</label>
                <select id="category" name="category" class="dot-input">
                    <option value="">Unset</option>
                    @foreach(['grain', 'vegetable', 'fruit', 'legume'] as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
                @error('category')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="typical_cycle_days" style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Typical cycle (days)</label>
                <input id="typical_cycle_days" name="typical_cycle_days" type="number" min="1" class="dot-input" value="{{ old('typical_cycle_days') }}">
                @error('typical_cycle_days')<p style="color:#ef4444;font-size:11px;margin:0.35rem 0 0;">{{ $message }}</p>@enderror
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('crops.index') }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Add Crop</button>
        </div>
    </form>
</div>
</x-app-layout>
