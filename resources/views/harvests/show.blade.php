<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;max-width:640px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('harvests.index') }}" style="font-size:0.78rem;color:#71717a;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to harvests
        </a>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">
                {{ $harvestRecord->cropCycle->crop->displayName() }}
            </h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">
                {{ $harvestRecord->cropCycle->field->farm->name }} · {{ $harvestRecord->cropCycle->field->name }} ({{ $harvestRecord->cropCycle->field->code }}) · {{ $harvestRecord->cropCycle->season }}
            </p>
        </div>
        @if($harvestRecord->quality_grade)
            <span class="dot-badge dot-badge-accent">Grade {{ $harvestRecord->quality_grade }}</span>
        @endif
    </div>

    <div class="dot-card" style="padding:1.5rem;">
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;">
            <div>
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Quantity Harvested</div>
                <div class="metric-val" style="font-size:1.4rem;font-weight:700;color:var(--accent);">{{ number_format((float) $harvestRecord->quantity_harvested, 2) }} {{ $harvestRecord->unit }}</div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Harvested On</div>
                <div style="font-size:13px;color:#d4d4d8;">{{ $harvestRecord->harvested_at?->format('l, F j, Y') }}</div>
            </div>
        </div>
        @if($harvestRecord->notes)
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.5rem;">Notes</div>
            <p style="font-size:12.5px;color:#a1a1aa;margin:0;">{{ $harvestRecord->notes }}</p>
        </div>
        @endif
    </div>

    <div style="margin-top:1.25rem;">
        <a href="{{ route('crop-cycles.show', [$harvestRecord->cropCycle->field->farm, $harvestRecord->cropCycle->field, $harvestRecord->cropCycle]) }}" class="dot-btn dot-btn-ghost">
            View crop cycle
        </a>
    </div>
</div>
</x-app-layout>
