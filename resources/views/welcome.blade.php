<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Farms — Agriculture ERP for crop planning, planting &amp; harvest</title>
        <meta name="description" content="Plan crop cycles, log planting and harvest events, and track yield across every field and season — the agriculture system of record for the Dot Ecosystem.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Karla:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
            html { background: var(--paper); }
            body { font-family: var(--font-body); background: var(--paper); color: var(--ink); }
            .font-display { font-family: var(--font-display); font-optical-sizing: auto; }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(38, 49, 28, 0.03); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            x-data="{ scrolled: false, mobileMenuOpen: false }"
            @scroll.window="scrolled = window.pageYOffset > 24"
            :class="scrolled ? 'bg-[#f7f2e3]/95 backdrop-blur-md border-b border-[var(--line)]' : 'border-b border-transparent'"
            class="fixed top-0 left-0 right-0 z-50 transition-colors duration-300"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Farms" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="#lifecycle" class="link-underline hover:text-[var(--ink)] pb-0.5">Lifecycle</a>
                    <a href="#features" class="link-underline hover:text-[var(--ink)] pb-0.5">Features</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--forest)] hover:bg-[var(--forest-deep)] text-[var(--paper)] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--ink-soft)] hover:text-[var(--ink)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--forest)] hover:bg-[var(--forest-deep)] text-[var(--paper)] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Create account
                                </a>
                            @endif
                        @endauth

                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden press p-2 -mr-2 text-[var(--ink)]" aria-label="Toggle menu">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                                <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div x-show="mobileMenuOpen"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="md:hidden border-t border-[var(--line)] bg-[var(--paper)]"
                 style="display: none;">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#lifecycle" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Lifecycle</a>
                    <a href="#features" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Features</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-end overflow-hidden">
            <!-- Photo: aerial crop-field-rows, by RECEP TİRYAKİ, unsplash.com/photos/an-aerial-view-of-a-farm-field-with-rows-of-crops-ATspM7IEDoI -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1729113707537-8c054ba97650?q=80&w=2400&auto=format&fit=crop');"></div>
            <!-- Wide screens: soft vertical grounding + diagonal reveal keeps most of the photo visible, text sits in the opaque left band -->
            <div class="absolute inset-0 hidden lg:block" style="background: linear-gradient(180deg, rgba(38,49,28,0.4) 0%, rgba(38,49,28,0.6) 45%, #f7f2e3 94%);"></div>
            <div class="absolute inset-0 hidden lg:block" style="background: linear-gradient(90deg, #f7f2e3 0%, rgba(247,242,227,0.72) 32%, transparent 62%);"></div>
            <!-- Narrow screens: text spans nearly the full width, so it needs a strong uniform scrim (no dark wash) for reliable contrast -->
            <div class="absolute inset-0 lg:hidden" style="background: linear-gradient(180deg, rgba(247,242,227,0.88) 0%, rgba(247,242,227,0.88) 55%, #f7f2e3 100%);"></div>

            <!-- Furrow rows — line-art nod to the plowed-field icon in the Dot.Farms mark -->
            <svg class="hidden lg:block absolute right-[4%] bottom-0 h-[70%] w-auto opacity-[0.16] pointer-events-none" viewBox="0 0 260 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M10 300L130 190L250 300" stroke="#26311c" stroke-width="2.5"/>
                <path d="M35 300L130 215L225 300" stroke="#26311c" stroke-width="2.5"/>
                <path d="M60 300L130 240L200 300" stroke="#26311c" stroke-width="2.5"/>
                <path d="M85 300L130 265L175 300" stroke="#26311c" stroke-width="2.5"/>
                <path d="M130 190V60" stroke="#26311c" stroke-width="2.5"/>
                <path d="M130 60C112 60 98 46 98 28" stroke="#26311c" stroke-width="2.5" stroke-linecap="round"/>
                <path d="M130 100C148 100 162 86 162 68" stroke="#26311c" stroke-width="2.5" stroke-linecap="round"/>
                <path d="M85 190V115" stroke="#26311c" stroke-width="1.5"/>
                <path d="M85 115C71 115 60 104 60 90" stroke="#26311c" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M175 190V125" stroke="#26311c" stroke-width="1.5"/>
                <path d="M175 125C189 125 200 114 200 100" stroke="#26311c" stroke-width="1.5" stroke-linecap="round"/>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--soil)] mb-6">
                        Agriculture ERP
                    </p>

                    <h1 class="font-display font-semibold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--ink)] mb-6">
                        Own the field.<br>Not the sale.
                    </h1>

                    <p class="text-lg text-[var(--ink-soft)] leading-relaxed max-w-xl mb-10">
                        Dot.Farms is the system of record for what happens on the farm — crop planning, planting and harvest execution, and yield tracking, from paddock to gate. The moment produce is harvest-ready, the commercial lifecycle hands off downstream.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--forest)] hover:bg-[var(--forest-deep)] text-[var(--paper)] font-display font-semibold rounded-lg transition-colors">
                                Create account
                            </a>
                            <a href="#features" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--ink)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--soil)] transition-colors">
                                See what it tracks
                            </a>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ url('/dashboard') }}" class="press px-7 py-3.5 bg-[var(--forest)] hover:bg-[var(--forest-deep)] text-[var(--paper)] font-display font-semibold rounded-lg transition-colors">
                                Go to dashboard
                            </a>
                        </div>
                    @endguest
                </div>
            </div>

            <!-- Domain strip — the real entity chain, not a fabricated metric -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[var(--paper)]/70 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--ink-soft)]">
                    <span>Farm &amp; field registry</span>
                    <span class="text-[var(--soil)]">·</span>
                    <span>Crop cycles</span>
                    <span class="text-[var(--soil)]">·</span>
                    <span>Planting &amp; harvest logs</span>
                    <span class="text-[var(--soil)]">·</span>
                    <span>Season yield tracking</span>
                </div>
            </div>
        </section>

        <!-- Lifecycle -->
        <section id="lifecycle" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--soil)] mb-4">Paddock to gate</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight">
                        One system, the whole growing season
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $lifecycle = [
                            ['tag' => '01 · Plan', 'title' => 'Set up the crop cycle', 'body' => 'Register the field, season, and crop against a team-owned crop catalog reused across cycles and fields.'],
                            ['tag' => '02 · Plant', 'title' => 'Log the planting event', 'body' => 'Record a planting record against the cycle as it happens in the field, not after the fact from memory.'],
                            ['tag' => '03 · Grow', 'title' => 'Track cycle status', 'body' => 'A crop cycle moves through planned, planted, growing, harvested, or failed — visible for every field, every season.'],
                            ['tag' => '04 · Harvest', 'title' => 'Record the yield', 'body' => 'A harvest record captures quantity harvested and marks the cycle complete — the ground-truth yield figure for the season.'],
                        ];
                    @endphp
                    @foreach ($lifecycle as $i => $l)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--soil)] mb-3">{{ $l['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--ink)] mb-2.5">{{ $l['title'] }}</h3>
                            <p class="text-[var(--ink-soft)] leading-relaxed max-w-md">{{ $l['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--paper-deep)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--soil)] mb-4">What it does</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight mb-5">
                            Built for farm owners, agronomists, and field operators
                        </h2>
                        <p class="text-[var(--ink-soft)] leading-relaxed max-w-sm">
                            No hardware to install, no marketplace to opt into. Register a farm, add fields, and the rest of the platform is ready.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-10">
                        @php
                            $features = [
                                ['title' => 'Farm & field registry', 'body' => 'Register farms and fields, track soil type and moisture zone per paddock, and manage active, fallow, or retired status.'],
                                ['title' => 'Crop catalog', 'body' => 'A team-owned catalog of crops and varieties, reused across every field and season instead of re-entered each cycle.'],
                                ['title' => 'Planting & harvest logs', 'body' => 'Operational logs tied to each crop cycle, timestamped as planting and harvest events actually happen.'],
                                ['title' => 'Season-by-season yield', 'body' => 'Harvest records build a yield history per field, so you can see what a season actually produced.'],
                                ['title' => 'Team-scoped tenancy', 'body' => 'Every farm, field, and record is scoped to the owning team, enforced at the policy layer, not just the UI.'],
                                ['title' => 'Outcome-anchored dashboard', 'body' => 'A plain operational summary — active fields, crops in season, recent harvests. No streaks, no vanity metrics.'],
                            ];
                        @endphp
                        @foreach ($features as $f)
                            <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                                <h3 class="font-display font-medium text-base text-[var(--ink)] mb-1.5">{{ $f['title'] }}</h3>
                                <p class="text-sm text-[var(--ink-soft)] leading-relaxed">{{ $f['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <!-- Photo: sunset over a farm field, by Mihail Ilchov, unsplash.com/photos/the-sun-is-setting-over-a-farm-field-p6LxxduM5x0 -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1700605201690-ab19ba4a4c61?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, #26311c 0%, rgba(38,49,28,0.82) 50%, #26311c 100%);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Run the season the way your team already tracks it
                </h2>
                <p class="text-[var(--paper-deep)] leading-relaxed mb-10 max-w-lg mx-auto opacity-90">
                    Register your farm, add your fields, and start logging crop cycles from planting through harvest.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold rounded-lg transition-colors">
                            Create account
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--paper)] font-medium rounded-lg border border-[rgba(247,242,227,0.28)] hover:border-[rgba(247,242,227,0.5)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo.png') }}" alt="Dot.Farms" class="h-11 w-auto opacity-90">
                </a>
                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="{{ route('policy.show') }}" class="hover:text-[var(--ink)] transition-colors">Privacy</a>
                    <a href="{{ route('cookies') }}" class="hover:text-[var(--ink)] transition-colors">Cookies</a>
                    <a href="{{ route('terms.show') }}" class="hover:text-[var(--ink)] transition-colors">Terms</a>
                </div>
                <p class="font-mono text-xs tracking-wide text-[var(--ink-soft)]">
                    &copy; {{ date('Y') }} Dot.Farms. Agriculture ERP for the Dot Ecosystem.
                </p>
            </div>
        </footer>

        <script>
            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
