<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:720px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('fields.show', [$farm, $field]) }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $field->name }}
        </a>
    </div>

    @php
        $statusColor = match($cropCycle->status) {
            'planted', 'growing' => '#f59e0b',
            'harvested' => '#22c55e',
            'failed' => '#ef4444',
            default => '#71717a',
        };
    @endphp

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">{{ $cropCycle->crop->displayName() }}</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ $farm->name }} · {{ $field->name }} ({{ $field->code }}) · {{ $cropCycle->season }}</p>
        </div>
        <span class="dot-badge" style="background:{{ $statusColor }}1a;color:{{ $statusColor }};">{{ ucfirst($cropCycle->status) }}</span>
    </div>

    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;">
            <div>
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Planted</div>
                <div style="font-size:13px;color:#d4d4d8;">{{ $cropCycle->planted_at?->format('M j, Y') ?? 'Not yet planted' }}</div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Expected harvest</div>
                <div style="font-size:13px;color:#d4d4d8;">{{ $cropCycle->expected_harvest_at?->format('M j, Y') ?? '—' }}</div>
            </div>
        </div>
        @if($cropCycle->notes)
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Notes</div>
            <p style="font-size:12.5px;color:#a1a1aa;margin:0;">{{ $cropCycle->notes }}</p>
        </div>
        @endif
    </div>

    @if($cropCycle->status === \App\Models\CropCycle::STATUS_PLANNED)
    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1rem;">Record Planting</h3>
        <form method="POST" action="{{ route('crop-cycles.planting.store', [$farm, $field, $cropCycle]) }}" style="display:grid;gap:1rem;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Planted on</label>
                    <input name="planted_at" type="date" class="dot-input" value="{{ old('planted_at', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Quantity</label>
                    <input name="quantity_planted" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_planted') }}">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Unit</label>
                    <select name="unit" class="dot-input">
                        <option value="kg">kg</option>
                        <option value="seeds">seeds</option>
                        <option value="seedlings">seedlings</option>
                    </select>
                </div>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Method</label>
                <select name="method" class="dot-input">
                    <option value="">Unset</option>
                    <option value="direct-seed">Direct-seed</option>
                    <option value="transplant">Transplant</option>
                    <option value="broadcast">Broadcast</option>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="dot-btn dot-btn-primary">Record Planting</button>
            </div>
        </form>
    </div>
    @endif

    @if($cropCycle->isInSeason())
    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1rem;">Record Harvest</h3>
        <form method="POST" action="{{ route('crop-cycles.harvest.store', [$farm, $field, $cropCycle]) }}" style="display:grid;gap:1rem;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Harvested on</label>
                    <input name="harvested_at" type="date" class="dot-input" value="{{ old('harvested_at', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Quantity</label>
                    <input name="quantity_harvested" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_harvested') }}" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Unit</label>
                    <select name="unit" class="dot-input">
                        <option value="kg">kg</option>
                        <option value="ton">ton</option>
                        <option value="crates">crates</option>
                    </select>
                </div>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#a1a1aa;margin-bottom:0.35rem;">Quality grade</label>
                <select name="quality_grade" class="dot-input">
                    <option value="">Unset</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="dot-btn dot-btn-primary">Record Harvest</button>
            </div>
        </form>
    </div>
    @endif

    @if($cropCycle->harvestRecords->isNotEmpty())
    <div class="dot-card" style="padding:1.5rem;">
        <h3 style="font-family:'Syne',sans-serif;font-size:0.875rem;font-weight:700;color:#f4f4f5;margin:0 0 1rem;">Harvest History</h3>
        <div style="display:grid;gap:0.5rem;">
            @foreach($cropCycle->harvestRecords as $harvest)
            <a href="{{ route('harvests.show', $harvest) }}" style="text-decoration:none;display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;">
                <div>
                    <div style="font-size:12px;font-weight:600;color:#d4d4d8;">{{ $harvest->harvested_at?->format('M j, Y') }}</div>
                    @if($harvest->quality_grade)<div style="font-size:11px;color:#52525b;margin-top:2px;">Grade {{ $harvest->quality_grade }}</div>@endif
                </div>
                <div class="metric-val" style="font-size:13px;font-weight:600;color:#f4f4f5;">{{ number_format((float) $harvest->quantity_harvested, 2) }} {{ $harvest->unit }}</div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
</x-app-layout>
