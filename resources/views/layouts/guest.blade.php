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

        <!-- Dark mode: apply persisted/system preference before paint to avoid a flash. -->
        <script>
            (function () {
                const stored = localStorage.getItem('dot-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
    </head>
    <body class="bg-[var(--paper)] dark:bg-gray-900">
        <div class="font-['Karla'] text-[var(--ink)] dark:text-gray-100 antialiased bg-[var(--paper)] dark:bg-gray-900 min-h-screen">
            <button
                type="button"
                onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('dot-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');"
                class="fixed top-4 right-4 z-50 inline-flex items-center justify-center h-9 w-9 rounded-full border border-[var(--line)] dark:border-gray-700 bg-white dark:bg-gray-800 text-[var(--ink-soft)] dark:text-gray-300 hover:text-[var(--ink)] dark:hover:text-white transition"
                title="Toggle dark mode"
            >
                <span class="dark:hidden">🌙</span>
                <span class="hidden dark:inline">☀️</span>
            </button>

            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
