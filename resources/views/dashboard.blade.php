<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Farm Dashboard</h1>
            <p class="dot-muted" style="font-size:0.78rem;margin:0;">{{ now()->format('l, F j, Y') }}</p>
        </div>
        @can('create', \App\Models\Farm::class)
        <a href="{{ route('farms.create') }}" class="dot-btn dot-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span>
            New Farm
        </a>
        @endcan
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin-bottom:2rem;">
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div class="dot-kicker" style="margin-bottom:0.75rem;">Farms / Active Fields</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:var(--accent);">{{ $farmCount }} <span style="font-size:1rem;color:#8b8b94;">/ {{ $activeFieldCount }}</span></div>
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div class="dot-kicker" style="margin-bottom:0.75rem;">Crops In Season</div>
            <div class="metric-val" style="font-size:2rem;font-weight:600;color:#f59e0b;">{{ $cropsInSeasonCount }}</div>
            @if($overdueCount > 0)
                <a href="{{ route('reports.index') }}#outstanding" style="display:inline-block;margin-top:0.5rem;font-size:11.5px;color:#f87171;text-decoration:none;">{{ $overdueCount }} past expected harvest &rarr;</a>
            @endif
        </div>
        <div class="dot-card" style="padding:1.25rem 1.5rem;">
            <div class="dot-kicker" style="margin-bottom:0.75rem;">Harvested, last {{ $windowDays }} days</div>
            @if($window['records'] === 0)
                <div style="font-size:0.85rem;color:#a1a1aa;line-height:1.4;">Nothing harvested in the last {{ $windowDays }} days.</div>
            @else
                @foreach($window['totals']->all() as $bucket)
                    <div class="metric-val" style="font-size:{{ $loop->first ? '1.5rem' : '1rem' }};font-weight:600;color:#22c55e;">{{ $bucket['display'] }}</div>
                @endforeach
                <div class="dot-muted" style="margin-top:0.35rem;font-size:11.5px;">{{ $window['records'] }} harvest {{ \Illuminate\Support\Str::plural('record', $window['records']) }}</div>
            @endif
        </div>
    </div>

    <div class="dot-card" style="padding:1.25rem 1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <span class="dot-h3" style="font-size:13px;">Latest harvests</span>
            <a href="{{ route('harvests.index') }}" style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:600;">View all &rarr;</a>
        </div>

        @if($recentHarvests->isEmpty())
            <div style="text-align:center;padding:2.5rem 0;">
                <span class="material-symbols-rounded" style="font-size:32px;color:#52525b;display:block;margin-bottom:0.5rem;">nutrition</span>
                <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.25rem;">No harvests recorded yet.</p>
                <p class="dot-muted" style="font-size:0.78rem;margin:0;">Add a farm and a field, plan a crop cycle, record its planting, then record harvests from the cycle's page.</p>
            </div>
        @else
            <table class="dot-table">
                <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Crop</th>
                        <th class="num">Quantity</th>
                        <th>Grade</th>
                        <th>Harvested</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentHarvests as $harvest)
                    <tr>
                        <td><a href="{{ route('harvests.show', $harvest) }}" style="color:inherit;text-decoration:none;">{{ $harvest->cropCycle->field->farm->name }}</a></td>
                        <td style="color:#a1a1aa;">{{ $harvest->cropCycle->crop->displayName() }}</td>
                        <td class="num metric-val">{{ rtrim(rtrim(number_format((float) $harvest->quantity_harvested, 2), '0'), '.') }} {{ $harvest->unit }}</td>
                        <td>@if($harvest->quality_grade)<span class="dot-badge dot-badge-accent">{{ $harvest->quality_grade }}</span>@endif</td>
                        <td class="dot-muted">{{ $harvest->harvested_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
</x-app-layout>
