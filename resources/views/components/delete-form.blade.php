{{--
    A DELETE form with its confirmation built in.
    - $cascade (string|null): when given, the form carries a required "confirm_cascade" checkbox with this
      wording (the server refuses the delete without it). Without it, the browser asks via confirm().
--}}
@props(['action', 'label' => 'Delete', 'confirm' => 'Delete this? It will disappear from Dot.Farms.', 'cascade' => null, 'small' => false])
<form method="POST" action="{{ $action }}" {{ $attributes->merge(['style' => $cascade ? 'margin:0;' : 'display:inline;margin:0;']) }} @unless($cascade) onsubmit="return confirm(@js($confirm))" @endunless>
    @csrf
    @method('DELETE')
    @if($cascade)
        <label class="dot-check">
            <input type="checkbox" name="confirm_cascade" value="1" required>
            <span>{{ $cascade }}</span>
        </label>
    @endif
    <button type="submit" class="dot-btn dot-btn-danger {{ $small ? 'dot-btn-sm' : '' }}">{{ $label }}</button>
</form>
