<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Crops</h1>
            <p class="dot-muted" style="font-size:0.78rem;margin:0;">Team-wide crop catalog, reused across farms and seasons</p>
        </div>
        @can('create', \App\Models\Crop::class)
        <a href="{{ route('crops.create') }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            New Crop
        </a>
        @endcan
    </div>

    <x-form-errors />

    @if($crops->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#52525b;display:block;margin-bottom:0.75rem;">grass</span>
            <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">No crops in the catalog yet.</p>
            <p class="dot-muted" style="font-size:0.8rem;margin:0 0 1rem;">Crops are what a crop cycle grows. Add the ones you plant, then plan a cycle on a field.</p>
            @can('create', \App\Models\Crop::class)
                <a href="{{ route('crops.create') }}" class="dot-btn dot-btn-primary">Add a crop</a>
            @else
                <p class="dot-muted" style="font-size:0.8rem;margin:0;">Ask a team owner or admin to add crops.</p>
            @endcan
        </div>
    @else
        <div class="dot-card" style="padding:0;overflow:hidden;">
            <div class="table-scroll">
            <table class="dot-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Typical cycle</th>
                        <th>Cycles</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($crops as $crop)
                    <tr>
                        <td style="font-weight:600;">{{ $crop->displayName() }}</td>
                        <td style="color:#a1a1aa;">{{ $crop->category ? ucfirst($crop->category) : '—' }}</td>
                        <td class="dot-muted">{{ $crop->typical_cycle_days ? $crop->typical_cycle_days.' days' : '—' }}</td>
                        <td class="dot-muted">{{ $crop->crop_cycles_count }}</td>
                        <td>
                            <div class="dot-row-actions">
                                @can('update', $crop)
                                    <a href="{{ route('crops.edit', $crop) }}" class="dot-btn dot-btn-ghost dot-btn-sm">Edit</a>
                                @endcan
                                @can('delete', $crop)
                                    @if($crop->crop_cycles_count > 0)
                                        <span class="dot-muted" style="font-size:11.5px;" title="Delete or re-plan its cycles first">in use by {{ $crop->crop_cycles_count }} {{ \Illuminate\Support\Str::plural('cycle', $crop->crop_cycles_count) }}</span>
                                    @else
                                        <x-delete-form :action="route('crops.destroy', $crop)" label="Delete" :small="true" :confirm="'Delete '.$crop->displayName().'?'" />
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>
</x-app-layout>
