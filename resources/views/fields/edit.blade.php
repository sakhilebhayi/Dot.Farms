<x-app-layout>
<div class="page-pad" style="padding:2rem 2.5rem 3rem;max-width:560px;">
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('fields.show', [$farm, $field]) }}" style="font-size:0.78rem;color:#a1a1aa;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
            Back to {{ $field->name }}
        </a>
    </div>

    <h1 style="font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:#f4f4f5;margin:0 0 1.5rem;">Edit Field</h1>
    <x-form-errors />

    <form method="POST" action="{{ route('fields.update', [$farm, $field]) }}" class="dot-card" style="padding:1.5rem;display:grid;gap:1rem;">
        @csrf
        @method('PUT')

        <div class="r-stack" style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
            <div>
                <label for="name" class="dot-label">Field name</label>
                <input id="name" name="name" type="text" class="dot-input" value="{{ old('name', $field->name) }}" required>
                @error('name')<p class="dot-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="code" class="dot-label">Code</label>
                <input id="code" name="code" type="text" class="dot-input" value="{{ old('code', $field->code) }}" required>
                @error('code')<p class="dot-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="r-stack" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="soil_type" class="dot-label">Soil type</label>
                <select id="soil_type" name="soil_type" class="dot-input">
                    <option value="">Unset</option>
                    @foreach(['loam', 'clay', 'sandy', 'silt', 'peat'] as $type)
                        <option value="{{ $type }}" @selected(old('soil_type', $field->soil_type) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="moisture_zone" class="dot-label">Moisture zone</label>
                <select id="moisture_zone" name="moisture_zone" class="dot-input">
                    <option value="">Unset</option>
                    @foreach(['dry', 'moderate', 'wet'] as $zone)
                        <option value="{{ $zone }}" @selected(old('moisture_zone', $field->moisture_zone) === $zone)>{{ ucfirst($zone) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="r-stack" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="size_hectares" class="dot-label">Size (hectares)</label>
                <input id="size_hectares" name="size_hectares" type="number" step="0.01" min="0" class="dot-input" value="{{ old('size_hectares', $field->size_hectares) }}">
                @error('size_hectares')<p class="dot-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="status" class="dot-label">Status</label>
                <select id="status" name="status" class="dot-input">
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $field->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
            <a href="{{ route('fields.show', [$farm, $field]) }}" class="dot-btn dot-btn-ghost">Cancel</a>
            <button type="submit" class="dot-btn dot-btn-primary">Save Changes</button>
        </div>
    </form>

    @can('delete', $field)
    <div class="dot-danger-zone">
        <h3 class="dot-h3" style="margin-bottom:0.35rem;">Delete this field</h3>
        @if($impact['cycles'] > 0)
            <p class="dot-muted" style="font-size:12.5px;margin:0 0 0.75rem;">
                This also deletes its {{ $impact['cycles'] }} crop {{ \Illuminate\Support\Str::plural('cycle', $impact['cycles']) }}
                and {{ $impact['harvests'] }} harvest {{ \Illuminate\Support\Str::plural('record', $impact['harvests']) }}.
            </p>
            <x-delete-form :action="route('fields.destroy', [$farm, $field])" label="Delete field and its cycles"
                cascade="I understand this removes {{ $impact['cycles'] }} crop {{ \Illuminate\Support\Str::plural('cycle', $impact['cycles']) }} and {{ $impact['harvests'] }} harvest {{ \Illuminate\Support\Str::plural('record', $impact['harvests']) }} along with the field." />
        @else
            <p class="dot-muted" style="font-size:12.5px;margin:0 0 0.75rem;">This field has no crop cycles, so nothing else is affected.</p>
            <x-delete-form :action="route('fields.destroy', [$farm, $field])" label="Delete field" confirm="Delete this field?" />
        @endif
        @error('confirm_cascade')<p class="dot-error">{{ $message }}</p>@enderror
    </div>
    @endcan
</div>
</x-app-layout>
