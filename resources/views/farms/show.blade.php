<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('farms.index') }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to farms
        </a>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">{{ $farm->name }}</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ $farm->location ?? 'No location set' }}{{ $farm->size_hectares ? ' · '.number_format((float) $farm->size_hectares, 1).' ha' : '' }}</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('farms.edit', $farm) }}" class="dot-btn dot-btn-ghost">Edit</a>
            <a href="{{ route('fields.create', $farm) }}" class="dot-btn dot-btn-primary">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                Add Field
            </a>
        </div>
    </div>

    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Fields</h3>

        @if($farm->fields->isEmpty())
            <div style="text-align:center;padding:2rem 0;">
                <span class="material-symbols-rounded" style="font-size:32px;color:#3f3f46;display:block;margin-bottom:0.5rem;">landscape</span>
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No fields yet. Add the first one.</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0.75rem;">
                @foreach($farm->fields as $field)
                <a href="{{ route('fields.show', [$farm, $field]) }}" style="text-decoration:none;display:block;padding:0.9rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                        <span style="font-size:13px;font-weight:600;color:#d4d4d8;">{{ $field->name }}</span>
                        <span style="font-size:10px;color:#52525b;font-family:'JetBrains Mono',monospace;">{{ $field->code }}</span>
                    </div>
                    <div style="font-size:11px;color:#71717a;">{{ $field->soil_type ? ucfirst($field->soil_type).' soil' : 'Soil unset' }} · {{ $field->crop_cycles_count }} cycle{{ $field->crop_cycles_count === 1 ? '' : 's' }}</div>
                </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="dot-card" style="padding:1.5rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Recent Harvests</h3>

        @if($recentHarvests->isEmpty())
            <div style="text-align:center;padding:1.5rem 0;">
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No harvests recorded on this farm yet.</p>
            </div>
        @else
            <div style="display:grid;gap:0.5rem;">
                @foreach($recentHarvests as $harvest)
                <a href="{{ route('harvests.show', $harvest) }}" style="text-decoration:none;display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;">
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $harvest->cropCycle->crop->displayName() }} — {{ $harvest->cropCycle->field->name }}</div>
                        <div style="font-size:11px;color:#52525b;margin-top:2px;">{{ $harvest->harvested_at?->format('M j, Y') }}</div>
                    </div>
                    <div class="metric-val" style="font-size:13px;font-weight:600;color:#f4f4f5;">{{ number_format((float) $harvest->quantity_harvested, 2) }} {{ $harvest->unit }}</div>
                </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
</x-app-layout>
