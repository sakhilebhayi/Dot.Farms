@php
    use App\Enums\CropCycleStatus as S;
    $status = $cropCycle->status;
    $params = [$farm, $field, $cropCycle];
    $transition = route('crop-cycles.transition', $params);
    $hasHarvests = $cropCycle->harvestRecords->isNotEmpty();
@endphp
<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;max-width:880px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('fields.show', [$farm, $field]) }}" style="font-size:0.78rem;color:#a1a1aa;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $field->name }}
        </a>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">{{ $cropCycle->crop->displayName() }}</h1>
            <p class="dot-muted" style="font-size:0.78rem;margin:0;">{{ $farm->name }} · {{ $field->name }} ({{ $field->code }}) · {{ $cropCycle->season }}</p>
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <x-status-badge :status="$status" />
            @if($canOperate)
                <a href="{{ route('crop-cycles.edit', $params) }}" class="dot-btn dot-btn-ghost dot-btn-sm">Edit</a>
            @endif
        </div>
    </div>

    <x-form-errors />

    {{-- Facts --}}
    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1.5rem;">
            <div>
                <div class="dot-kicker" style="margin-bottom:0.5rem;">Planted</div>
                <div style="font-size:13px;color:#d4d4d8;">{{ $cropCycle->planted_at?->format('M j, Y') ?? 'Not yet planted' }}</div>
            </div>
            <div>
                <div class="dot-kicker" style="margin-bottom:0.5rem;">Expected harvest</div>
                <div style="font-size:13px;color:#d4d4d8;">
                    {{ $cropCycle->expected_harvest_at?->format('M j, Y') ?? '—' }}
                    @if($cropCycle->isOverdue()) <span class="dot-badge" style="background:#ef44441f;color:#f87171;margin-left:4px;">overdue</span> @endif
                </div>
            </div>
            <div>
                <div class="dot-kicker" style="margin-bottom:0.5rem;">Harvested so far</div>
                <div class="metric-val" style="font-size:13px;color:#f4f4f5;">{{ $harvestTotals->toString('Nothing yet') }}</div>
            </div>
            @if($cropCycle->closed_at)
            <div>
                <div class="dot-kicker" style="margin-bottom:0.5rem;">Closed</div>
                <div style="font-size:13px;color:#d4d4d8;">{{ $cropCycle->closed_at->format('M j, Y') }}</div>
            </div>
            @endif
        </div>
        @if($cropCycle->end_reason)
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.06);">
            <div class="dot-kicker" style="margin-bottom:0.5rem;">Why it ended</div>
            <p style="font-size:12.5px;color:#d4d4d8;margin:0;">{{ $cropCycle->end_reason }}</p>
        </div>
        @endif
        @if($cropCycle->notes)
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.06);">
            <div class="dot-kicker" style="margin-bottom:0.5rem;">Notes</div>
            <p style="font-size:12.5px;color:#a1a1aa;margin:0;">{{ $cropCycle->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Lifecycle: only the moves the state machine allows from here --}}
    @if($canOperate)
    <div class="dot-card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <h3 class="dot-h3" style="margin-bottom:0.35rem;">Cycle status</h3>
        <p class="dot-muted" style="font-size:12px;margin:0 0 1rem;">
            @switch($status)
                @case(S::Planned) Not planted yet. Record a planting below to start the cycle. @break
                @case(S::Planted) In the ground. Mark it growing once the crop has emerged; harvests can be recorded now and do not close the cycle. @break
                @case(S::Growing) Growing. Record harvests as they happen; close the cycle when the harvest is complete. @break
                @default This cycle is closed. Re-open it to record more plantings or harvests.
            @endswitch
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-start;">
            @if($status->canTransitionTo(S::Growing) && $status === S::Planted)
                <form method="POST" action="{{ $transition }}" style="margin:0;">@csrf
                    <input type="hidden" name="action" value="growing">
                    <button type="submit" class="dot-btn dot-btn-ghost">Mark growing</button>
                </form>
            @endif
            @if($status->isInSeason())
                <form method="POST" action="{{ $transition }}" style="margin:0;">@csrf
                    <input type="hidden" name="action" value="close">
                    <button type="submit" class="dot-btn dot-btn-primary" @disabled(! $hasHarvests) @if(! $hasHarvests) title="Record at least one harvest first" @endif>Close as harvested</button>
                </form>
            @endif
            @if($status->isClosed())
                <form method="POST" action="{{ $transition }}" style="margin:0;">@csrf
                    <input type="hidden" name="action" value="reopen">
                    <button type="submit" class="dot-btn dot-btn-ghost">Re-open cycle</button>
                </form>
            @endif
        </div>

        @if($status->isOpen())
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:1rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.06);">
            @if($status->canTransitionTo(S::Failed))
            <form method="POST" action="{{ $transition }}" style="margin:0;">@csrf
                <input type="hidden" name="action" value="fail">
                <label class="dot-label" for="fail-reason">Mark failed (crop lost)</label>
                <div style="display:flex;gap:0.5rem;">
                    <input id="fail-reason" name="reason" type="text" class="dot-input" placeholder="What happened? e.g. hail, drought" required maxlength="1000">
                    <button type="submit" class="dot-btn dot-btn-danger">Mark failed</button>
                </div>
            </form>
            @endif
            <form method="POST" action="{{ $transition }}" style="margin:0;">@csrf
                <input type="hidden" name="action" value="abandon">
                <label class="dot-label" for="abandon-reason">Abandon (called off)</label>
                <div style="display:flex;gap:0.5rem;">
                    <input id="abandon-reason" name="reason" type="text" class="dot-input" placeholder="Why was it called off?" required maxlength="1000">
                    <button type="submit" class="dot-btn dot-btn-danger">Abandon</button>
                </div>
            </form>
        </div>
        @endif
    </div>
    @endif

    {{-- Plantings --}}
    <div class="dot-card" style="padding:0;margin-bottom:1.25rem;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem 0.75rem;"><h3 class="dot-h3">Plantings</h3></div>
        @if($cropCycle->plantingRecords->isEmpty())
            <p class="dot-muted" style="font-size:12.5px;margin:0;padding:0 1.5rem 1.25rem;">No planting recorded.@if($canOperate && $status->isOpen()) Record the first one below to start this cycle.@endif</p>
        @else
            <div class="table-scroll">
            <table class="dot-table">
                <thead><tr><th>Planted</th><th class="num">Quantity</th><th>Method</th><th></th></tr></thead>
                <tbody>
                    @foreach($cropCycle->plantingRecords as $planting)
                    <tr>
                        <td>{{ $planting->planted_at->format('M j, Y') }}</td>
                        <td class="num metric-val">{{ $planting->quantity_planted !== null ? rtrim(rtrim(number_format((float) $planting->quantity_planted, 2), '0'), '.').' '.$planting->unit : '—' }}</td>
                        <td class="dot-muted">{{ $planting->method ? ucfirst(str_replace('-', ' ', $planting->method)) : '—' }}</td>
                        <td>
                            <div class="dot-row-actions">
                                @if($canOperate)<a href="{{ route('plantings.edit', [...$params, $planting]) }}" class="dot-btn dot-btn-ghost dot-btn-sm">Edit</a>@endif
                                @if($canManage)<x-delete-form :action="route('plantings.destroy', [...$params, $planting])" label="Delete" :small="true" confirm="Delete this planting?" />@endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif

        @if($canOperate && $status->isOpen())
        <form method="POST" action="{{ route('crop-cycles.planting.store', $params) }}" style="display:grid;gap:1rem;padding:1.25rem 1.5rem;border-top:1px solid rgba(255,255,255,0.06);">
            @csrf
            <div class="dot-kicker">{{ $status === S::Planned ? 'Record planting' : 'Record a further planting' }}</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
                <div>
                    <label class="dot-label" for="planted_at">Planted on</label>
                    <input id="planted_at" name="planted_at" type="date" class="dot-input" value="{{ old('planted_at', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="dot-label" for="quantity_planted">Quantity</label>
                    <input id="quantity_planted" name="quantity_planted" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_planted') }}">
                </div>
                <div>
                    <label class="dot-label" for="planting_unit">Unit</label>
                    <select id="planting_unit" name="unit" class="dot-input">
                        @foreach($plantingUnits as $unit)
                            <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>{{ $unit->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="dot-label" for="method">Method</label>
                    <select id="method" name="method" class="dot-input">
                        <option value="">Unset</option>
                        <option value="direct-seed">Direct-seed</option>
                        <option value="transplant">Transplant</option>
                        <option value="broadcast">Broadcast</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="dot-btn dot-btn-primary">Record Planting</button>
            </div>
        </form>
        @endif
    </div>

    {{-- Harvests --}}
    <div class="dot-card" style="padding:0;margin-bottom:1.25rem;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem 0.75rem;display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;">
            <h3 class="dot-h3">Harvests</h3>
            @if($hasHarvests)<span class="metric-val dot-muted" style="font-size:12px;">Total {{ $harvestTotals->toString() }}</span>@endif
        </div>
        @if(! $hasHarvests)
            <p class="dot-muted" style="font-size:12.5px;margin:0;padding:0 1.5rem 1.25rem;">
                @if($status === S::Planned) Nothing to harvest yet: this cycle has not been planted.
                @elseif($status->isInSeason()) No harvest recorded yet.@if($canOperate) Record each pick below; the cycle stays open until you close it.@endif
                @else No harvest was recorded on this cycle. @endif
            </p>
        @else
            <div class="table-scroll">
            <table class="dot-table">
                <thead><tr><th>Harvested</th><th class="num">Quantity</th><th>Grade</th><th></th></tr></thead>
                <tbody>
                    @foreach($cropCycle->harvestRecords as $harvest)
                    <tr>
                        <td><a href="{{ route('harvests.show', $harvest) }}" style="color:#d4d4d8;text-decoration:none;font-weight:600;">{{ $harvest->harvested_at->format('M j, Y') }}</a></td>
                        <td class="num metric-val">{{ rtrim(rtrim(number_format((float) $harvest->quantity_harvested, 2), '0'), '.') }} {{ $harvest->unit }}</td>
                        <td>@if($harvest->quality_grade)<span class="dot-badge dot-badge-accent">{{ $harvest->quality_grade }}</span>@endif</td>
                        <td>
                            <div class="dot-row-actions">
                                @if($canOperate)<a href="{{ route('harvests.edit', $harvest) }}" class="dot-btn dot-btn-ghost dot-btn-sm">Edit</a>@endif
                                @if($canManage)<x-delete-form :action="route('harvests.destroy', $harvest)" label="Delete" :small="true" confirm="Delete this harvest record?" />@endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif

        @if($canOperate && $status->acceptsHarvests())
        <form method="POST" action="{{ route('crop-cycles.harvest.store', $params) }}" style="display:grid;gap:1rem;padding:1.25rem 1.5rem;border-top:1px solid rgba(255,255,255,0.06);">
            @csrf
            <div class="dot-kicker">Record harvest</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
                <div>
                    <label class="dot-label" for="harvested_at">Harvested on</label>
                    <input id="harvested_at" name="harvested_at" type="date" class="dot-input" value="{{ old('harvested_at', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="dot-label" for="quantity_harvested">Quantity</label>
                    <input id="quantity_harvested" name="quantity_harvested" type="number" step="0.01" min="0" class="dot-input" value="{{ old('quantity_harvested') }}" required>
                </div>
                <div>
                    <label class="dot-label" for="harvest_unit">Unit</label>
                    <select id="harvest_unit" name="unit" class="dot-input">
                        @foreach($harvestUnits as $unit)
                            <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>{{ $unit->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="dot-label" for="quality_grade">Quality grade</label>
                    <select id="quality_grade" name="quality_grade" class="dot-input">
                        <option value="">Unset</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="dot-btn dot-btn-primary">Record Harvest</button>
            </div>
        </form>
        @endif
    </div>

    @if($canManage)
    <div class="dot-danger-zone">
        <h3 class="dot-h3" style="margin-bottom:0.35rem;">Delete this cycle</h3>
        @if(($impact['plantings'] + $impact['harvests']) > 0)
            <p class="dot-muted" style="font-size:12.5px;margin:0 0 0.75rem;">This also deletes its {{ $impact['plantings'] }} {{ \Illuminate\Support\Str::plural('planting', $impact['plantings']) }} and {{ $impact['harvests'] }} harvest {{ \Illuminate\Support\Str::plural('record', $impact['harvests']) }}; the harvest totals drop accordingly.</p>
            <x-delete-form :action="route('crop-cycles.destroy', $params)" label="Delete cycle and its records"
                cascade="I understand this removes {{ $impact['plantings'] }} {{ \Illuminate\Support\Str::plural('planting', $impact['plantings']) }} and {{ $impact['harvests'] }} harvest {{ \Illuminate\Support\Str::plural('record', $impact['harvests']) }} with the cycle." />
        @else
            <p class="dot-muted" style="font-size:12.5px;margin:0 0 0.75rem;">Nothing has been recorded on this cycle.</p>
            <x-delete-form :action="route('crop-cycles.destroy', $params)" label="Delete cycle" confirm="Delete this cycle?" />
        @endif
    </div>
    @endif
</div>
</x-app-layout>
