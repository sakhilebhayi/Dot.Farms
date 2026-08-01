<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="margin-bottom:2rem;">
        <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Harvests</h1>
        <p style="font-size:0.78rem;color:#52525b;margin:0;">All harvest records across every farm on this team</p>
    </div>

    @if($harvestRecords->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">nutrition</span>
            <p style="font-size:0.85rem;color:#52525b;margin:0;">No harvests recorded yet. Record one from a crop cycle's page.</p>
        </div>
    @else
        <div class="dot-card" style="padding:0;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="text-align:left;color:#52525b;font-size:10px;text-transform:uppercase;letter-spacing:0.07em;">
                        <th style="padding:12px 16px;font-weight:600;">Farm</th>
                        <th style="padding:12px 16px;font-weight:600;">Field</th>
                        <th style="padding:12px 16px;font-weight:600;">Crop</th>
                        <th style="padding:12px 16px;font-weight:600;">Quantity</th>
                        <th style="padding:12px 16px;font-weight:600;">Grade</th>
                        <th style="padding:12px 16px;font-weight:600;">Harvested</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($harvestRecords as $harvest)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="padding:10px 16px;">
                            <a href="{{ route('harvests.show', $harvest) }}" style="color:#d4d4d8;text-decoration:none;font-weight:600;">{{ $harvest->cropCycle->field->farm->name }}</a>
                        </td>
                        <td style="padding:10px 16px;color:#a1a1aa;">{{ $harvest->cropCycle->field->name }}</td>
                        <td style="padding:10px 16px;color:#a1a1aa;">{{ $harvest->cropCycle->crop->displayName() }}</td>
                        <td style="padding:10px 16px;color:#d4d4d8;" class="metric-val">{{ number_format((float) $harvest->quantity_harvested, 2) }} {{ $harvest->unit }}</td>
                        <td style="padding:10px 16px;">
                            @if($harvest->quality_grade)<span class="dot-badge dot-badge-accent">{{ $harvest->quality_grade }}</span>@endif
                        </td>
                        <td style="padding:10px 16px;color:#71717a;">{{ $harvest->harvested_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">
            {{ $harvestRecords->links() }}
        </div>
    @endif
</div>
</x-app-layout>
