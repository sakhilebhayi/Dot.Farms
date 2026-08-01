<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Crops</h1>
            <p style="font-size:0.78rem;color:#52525b;margin:0;">Team-wide crop catalog, reused across farms and seasons</p>
        </div>
        <a href="{{ route('crops.create') }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            New Crop
        </a>
    </div>

    @if($crops->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#3f3f46;display:block;margin-bottom:0.75rem;">grass</span>
            <p style="font-size:0.85rem;color:#52525b;margin:0 0 1rem;">No crops in the catalog yet.</p>
            <a href="{{ route('crops.create') }}" class="dot-btn dot-btn-primary">Add a crop</a>
        </div>
    @else
        <div class="dot-card" style="padding:0;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="text-align:left;color:#52525b;font-size:10px;text-transform:uppercase;letter-spacing:0.07em;">
                        <th style="padding:12px 16px;font-weight:600;">Name</th>
                        <th style="padding:12px 16px;font-weight:600;">Category</th>
                        <th style="padding:12px 16px;font-weight:600;">Typical cycle</th>
                        <th style="padding:12px 16px;font-weight:600;">Cycles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($crops as $crop)
                    <tr style="border-top:1px solid rgba(255,255,255,0.06);">
                        <td style="padding:10px 16px;color:#d4d4d8;font-weight:600;">{{ $crop->displayName() }}</td>
                        <td style="padding:10px 16px;color:#a1a1aa;">{{ $crop->category ? ucfirst($crop->category) : '—' }}</td>
                        <td style="padding:10px 16px;color:#71717a;">{{ $crop->typical_cycle_days ? $crop->typical_cycle_days.' days' : '—' }}</td>
                        <td style="padding:10px 16px;color:#71717a;">{{ $crop->crop_cycles_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</x-app-layout>
