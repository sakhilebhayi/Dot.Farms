<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Farm Dashboard</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">{{ now()->format('l, F j, Y') }}</p>
        </div>
        <a href="{{ route('farms.create') }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            New Farm
        </a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;">
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Farms / Active Fields</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $farmCount }} <span style="font-size:1rem;color:#52525b;">/ {{ $activeFieldCount }}</span></div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Crops In Season</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#f59e0b;">{{ $cropsInSeasonCount }}</div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:#52525b;margin-bottom:0.75rem;">Recent Harvests</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#22c55e;">{{ $recentHarvests->count() }}</div>
        </div>
    </div>

    <div class="dot-card" style="padding:1.25rem 1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <span style="font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:#f4f4f5;">Recent Harvests</span>
            <a href="{{ route('harvests.index') }}" style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:600;">View all &rarr;</a>
        </div>

        @if($recentHarvests->isEmpty())
            <div style="text-align:center;padding:2.5rem 0;">
                <span class="material-symbols-rounded" style="font-size:32px;color:#3f3f46;display:block;margin-bottom:0.5rem;">nutrition</span>
                <p style="font-size:0.8rem;color:#52525b;margin:0;">No harvests recorded yet.</p>
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="text-align:left;color:#52525b;font-size:10px;text-transform:uppercase;letter-spacing:0.07em;">
                        <th style="padding:6px 8px;font-weight:600;">Farm</th>
                        <th style="padding:6px 8px;font-weight:600;">Crop</th>
                        <th style="padding:6px 8px;font-weight:600;">Quantity</th>
                        <th style="padding:6px 8px;font-weight:600;">Grade</th>
                        <th style="padding:6px 8px;font-weight:600;">Harvested</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentHarvests as $harvest)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="padding:8px;color:#d4d4d8;">
                            <a href="{{ route('harvests.show', $harvest) }}" style="color:inherit;text-decoration:none;">{{ $harvest->cropCycle->field->farm->name }}</a>
                        </td>
                        <td style="padding:8px;color:#a1a1aa;">{{ $harvest->cropCycle->crop->displayName() }}</td>
                        <td style="padding:8px;color:#d4d4d8;" class="metric-val">{{ number_format((float) $harvest->quantity_harvested, 2) }} {{ $harvest->unit }}</td>
                        <td style="padding:8px;">
                            @if($harvest->quality_grade)
                                <span class="dot-badge dot-badge-accent">{{ $harvest->quality_grade }}</span>
                            @endif
                        </td>
                        <td style="padding:8px;color:#71717a;">{{ $harvest->harvested_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
</x-app-layout>
