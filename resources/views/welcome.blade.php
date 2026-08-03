<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Farms - Agriculture ERP for Crop Planning, Planting & Harvest</title>
        <meta name="description" content="Plan crop cycles, log planting and harvest events, and track yield across every field and season — the agriculture system of record for the Dot Ecosystem.">

        @fonts

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-20px); }
            }
            .float-animation {
                animation: float 6s ease-in-out infinite;
            }
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .slide-in-up {
                animation: slideInUp 0.8s ease-out forwards;
            }
        </style>
    </head>
    <body class="bg-gray-900 text-gray-100 antialiased">

        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" x-data="{ scrolled: false, mobileMenuOpen: false }"
                @scroll.window="scrolled = window.pageYOffset > 50"
                :class="scrolled ? 'bg-gray-900/95 backdrop-blur-xl shadow-lg border-b border-gray-800' : 'bg-transparent'">
            <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <a href="/" class="flex items-center gap-3 group">
                        <div class="relative">
                            <img src="{{ asset('images/logo.png') }}" alt="Dot.Farms" class="h-14 w-auto transform group-hover:scale-105 transition-transform duration-300">
                            <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                        </div>
                        <p class="hidden sm:block text-xs text-emerald-400 font-medium border-l border-gray-700 pl-3">Agriculture ERP</p>
                    </a>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center gap-8">
                        <a href="#features" class="text-gray-300 hover:text-emerald-400 transition-colors font-medium">Features</a>
                        <a href="#lifecycle" class="text-gray-300 hover:text-emerald-400 transition-colors font-medium">Crop Lifecycle</a>
                    </div>

                    <!-- Auth Links -->
                    @if (Route::has('login'))
                        <div class="flex items-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="hidden sm:flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-gray-900 font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/30 transform hover:scale-105">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    <span>Dashboard</span>
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="hidden sm:block px-4 py-2 text-gray-300 hover:text-white transition-colors font-medium">
                                    Sign In
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-gray-900 font-semibold rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/30 transform hover:scale-105">
                                        <span>Get Started</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </a>
                                @endif
                            @endauth

                            <!-- Mobile menu button -->
                            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-400 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="md:hidden mt-4 py-4 border-t border-gray-800"
                     style="display: none;">
                    <div class="flex flex-col gap-2">
                        <a href="#features" class="px-4 py-2 text-gray-300 hover:text-emerald-400 hover:bg-gray-800 rounded-lg transition-colors">Features</a>
                        <a href="#lifecycle" class="px-4 py-2 text-gray-300 hover:text-emerald-400 hover:bg-gray-800 rounded-lg transition-colors">Crop Lifecycle</a>
                        @guest
                            <a href="{{ route('login') }}" class="px-4 py-2 text-gray-300 hover:text-emerald-400 hover:bg-gray-800 rounded-lg transition-colors">Sign In</a>
                        @endguest
                    </div>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-20">
            <!-- Photographic Background: real aerial crop-field-rows photo by RECEP TİRYAKİ (@receqtryaki), unsplash.com/photos/an-aerial-view-of-a-farm-field-with-rows-of-crops-ATspM7IEDoI -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1729113707537-8c054ba97650?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/85 to-gray-900/60"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900 via-gray-900/40 to-transparent"></div>

            <!-- Floating Elements -->
            <div class="absolute top-20 left-10 w-64 h-64 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 float-animation"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-amber-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 float-animation" style="animation-delay: 2s;"></div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="max-w-3xl space-y-8 slide-in-up">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span>Agriculture System of Record</span>
                    </div>

                    <h2 class="text-5xl lg:text-7xl font-bold leading-tight">
                        <span class="bg-gradient-to-r from-white via-gray-100 to-gray-300 bg-clip-text text-transparent">Own the Field,</span><br>
                        <span class="bg-gradient-to-r from-emerald-400 via-emerald-500 to-emerald-600 bg-clip-text text-transparent">Paddock to Gate</span>
                    </h2>

                    <p class="text-xl text-gray-300 leading-relaxed max-w-xl">
                        Plan crop cycles, log planting and harvest events, and track yield across every field and season. Built for farm owners, agronomists, and field operators who need one system of record for what actually happens on the farm.
                    </p>

                    <!-- Key Stats -->
                    <div class="grid grid-cols-3 gap-6 py-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white mb-1">Farm →</div>
                            <div class="text-sm text-gray-400">Field → Cycle</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white mb-1">Plant →</div>
                            <div class="text-sm text-gray-400">Grow → Harvest</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white mb-1">Season</div>
                            <div class="text-sm text-gray-400">Yield Tracking</div>
                        </div>
                    </div>

                    @guest
                        <div class="flex flex-wrap gap-4">
                            <a href="{{ route('register') }}" class="group flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-gray-900 font-bold rounded-xl transition-all duration-300 shadow-2xl shadow-emerald-500/30 transform hover:scale-105">
                                <span>Get Started</span>
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                            <a href="{{ route('login') }}" class="flex items-center gap-2 px-8 py-4 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-xl transition-all duration-300 border border-gray-700 hover:border-gray-600">
                                <span>Sign In</span>
                            </a>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-4">
                            <a href="{{ url('/dashboard') }}" class="group flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-gray-900 font-bold rounded-xl transition-all duration-300 shadow-2xl shadow-emerald-500/30 transform hover:scale-105">
                                <span>Go to Dashboard</span>
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        </div>
                    @endguest
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-900/50 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-500/5 to-transparent"></div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium mb-6">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                        </svg>
                        <span>Core Features</span>
                    </div>
                    <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                        Everything You Need to<br>
                        <span class="bg-gradient-to-r from-emerald-400 to-emerald-600 bg-clip-text text-transparent">Run the Farm</span>
                    </h2>
                    <p class="text-xl text-gray-400 max-w-3xl mx-auto">
                        Purpose-built tools for the agriculture domain — from farm and field registry to season-by-season yield history
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-emerald-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Farm & Field Registry</h3>
                        <p class="text-gray-400 leading-relaxed">Register farms and fields, track soil type and moisture zone per paddock, and manage active, fallow, or retired status.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-amber-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 8v2m0-10a9 9 0 100 18 9 9 0 000-18z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Crop Cycle Planning</h3>
                        <p class="text-gray-400 leading-relaxed">Plan the full planted → growing → harvested lifecycle per field, per season, with a team-owned crop catalog reused across cycles.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-blue-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11H5a2 2 0 00-2 2v7a2 2 0 002 2h4m0-11v11m0-11h6m-6 11h6m0-11h4a2 2 0 012 2v7a2 2 0 01-2 2h-4m0-11v11"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Planting & Harvest Logs</h3>
                        <p class="text-gray-400 leading-relaxed">Record planting and harvest events as they happen in the field, building an operational log tied to each crop cycle.</p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-purple-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Yield Tracking</h3>
                        <p class="text-gray-400 leading-relaxed">Harvest records capture quantity harvested per cycle, giving you a season-by-season yield history for every field.</p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-red-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Team-Scoped Access</h3>
                        <p class="text-gray-400 leading-relaxed">Every farm, field, and record is scoped to the owning team, so multi-farm operations stay cleanly isolated from each other.</p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="group bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl border border-gray-700 hover:border-emerald-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 transform hover:-translate-y-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg shadow-cyan-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 group-hover:text-emerald-400 transition-colors">Operational Dashboard</h3>
                        <p class="text-gray-400 leading-relaxed">A plain, outcome-anchored summary of active fields, crops currently in season, and recent harvests — no vanity metrics.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Crop Lifecycle Section -->
        <section id="lifecycle" class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900"></div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/10 border border-amber-500/20 rounded-full text-amber-400 text-sm font-medium mb-6">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>Paddock to Gate</span>
                    </div>
                    <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                        One System, the Whole<br>
                        <span class="bg-gradient-to-r from-amber-400 to-amber-600 bg-clip-text text-transparent">Growing Season</span>
                    </h2>
                    <p class="text-xl text-gray-400 max-w-3xl mx-auto">
                        Dot.Farms is the system of record for what happens on the farm — not a marketplace. The moment produce is harvest-ready, the commercial lifecycle hands off downstream.
                    </p>
                </div>

                <div class="grid md:grid-cols-4 gap-6">
                    <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-2xl border border-gray-700 text-center">
                        <div class="w-12 h-12 mx-auto bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center mb-4 text-emerald-400 font-bold">1</div>
                        <h3 class="text-white font-semibold mb-2">Plan</h3>
                        <p class="text-sm text-gray-400">Set up the crop cycle for a field and season</p>
                    </div>
                    <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-2xl border border-gray-700 text-center">
                        <div class="w-12 h-12 mx-auto bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center mb-4 text-emerald-400 font-bold">2</div>
                        <h3 class="text-white font-semibold mb-2">Plant</h3>
                        <p class="text-sm text-gray-400">Log the planting event against the cycle</p>
                    </div>
                    <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-2xl border border-gray-700 text-center">
                        <div class="w-12 h-12 mx-auto bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center mb-4 text-emerald-400 font-bold">3</div>
                        <h3 class="text-white font-semibold mb-2">Grow</h3>
                        <p class="text-sm text-gray-400">Track cycle status through the season</p>
                    </div>
                    <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-2xl border border-gray-700 text-center">
                        <div class="w-12 h-12 mx-auto bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center mb-4 text-emerald-400 font-bold">4</div>
                        <h3 class="text-white font-semibold mb-2">Harvest</h3>
                        <p class="text-sm text-gray-400">Record the yield and hand off downstream</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
            <!-- Photographic Background: real sunset-over-farm-field photo by Mihail Ilchov (@archange1michael), unsplash.com/photos/the-sun-is-setting-over-a-farm-field-p6LxxduM5x0 -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1700605201690-ab19ba4a4c61?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0 bg-gray-900/90"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-600/20 via-emerald-500/10 to-transparent"></div>

            <div class="relative z-10 max-w-4xl mx-auto text-center">
                <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                    Ready to Run Your<br>
                    <span class="bg-gradient-to-r from-emerald-400 to-emerald-600 bg-clip-text text-transparent">Growing Season?</span>
                </h2>
                <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto">
                    Register your farm, add your fields, and start logging crop cycles from planting through harvest.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="group flex items-center gap-2 px-10 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-gray-900 font-bold rounded-xl transition-all duration-300 shadow-2xl shadow-emerald-500/30 transform hover:scale-105">
                            <span>Get Started</span>
                            <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <a href="{{ route('login') }}" class="flex items-center gap-2 px-10 py-4 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-xl transition-all duration-300 border border-gray-700 hover:border-gray-600">
                            <span>Sign In</span>
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-gray-800 bg-gray-900/50">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex flex-col items-center md:items-start">
                        <img src="{{ asset('images/logo.png') }}" alt="Dot.Farms" class="h-12 w-auto mb-3">
                        <p class="text-gray-400 text-sm text-center md:text-left">
                            The agriculture system of record for the Dot Ecosystem.
                        </p>
                    </div>
                    <p class="text-gray-400 text-sm">
                        &copy; {{ date('Y') }} Dot.Farms. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>

    </body>
</html>
