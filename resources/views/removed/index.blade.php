@php
    use Illuminate\Support\Str;
    $typeNames = ['farm' => 'Farms', 'field' => 'Fields', 'crop' => 'Crops', 'cycle' => 'Crop cycles', 'planting' => 'Plantings', 'harvest' => 'Harvests'];
    $totalRemoved = array_sum($counts);
    $filtered = $type !== null || $search !== null;
@endphp
<x-app-layout>
<div style="padding:2rem 2.5rem 3rem;">
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#f4f4f5;margin:0 0 0.2rem;letter-spacing:-0.01em;">Removed</h1>
        <p class="dot-muted" style="font-size:0.78rem;margin:0;">
            Farms, fields, crops, crop cycles and records that were deleted on this team.
            They are deleted for good {{ $retentionDays }} days after removal.
            @unless($canRestore) Only an owner or admin can restore them. @endunless
        </p>
    </div>

    <x-form-errors />

    <form method="GET" action="{{ route('removed.index') }}" class="dot-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
        <div class="dot-filter-bar">
            <div>
                <label class="dot-label" for="f-type">Type</label>
                <select id="f-type" name="type" class="dot-input">
                    <option value="">All types ({{ $totalRemoved }})</option>
                    @foreach($typeNames as $key => $name)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $name }} ({{ $counts[$key] }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="dot-label" for="f-q">Search</label>
                <input id="f-q" name="q" type="search" class="dot-input" value="{{ $search }}" maxlength="100" placeholder="Name, code, season, crop, notes">
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="dot-btn dot-btn-primary">Filter</button>
                @if($filtered)<a href="{{ route('removed.index') }}" class="dot-btn dot-btn-ghost">Clear</a>@endif
            </div>
        </div>
    </form>

    @if($items->isEmpty())
        <div class="dot-card" style="padding:3rem 1.5rem;text-align:center;">
            <span class="material-symbols-rounded" style="font-size:36px;color:#52525b;display:block;margin-bottom:0.75rem;">restore_from_trash</span>
            @if($filtered)
                <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">No removed records match these filters.</p>
                <p class="dot-muted" style="font-size:0.8rem;margin:0;"><a href="{{ route('removed.index') }}" style="color:var(--accent);">Clear the filters</a> to see everything that was removed.</p>
            @else
                <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 0.35rem;">Nothing has been removed.</p>
                <p class="dot-muted" style="font-size:0.8rem;margin:0;">When someone deletes a farm, field, crop, crop cycle or record it lands here, and an owner or admin can restore it.</p>
            @endif
        </div>
    @else
        <div class="dot-card" style="padding:0;overflow:hidden;">
            <div style="overflow-x:auto;">
            <table class="dot-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Record</th>
                        <th>Removed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td><span class="dot-badge dot-badge-accent">{{ $item->typeLabel() }}</span></td>
                        <td>
                            <div style="font-weight:600;color:#f4f4f5;">{{ $item->title }}</div>
                            @if($item->detail !== '')<div class="dot-muted" style="font-size:11.5px;">{{ $item->detail }}</div>@endif
                        </td>
                        <td class="dot-muted" style="white-space:nowrap;" title="{{ $item->removedAt?->format('M j, Y H:i') }} UTC">
                            {{ $item->removedAt?->format('M j, Y') }}
                            <div style="font-size:11.5px;">{{ $item->removedAt?->diffForHumans() }}</div>
                        </td>
                        <td>
                            <div class="dot-row-actions">
                                @can('restore', $item->record)
                                    @if($item->blockedBy)
                                        <span class="dot-muted" style="font-size:11.5px;text-align:right;">
                                            Restore the {{ $item->blockedBy->label() }}
                                            <a href="{{ route('removed.index', ['type' => $item->blockedBy->type, 'q' => $item->blockedBy->name]) }}" style="color:var(--accent);">{{ $item->blockedBy->name }}</a>
                                            first
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('removed.restore', ['type' => $item->type, 'id' => $item->record->id]) }}" style="display:inline;margin:0;">
                                            @csrf
                                            @if($type)<input type="hidden" name="back_type" value="{{ $type }}">@endif
                                            @if($search)<input type="hidden" name="back_q" value="{{ $search }}">@endif
                                            <button type="submit" class="dot-btn dot-btn-ghost dot-btn-sm">
                                                <span class="material-symbols-rounded" style="font-size:15px;">restore_from_trash</span>
                                                Restore
                                            </button>
                                        </form>
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
        <div style="margin-top:1rem;">
            {{ $items->links() }}
        </div>
    @endif
</div>
</x-app-layout>
