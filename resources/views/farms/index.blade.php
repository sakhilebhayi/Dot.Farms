<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Farms</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ $farms->count() }} farm{{ $farms->count() === 1 ? '' : 's' }} on this team</p>
        </div>
        <a href="{{ route('farms.create') }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            New Farm
        </a>
    </div>

    @if($farms->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">agriculture</span>
            <p style="font-size:0.85rem;color:#52525b;margin:0 0 1rem;">No farms yet. Add your first one to get started.</p>
            <a href="{{ route('farms.create') }}" class="dot-btn dot-btn-primary">Add a farm</a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;">
            @foreach($farms as $farm)
            <a href="{{ route('farms.show', $farm) }}" class="dot-card" style="padding:1.25rem 1.5rem;text-decoration:none;display:block;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <span style="font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:#f4f4f5;">{{ $farm->name }}</span>
                    <span class="dot-badge {{ $farm->isActive() ? 'dot-badge-accent' : '' }}" style="{{ $farm->isActive() ? '' : 'background:rgba(255,255,255,0.06);color:#71717a;' }}">{{ ucfirst($farm->status) }}</span>
                </div>
                <p style="font-size:12px;color:#71717a;margin:0 0 0.75rem;">{{ $farm->location ?? 'No location set' }}</p>
                <div style="display:flex;gap:1rem;font-size:11px;color:#52525b;">
                    <span>{{ $farm->fields_count }} field{{ $farm->fields_count === 1 ? '' : 's' }}</span>
                    @if($farm->size_hectares)
                        <span>{{ number_format((float) $farm->size_hectares, 1) }} ha</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    @endif
</div>
</x-app-layout>
