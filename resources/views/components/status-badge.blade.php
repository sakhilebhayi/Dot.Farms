@props(['status'])
<span class="dot-badge" style="background:{{ $status->color() }}1f;color:{{ $status->color() }};">{{ $status->label() }}</span>
