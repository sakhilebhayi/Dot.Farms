<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Karla:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles

        <!-- Dot.Farms brand tokens — copied verbatim from resources/views/welcome.blade.php so the same
             palette/type system is available on every auth page. Do not fork these values here; if the
             welcome page's tokens change, update both. -->
        <style>
            :root {
                --paper: #f7f2e3;
                --paper-deep: #efe6cd;
                --ink: #26311c;
                --ink-soft: #3a4629;
                --forest: #3f5a28;
                --forest-deep: #2a3d1a;
                --gold: #dda52e;
                --gold-soft: #f0c862;
                --soil: #8a6239;
                --line: rgba(38, 49, 28, 0.14);
                --font-display: 'Fraunces', ui-serif, Georgia, serif;
                --font-body: 'Karla', system-ui, sans-serif;
                --font-mono: 'Space Mono', ui-monospace, monospace;
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            .font-display { font-family: var(--font-display); font-optical-sizing: auto; }
            .font-mono { font-family: var(--font-mono); }
        </style>

    </head>
    <body class="bg-[var(--paper)]">
        {{-- Single fixed brand theme (paper/ink/forest/gold), matching welcome.blade.php. The
             dark-mode toggle previously here (button + persisted/system-preference script) is
             removed: no page in this file family actually renders correctly in dark mode (the
             wrapper's dark: classes were fixed to one deliberate light look during the ecosystem
             standardization pass, but several of the auth pages' own content — e.g. login.blade.php's
             "Sign in" heading — still had live dark:text-gray-100 variants with no matching dark
             background once the toggle triggered, producing invisible text against a light backdrop
             for any visitor with a dark-mode OS preference). Same convention as the marketing page:
             one deliberate look, no toggle. --}}
        <div class="font-['Karla'] text-[var(--ink)] antialiased bg-[var(--paper)] min-h-screen">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
