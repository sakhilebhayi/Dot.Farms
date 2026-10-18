<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Harvests</h1>
            <p class="dot-muted" style="font-size:0.78rem;margin:0;">Every harvest record across the farms on this team</p>
        </div>
        @if($recordCount > 0)
        <a href="{{ route('harvests.export', $filters->toQuery()) }}" class="dot-btn dot-btn-ghost">
            <span class="material-symbols-rounded" style="font-size:16px;">download</span>
            Export CSV{{ $filters->isActive() ? ' (filtered)' : '' }}
        </a>
        @endif
    </div>

    <x-form-errors />

    <form method="GET" action="{{ route('harvests.index') }}" class="dot-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
        <div class="dot-filter-bar">
            <div>
                <label class="dot-label" for="f-season">Season</label>
                <select id="f-season" name="season" class="dot-input">
                    <option value="">All seasons</option>
                    @foreach($seasons as $season)
                        <option value="{{ $season }}" @selected($filters->season === $season)>{{ $season }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="dot-label" for="f-crop">Crop</label>
                <select id="f-crop" name="crop_id" class="dot-input">
                    <option value="">All crops</option>
                    @foreach($crops as $crop)
                        <option value="{{ $crop->id }}" @selected($filters->cropId === $crop->id)>{{ $crop->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="dot-label" for="f-farm">Farm</label>
                <select id="f-farm" name="farm_id" class="dot-input">
                    <option value="">All farms</option>
                    @foreach($farms as $farm)
                        <option value="{{ $farm->id }}" @selected($filters->farmId === $farm->id)>{{ $farm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="dot-label" for="f-from">From</label>
                <input id="f-from" name="from" type="date" class="dot-input" value="{{ $filters->from }}">
            </div>
            <div>
                <label class="dot-label" for="f-to">To</label>
                <input id="f-to" name="to" type="date" class="dot-input" value="{{ $filters->to }}">
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="dot-btn dot-btn-primary">Filter</button>
                @if($filters->isActive())<a href="{{ route('harvests.index') }}" class="dot-btn dot-btn-ghost">Clear</a>@endif
            </div>
        </div>
    </form>

    @if($harvestRecords->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#52525b;display:block;margin-bottom:0.75rem;">nutrition</span>
            @if($filters->isActive())
                <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">No harvests match these filters.</p>
                <p class="dot-muted" style="font-size:0.8rem;margin:0;"><a href="{{ route('harvests.index') }}" style="color:var(--accent);">Clear the filters</a> to see every record.</p>
            @else
                <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">No harvests recorded yet.</p>
                <p class="dot-muted" style="font-size:0.8rem;margin:0;">A harvest is recorded on a crop cycle that has been planted. Open a field, plan a cycle, record its planting, then record the harvest from the cycle's page.</p>
            @endif
        </div>
    @else
        <div class="dot-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
            <div class="dot-kicker" style="margin-bottom:0.5rem;">{{ $recordCount }} {{ \Illuminate\Support\Str::plural('record', $recordCount) }}{{ $filters->isActive() ? ' matching' : '' }} · total harvested</div>
            <div class="dot-stat-line">
                @foreach($totals->all() as $bucket)
                    <span class="metric-val" style="font-size:1.1rem;font-weight:600;color:var(--accent);">{{ $bucket['display'] }}</span>
                @endforeach
            </div>
            @if(count($totals->all()) > 1)
                <p class="dot-muted" style="font-size:11.5px;margin:0.5rem 0 0;">Units that cannot be converted into each other are totalled separately, never added.</p>
            @endif
        </div>

        <div class="dot-card" style="padding:0;overflow:hidden;">
            <div class="table-scroll">
            <table class="dot-table">
                <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Field</th>
                        <th>Crop</th>
                        <th>Season</th>
                        <th class="num">Quantity</th>
                        <th>Grade</th>
                        <th>Harvested</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($harvestRecords as $harvest)
                    <tr>
                        <td><a href="{{ route('harvests.show', $harvest) }}" style="color:#f4f4f5;text-decoration:none;font-weight:600;">{{ $harvest->cropCycle->field->farm->name }}</a></td>
                        <td style="color:#a1a1aa;">{{ $harvest->cropCycle->field->name }}</td>
                        <td style="color:#a1a1aa;">{{ $harvest->cropCycle->crop->displayName() }}</td>
                        <td class="dot-muted">{{ $harvest->cropCycle->season }}</td>
                        <td class="num metric-val">{{ rtrim(rtrim(number_format((float) $harvest->quantity_harvested, 2), '0'), '.') }} {{ $harvest->unit }}</td>
                        <td>@if($harvest->quality_grade)<span class="dot-badge dot-badge-accent">{{ $harvest->quality_grade }}</span>@endif</td>
                        <td class="dot-muted">{{ $harvest->harvested_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        <div style="margin-top:1rem;">
            {{ $harvestRecords->links() }}
        </div>
    @endif
</div>
</x-app-layout>
