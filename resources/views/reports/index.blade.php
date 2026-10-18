<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Reports</h1>
            <p class="dot-muted" style="font-size:0.78rem;margin:0;">Yield, harvest totals and unfinished cycles, from the records on this team</p>
        </div>
        @if($hasHarvests)
        <a href="{{ route('harvests.export', array_filter(['season' => $season])) }}" class="dot-btn dot-btn-ghost">
            <span class="material-symbols-rounded" style="font-size:16px;">download</span>
            Export harvests (CSV)
        </a>
        @endif
    </div>

    @unless($hasAnything)
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#52525b;display:block;margin-bottom:0.75rem;">insights</span>
            <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">Nothing to report yet.</p>
            <p class="dot-muted" style="font-size:0.8rem;margin:0 auto;max-width:460px;">Reports are built from crop cycles and their harvests. Add a farm and a field, plan a crop cycle, record a planting, then record a harvest: yield per field, monthly totals and the list of unfinished cycles appear here.</p>
        </div>
    @else
        @if(count($seasons) > 0)
        <form method="GET" action="{{ route('reports.index') }}" style="display:flex;gap:0.5rem;align-items:end;margin-bottom:1.5rem;flex-wrap:wrap;">
            <div style="min-width:200px;">
                <label class="dot-label" for="season">Season</label>
                <select id="season" name="season" class="dot-input" onchange="this.form.submit()">
                    <option value="">All seasons</option>
                    @foreach($seasons as $option)
                        <option value="{{ $option }}" @selected($season === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="dot-btn dot-btn-ghost">Apply</button></noscript>
            @if($season)<a href="{{ route('reports.index') }}" class="dot-btn dot-btn-ghost">Clear</a>@endif
        </form>
        @endif

        {{-- Yield per field, per season --}}
        <section style="margin-bottom:2rem;">
            <h2 class="dot-h3" style="font-size:1rem;margin-bottom:0.25rem;">Yield per field, per season</h2>
            <p class="dot-muted" style="font-size:12px;margin:0 0 0.75rem;">Per hectare uses the area planted when plantings are recorded in hectares, otherwise the field's size. Units that cannot be converted are shown separately, never added.</p>

            @forelse($yieldsBySeason as $seasonName => $rows)
                <div class="dot-card" style="padding:0;margin-bottom:1rem;overflow:hidden;">
                    <div style="padding:0.85rem 1.1rem 0.5rem;"><span class="dot-kicker">{{ $seasonName }}</span></div>
                    <div style="overflow-x:auto;">
                    <table class="dot-table" style="table-layout:fixed;min-width:760px;">
                        <colgroup><col style="width:22%"><col style="width:20%"><col style="width:12%"><col style="width:13%"><col style="width:17%"><col style="width:16%"></colgroup>
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Crop</th>
                                <th>Status</th>
                                <th class="num">Area</th>
                                <th class="num">Harvested</th>
                                <th class="num">Per hectare</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('crop-cycles.show', [$row['cycle']->field->farm, $row['cycle']->field, $row['cycle']]) }}" style="color:#f4f4f5;text-decoration:none;font-weight:600;">{{ $row['field'] }}</a>
                                    <div class="dot-muted" style="font-size:11.5px;">{{ $row['farm'] }}</div>
                                </td>
                                <td style="color:#a1a1aa;">{{ $row['crop'] }}</td>
                                <td><x-status-badge :status="$row['cycle']->status" /></td>
                                <td class="num metric-val">
                                    @if($row['area_ha'] !== null)
                                        {{ rtrim(rtrim(number_format($row['area_ha'], 2), '0'), '.') }} ha
                                        <div class="dot-muted" style="font-size:10.5px;font-family:Inter,sans-serif;">{{ $row['area_basis'] === 'planted' ? 'planted area' : 'field size' }}</div>
                                    @else
                                        <span class="dot-muted" title="Set the field size or record the planted area in hectares">area unknown</span>
                                    @endif
                                </td>
                                <td class="num metric-val">
                                    @forelse($row['harvested']->all() as $bucket)<div>{{ $bucket['display'] }}</div>@empty<span class="dot-muted">none yet</span>@endforelse
                                </td>
                                <td class="num metric-val" style="color:var(--accent);">
                                    @forelse($row['per_ha']->all() as $bucket)<div>{{ $bucket['display'] }}/ha</div>@empty<span class="dot-muted">—</span>@endforelse
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @empty
                <div class="dot-card" style="padding:1.5rem;"><p class="dot-muted" style="font-size:12.5px;margin:0;">No crop cycles{{ $season ? " in {$season}" : '' }}.</p></div>
            @endforelse
        </section>

        {{-- Harvest totals per crop and month --}}
        <section style="margin-bottom:2rem;">
            <h2 class="dot-h3" style="font-size:1rem;margin-bottom:0.75rem;">Harvest by crop and month</h2>
            @if($byMonth->isEmpty())
                <div class="dot-card" style="padding:1.5rem;"><p class="dot-muted" style="font-size:12.5px;margin:0;">No harvests recorded{{ $season ? " in {$season}" : '' }} yet. Totals by crop and month appear once a harvest is recorded on a planted cycle.</p></div>
            @else
                <div class="dot-card" style="padding:0;overflow:hidden;">
                    <div style="overflow-x:auto;">
                    <table class="dot-table">
                        <thead>
                            <tr><th>Month</th><th>Crop</th><th class="num">Harvested</th><th class="num">Records</th></tr>
                        </thead>
                        <tbody>
                            @foreach($byMonth as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td style="color:#a1a1aa;">{{ $row['crop'] }}</td>
                                <td class="num metric-val">@foreach($row['totals']->all() as $bucket)<div>{{ $bucket['display'] }}</div>@endforeach</td>
                                <td class="num dot-muted">{{ $row['records'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif
        </section>

        {{-- Outstanding cycles --}}
        <section id="outstanding">
            <h2 class="dot-h3" style="font-size:1rem;margin-bottom:0.25rem;">Outstanding cycles</h2>
            <p class="dot-muted" style="font-size:12px;margin:0 0 0.75rem;">Cycles that are planned, planted or growing and not yet closed. Overdue ones (past their expected harvest date) come first.</p>
            @if($outstanding->isEmpty())
                <div class="dot-card" style="padding:1.5rem;"><p class="dot-muted" style="font-size:12.5px;margin:0;">Every cycle is closed. Nothing outstanding.</p></div>
            @else
                <div class="dot-card" style="padding:0;overflow:hidden;">
                    <div style="overflow-x:auto;">
                    <table class="dot-table">
                        <thead>
                            <tr><th>Cycle</th><th>Status</th><th>Planted</th><th>Expected harvest</th><th class="num">Harvested so far</th></tr>
                        </thead>
                        <tbody>
                            @foreach($outstanding as $row)
                            @php($cycle = $row['cycle'])
                            <tr>
                                <td>
                                    <a href="{{ route('crop-cycles.show', [$cycle->field->farm, $cycle->field, $cycle]) }}" style="color:#f4f4f5;text-decoration:none;font-weight:600;">{{ $cycle->crop->displayName() }}</a>
                                    <div class="dot-muted" style="font-size:11.5px;">{{ $cycle->field->farm->name }} · {{ $cycle->field->name }} · {{ $cycle->season }}</div>
                                </td>
                                <td><x-status-badge :status="$cycle->status" /></td>
                                <td class="dot-muted">{{ $cycle->planted_at?->format('M j, Y') ?? 'not planted' }}@if($row['days_in_ground'] !== null) <span style="font-size:11px;">({{ $row['days_in_ground'] }} d)</span>@endif</td>
                                <td>
                                    {{ $cycle->expected_harvest_at?->format('M j, Y') ?? '—' }}
                                    @if($row['overdue']) <span class="dot-badge" style="background:#ef44441f;color:#f87171;margin-left:4px;">overdue</span>@endif
                                </td>
                                <td class="num metric-val">{{ $row['harvested']->toString('—') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif
        </section>
    @endunless
</div>
</x-app-layout>
