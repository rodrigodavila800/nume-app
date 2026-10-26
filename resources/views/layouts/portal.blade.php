<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal do Cliente | NUME - Vídeos Estratégicos')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                            950: '#431407',
                        }
                    }
                }
            }
        }
    </script>
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col bg-zinc-50 text-zinc-900 font-sans antialiased selection:bg-orange-500 selection:text-white">
    <!-- Header / Navbar Moderno -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-zinc-200/80 shadow-xs transition">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-18 items-center justify-between gap-4">
                
                <!-- Logo & Badge -->
                <div class="flex items-center gap-4">
                    <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-3 transition hover:opacity-90 group">
                        <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain">
                        <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-50 text-orange-700 border border-orange-200/70 shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                            {{ __('Portal') }}
                        </span>
                    </a>
                </div>

                <!-- Navegação Central (Abas Estilo Segmented Control) -->
                <nav class="hidden lg:flex items-center p-1 bg-zinc-100/90 rounded-xl border border-zinc-200/70 gap-1">
                    <a href="{{ route('portal.dashboard') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.dashboard*') || request()->routeIs('portal.productions*') || request()->routeIs('portal.roteiros*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        {{ __('Esteira de Produção') }}
                    </a>
                    <a href="{{ route('portal.profile.edit') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.profile*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        {{ __('DNA da Marca') }}
                    </a>
                    <a href="{{ route('portal.marketing-plan.index') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.marketing-plan*', 'portal.anamnesis*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ __('Grade Editorial') }}
                    </a>
                    <a href="{{ route('portal.social.index') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.social*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        {{ __('Redes Sociais') }}
                    </a>
                    <a href="{{ route('portal.plans.index') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.plans*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Planos') }}
                    </a>
                    <a href="{{ route('portal.packs.index') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.packs*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        {{ __('Pacotes') }}
                    </a>
                    <a href="{{ route('portal.quotes.index') }}" 
                       class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ request()->routeIs('portal.quotes*') ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60' : 'text-zinc-600 hover:text-zinc-900 hover:bg-white/60' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ __('Orçamentos') }}
                    </a>
                </nav>

                <!-- Área Direita: Idioma + Perfil + Logout -->
                <div class="flex items-center gap-3">
                    
                    <!-- Language Switcher Pill (Minimalista BR / ES) -->
                    <div class="inline-flex p-0.5 bg-zinc-100/90 rounded-lg border border-zinc-200/80 text-[11px] font-bold">
                        <a href="{{ route('lang.switch', 'pt_BR') }}" 
                           class="px-2.5 py-1 rounded-md transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60 font-extrabold' : 'text-zinc-500 hover:text-zinc-800' }}" 
                           title="Português (Brasil)">
                            BR
                        </a>
                        <a href="{{ route('lang.switch', 'es') }}" 
                           class="px-2.5 py-1 rounded-md transition {{ session('locale') === 'es' ? 'bg-white text-orange-600 shadow-xs border border-zinc-200/60 font-extrabold' : 'text-zinc-500 hover:text-zinc-800' }}" 
                           title="Español (España)">
                            ES
                        </a>
                    </div>

                    <!-- Perfil do Cliente -->
                    @php
                        $userClient = auth('cliente')->user();
                        $clientName = $userClient?->name ?? 'Cliente Conectado';
                        $initials = collect(explode(' ', $clientName))->filter()->take(2)->map(fn($w) => mb_substr($w, 0, 1))->join('');
                    @endphp

                    <div class="hidden sm:flex items-center gap-2.5 pl-3 border-l border-zinc-200">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-orange-600 to-amber-400 text-white font-extrabold text-xs flex items-center justify-center shadow-xs ring-2 ring-white">
                            {{ $initials ?: 'CL' }}
                        </div>
                        <div class="flex flex-col text-left">
                            <span class="text-xs font-bold text-zinc-800 max-w-[130px] truncate leading-tight">{{ $clientName }}</span>
                            <span class="text-[10px] text-zinc-500 font-medium flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 
                                {{ $userClient?->company_name ? \Illuminate\Support\Str::limit($userClient->company_name, 16) : 'Sessão Ativa' }}
                            </span>
                        </div>
                    </div>

                    <!-- Botão Logout com Ícone -->
                    <form action="{{ route('portal.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" 
                                title="{{ __('Encerrar Sessão') }}" 
                                class="p-2 rounded-xl text-zinc-400 hover:text-red-600 hover:bg-red-50/80 border border-transparent hover:border-red-100 transition flex items-center justify-center" 
                                aria-label="Sair">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>

                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="togglePortalMobileMenu()" class="lg:hidden p-2 rounded-xl text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition" aria-label="Abrir Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Dropdown Nav Menu -->
            <div id="portal-mobile-menu" class="hidden lg:hidden border-t border-zinc-100 py-3 space-y-1">
                <a href="{{ route('portal.dashboard') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.dashboard*') || request()->routeIs('portal.productions*') || request()->routeIs('portal.roteiros*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    {{ __('Esteira de Produção') }}
                </a>
                <a href="{{ route('portal.profile.edit') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.profile*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    {{ __('DNA da Marca & Redes') }}
                </a>
                <a href="{{ route('portal.marketing-plan.index') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.marketing-plan*', 'portal.anamnesis*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ __('Grade Editorial & Plano') }}
                </a>
                <a href="{{ route('portal.social.index') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.social*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    {{ __('Redes Sociais & Publicação') }}
                </a>
                <a href="{{ route('portal.plans.index') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.plans*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    {{ __('Planos (Retainers)') }}
                </a>
                <a href="{{ route('portal.packs.index') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.packs*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    {{ __('Pacotes (Avulsos)') }}
                </a>
                <a href="{{ route('portal.quotes.index') }}" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('portal.quotes*') ? 'bg-orange-50 text-orange-700' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('Orçamentos & Propostas') }}
                </a>
                
                <div class="pt-3 mt-2 border-t border-zinc-100 flex items-center justify-between px-3.5">
                    <span class="text-xs font-bold text-zinc-700 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ $clientName }}
                    </span>
                    <form action="{{ route('portal.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-extrabold text-red-600 hover:text-red-700">
                            {{ __('Sair da Conta') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 py-8 sm:py-10">
        @if (session('warning') || session('success') || session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3 mb-6">
                @if (session('warning'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold shadow-xs">
                        <span class="text-base">⚠️</span>
                        <div class="flex-1">{{ session('warning') }}</div>
                    </div>
                @endif
                @if (session('success'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold shadow-xs">
                        <span class="text-base">✓</span>
                        <div class="flex-1">{{ session('success') }}</div>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold shadow-xs">
                        <span class="text-base">✕</span>
                        <div class="flex-1">{{ session('error') }}</div>
                    </div>
                @endif
            </div>
        @endif
        @yield('content')
    </main>

    <script>
        function togglePortalMobileMenu() {
            const menu = document.getElementById('portal-mobile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
    </script>

    <!-- Footer -->
    <footer class="border-t border-zinc-200 bg-white py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-zinc-500">
            <div class="flex items-center gap-2">
                <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-5 w-auto object-contain opacity-70">
                <span>© {{ date('Y') }} NUME. Plataforma de Produção Audiovisual Focada em Retenção e Vendas.</span>
            </div>

            @if(!empty($portalTenant) && !empty($portalTenant->socialLinks()))
                <div class="flex items-center gap-3">
                    <a href="{{ route('legal.privacy') }}" class="text-[11px] text-zinc-400 hover:text-orange-600 transition underline mr-2">
                        Privacidade & RGPD
                    </a>
                    @if($portalTenant->social_instagram)
                        <a href="{{ str_starts_with($portalTenant->social_instagram, 'http') ? $portalTenant->social_instagram : 'https://instagram.com/' . ltrim($portalTenant->social_instagram, '@') }}" 
                           target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-pink-500 transition" title="Instagram">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    @endif
                    @if($portalTenant->social_youtube)
                        <a href="{{ $portalTenant->social_youtube }}" 
                           target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-red-500 transition" title="YouTube">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    @endif
                    @if($portalTenant->social_linkedin)
                        <a href="{{ $portalTenant->social_linkedin }}" 
                           target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-blue-500 transition" title="LinkedIn">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451c.979 0 1.778-.773 1.778-1.729V1.73C24 .774 23.205 0 22.225 0z"/></svg>
                        </a>
                    @endif
                    @if($portalTenant->social_tiktok)
                        <a href="{{ str_starts_with($portalTenant->social_tiktok, 'http') ? $portalTenant->social_tiktok : 'https://tiktok.com/@' . ltrim($portalTenant->social_tiktok, '@') }}" 
                           target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-cyan-400 transition" title="TikTok">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                        </a>
                    @endif
                    @if($portalTenant->social_whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $portalTenant->social_whatsapp) }}" 
                           target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-emerald-500 transition" title="WhatsApp Suporte">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </a>
                    @endif
                </div>
            @else
                <div class="flex items-center gap-4 font-medium">
                    <span class="text-orange-700 font-semibold">Suporte VIP & Aprovações em Tempo Real</span>
                </div>
            @endif
        </div>
    </footer>

    <!-- Sofi Copilot Chat Floating Widget -->
    @include('portal.components.sofi-chat-widget')

    <!-- SweetAlert2 & Sofi Alert Engine -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.sofiConfirm = function(options = {}) {
            const isEs = document.documentElement.lang === 'es' || '{{ session('locale') }}' === 'es';
            
            return Swal.fire({
                title: options.title || (isEs ? '¿Confirmar acción?' : 'Confirmar ação?'),
                text: options.text || '',
                icon: options.icon || 'question',
                iconColor: '#ea580c',
                showCancelButton: true,
                confirmButtonText: options.confirmText || (isEs ? 'Sí, continuar' : 'Sim, continuar'),
                cancelButtonText: options.cancelText || (isEs ? 'Cancelar' : 'Cancelar'),
                customClass: {
                    popup: 'rounded-3xl p-6 shadow-2xl border border-zinc-200 bg-white font-sans text-center',
                    title: 'text-lg font-black text-zinc-900',
                    htmlContainer: 'text-xs sm:text-sm text-zinc-600 mt-2 leading-relaxed',
                    confirmButton: 'px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-orange-600 hover:bg-orange-700 text-white shadow-md shadow-orange-900/20 transition mx-1 cursor-pointer',
                    cancelButton: 'px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition mx-1 cursor-pointer'
                },
                buttonsStyling: false,
                backdrop: 'rgba(15, 23, 42, 0.45)'
            });
        };

        window.sofiToast = function(title, type = 'success') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                icon: type,
                title: title,
                customClass: {
                    popup: 'rounded-2xl shadow-xl border border-zinc-200 bg-white text-xs font-bold'
                }
            });
        };
    </script>

    @stack('scripts')
</body>
</html>
