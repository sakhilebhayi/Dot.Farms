@if($errors->any())
    <div class="dot-alert" role="alert">
        @foreach($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif
