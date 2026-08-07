<div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4 overflow-hidden">
    {{-- Same hero photo as welcome.blade.php (aerial crop-field rows, RECEP TİRYAKİ). This
    platform's hero deliberately avoids a dark wash ("no dark wash" — see its own comment) in
    favor of a light paper-toned scrim, so the auth card mirrors that rather than the dark-ink
    treatment used elsewhere in this rollout. --}}
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1729113707537-8c054ba97650?q=80&w=2400&auto=format&fit=crop');"></div>
    <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 65% at 50% 35%, var(--paper) 0%, rgba(247,242,227,0.94) 45%, rgba(247,242,227,0.7) 72%, rgba(247,242,227,0.35) 100%);"></div>

    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-6 px-6 py-4 bg-white border border-[var(--line)] shadow-sm overflow-hidden sm:rounded-xl">
        {{ $slot }}
    </div>
</div>
