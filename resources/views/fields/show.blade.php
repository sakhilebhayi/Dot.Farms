<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('farms.show', $farm) }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $farm->name }}
        </a>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">{{ $field->name }} <span style="font-size:0.9rem;color:#52525b;font-family:'JetBrains Mono',monospace;font-weight:400;">{{ $field->code }}</span></h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">
                {{ $field->soil_type ? ucfirst($field->soil_type).' soil' : 'Soil unset' }}
                · {{ $field->moisture_zone ? ucfirst($field->moisture_zone).' moisture zone' : 'Moisture zone unset' }}
                @if($field->size_hectares) · {{ number_format((float) $field->size_hectares, 1) }} ha @endif
            </p>
        </div>
        <a href="{{ route('crop-cycles.create', [$farm, $field]) }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            Plan Crop Cycle
        </a>
    </div>

    <div class="dot-card" style="padding:1.5rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1.25rem;">Crop Cycles</h3>

        @if($field->cropCycles->isEmpty())
            <div style="text-align:center;padding:2rem 0;">
                <span class="material-symbols-rounded" style="font-size:32px;color:#3f3f46;display:block;margin-bottom:0.5rem;">grass</span>
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No crop cycles planned for this field yet.</p>
            </div>
        @else
            <div style="display:grid;gap:0.5rem;">
                @foreach($field->cropCycles as $cycle)
                @php
                    $statusColor = match($cycle->status) {
                        'planted', 'growing' => '#f59e0b',
                        'harvested' => '#22c55e',
                        'failed' => '#ef4444',
                        default => '#71717a',
                    };
                @endphp
                <a href="{{ route('crop-cycles.show', [$farm, $field, $cycle]) }}" style="text-decoration:none;display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;">
                    <div>
                        <div style="font-size:12.5px;font-weight:600;color:#d4d4d8;">{{ $cycle->crop->displayName() }} — {{ $cycle->season }}</div>
                        <div style="font-size:11px;color:#52525b;margin-top:2px;">
                            {{ $cycle->planted_at?->format('M j, Y') ?? 'Not yet planted' }}
                            @if($cycle->expected_harvest_at) · expected harvest {{ $cycle->expected_harvest_at->format('M j, Y') }} @endif
                        </div>
                    </div>
                    <span class="dot-badge" style="background:{{ $statusColor }}1a;color:{{ $statusColor }};">{{ ucfirst($cycle->status) }}</span>
                </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
</x-app-layout>
