<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950 overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NUME | Gestão Estratégica de Produções')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}?v={{ file_exists(public_path('css/app-shell.css')) ? filemtime(public_path('css/app-shell.css')) : time() }}">
</head>
<body class="min-h-full bg-zinc-950 text-zinc-100 antialiased selection:bg-orange-500 selection:text-white overflow-x-hidden">

    <div id="app-shell" class="min-h-screen flex flex-col overflow-x-hidden">

        <!-- Mobile Sidebar Overlay -->
        <div id="sidebar-overlay" onclick="toggleSidebarMobile()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 hidden xl:hidden transition-opacity"></div>

        <!-- ================= SIDEBAR ================= -->
        <aside id="sidebar" class="fixed top-0 left-0 z-50 h-screen w-64 bg-zinc-900 border-r border-zinc-800 flex flex-col justify-between transition-all duration-300 -translate-x-full xl:translate-x-0">
            
            <!-- Top: Brand Header -->
            <div class="h-16 flex items-center justify-between px-5 border-b border-zinc-800 shrink-0">
                <a href="{{ route('productions.index') }}" class="flex items-center gap-3 overflow-hidden">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-7 w-auto object-contain">
                </a>
                <button onclick="toggleSidebarDesktop()" class="hidden xl:flex text-zinc-500 hover:text-zinc-300 p-1.5 rounded-lg hover:bg-zinc-800 transition" title="Recolher menu">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Middle: Nav Sections -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 [scrollbar-width:thin]">
                
                <!-- Section: Visão Geral -->
                <div class="space-y-1">
                    <p class="sidebar-heading px-3 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 mb-2">
                        {{ __('Visão Geral') }}
                    </p>
                    <a href="{{ route('productions.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('productions*') ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect width="7" height="9" x="3" y="3" rx="1" stroke-width="2"/>
                            <rect width="7" height="5" x="14" y="3" rx="1" stroke-width="2"/>
                            <rect width="7" height="9" x="14" y="12" rx="1" stroke-width="2"/>
                            <rect width="7" height="5" x="3" y="16" rx="1" stroke-width="2"/>
                        </svg>
                        <span class="sidebar-text">{{ __('Produções & Painel') }}</span>
                    </a>
                    <a href="{{ route('distributions.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('distributions*') ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                        <span class="sidebar-text">{{ __('Distribuição Social') }}</span>
                    </a>
                    <a href="{{ route('calendar.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('calendar*') ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="sidebar-text">{{ __('Calendário Geral') }}</span>
                    </a>
                    <a href="{{ route('batch_planner.index') }}"
                       class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('batch_planner*') ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span class="sidebar-text">{{ __('Sofi Batch Planner') }}</span>
                        </div>
                        <span class="sidebar-badge text-[9px] bg-orange-500/20 text-orange-400 px-1.5 py-0.5 rounded font-extrabold uppercase">AI</span>
                    </a>
                    <a href="{{ route('analytics.index') }}"
                       class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('analytics*') ? 'bg-orange-600 text-white shadow-lg shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="sidebar-text">{{ __('Vendas & BI (Sofi)') }}</span>
                        </div>
                        <span class="sidebar-badge text-[9px] bg-orange-500/20 text-orange-400 px-1.5 py-0.5 rounded font-extrabold uppercase">AI</span>
                    </a>
                </div>

                @php
                    $isCatalogoActive = request()->routeIs(['clientes*', 'packs*', 'admin.plans*', 'addons*', 'categories*', 'showcase-videos*']);
                    $isFinanceiroActive = request()->routeIs(['billing*', 'admin.invoices*', 'admin.subscriptions*', 'stripe-connect*', 'quotes*']);
                    $isSettingsActive = request()->routeIs(['admin.settings*', 'api_keys*']);
                @endphp

                <!-- Section: Catálogo & Cadastros (Dropdown Retrátil) -->
                <div class="space-y-1">
                    <button type="button" onclick="toggleNavGroup('catalogo')"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all {{ $isCatalogoActive ? 'text-white bg-zinc-800/50' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/30' }}">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span class="sidebar-text">{{ __('Catálogo & Cadastros') }}</span>
                        </div>
                        <svg id="menu-chevron-catalogo" class="sidebar-chevron w-4 h-4 text-zinc-500 transition-transform duration-200 {{ $isCatalogoActive ? 'rotate-180 text-orange-400' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="menu-group-catalogo" class="space-y-1 pl-3 border-l-2 border-zinc-800 ml-3.5 my-1 transition-all {{ $isCatalogoActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.plans.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.plans*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🔁</span>
                            <span class="sidebar-text">{{ __('Planos de Assinatura') }}</span>
                        </a>
                        <a href="{{ route('packs.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('packs*') && !request()->routeIs('admin.plans*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>📦</span>
                            <span class="sidebar-text">{{ __('Pacotes Avulsos') }}</span>
                        </a>
                        <a href="{{ route('addons.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('addons*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>⚡</span>
                            <span class="sidebar-text">{{ __('Serviços & Add-ons') }}</span>
                        </a>
                        <a href="{{ route('categories.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('categories*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🏷️</span>
                            <span class="sidebar-text">{{ __('Categorias & Tags') }}</span>
                        </a>
                        <a href="{{ route('showcase-videos.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('showcase-videos*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🎬</span>
                            <span class="sidebar-text">{{ __('Portfólio / Vitrine') }}</span>
                        </a>
                        <a href="{{ route('clientes.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('clientes*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>👥</span>
                            <span class="sidebar-text">{{ __('Clientes') }}</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Financeiro & Faturas (Dropdown Retrátil) -->
                <div class="space-y-1">
                    <button type="button" onclick="toggleNavGroup('financeiro')"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all {{ $isFinanceiroActive ? 'text-white bg-zinc-800/50' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/30' }}">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="sidebar-text">{{ __('Financeiro & Faturas') }}</span>
                        </div>
                        <svg id="menu-chevron-financeiro" class="sidebar-chevron w-4 h-4 text-zinc-500 transition-transform duration-200 {{ $isFinanceiroActive ? 'rotate-180 text-emerald-400' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="menu-group-financeiro" class="space-y-1 pl-3 border-l-2 border-zinc-800 ml-3.5 my-1 transition-all {{ $isFinanceiroActive ? '' : 'hidden' }}">
                        <a href="{{ route('quotes.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('quotes*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🧮</span>
                            <span class="sidebar-text">{{ __('Orçamentos & Propostas') }}</span>
                        </a>
                        <a href="{{ route('admin.subscriptions.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.subscriptions*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🔄</span>
                            <span class="sidebar-text">{{ __('Assinaturas dos Clientes') }}</span>
                        </a>
                        <a href="{{ route('admin.invoices.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.invoices*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>📄</span>
                            <span class="sidebar-text">{{ __('Facturas & AEAT') }}</span>
                        </a>
                        <a href="{{ route('billing.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('billing*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>💎</span>
                            <span class="sidebar-text">{{ __('Assinatura SaaS') }}</span>
                        </a>
                        <a href="{{ route('stripe-connect.index') }}"
                           class="flex items-center justify-between px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('stripe-connect*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <div class="flex items-center gap-2.5">
                                <span>⚡</span>
                                <span class="sidebar-text">{{ __('Stripe Connect') }}</span>
                            </div>
                            @if(auth()->check() && auth()->user()->tenant && auth()->user()->tenant->canReceivePayments())
                                <span class="sidebar-badge w-2 h-2 rounded-full bg-emerald-400"></span>
                            @else
                                <span class="sidebar-badge text-[9px] bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded font-bold">Pendente</span>
                            @endif
                        </a>
                    </div>
                </div>

                @if(auth()->check() && auth()->user()->isAdmin())
                <!-- Section: Configurações do SaaS (Dropdown Retrátil) -->
                <div class="space-y-1">
                    <button type="button" onclick="toggleNavGroup('configuracoes')"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all {{ $isSettingsActive ? 'text-white bg-zinc-800/50' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/30' }}">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="sidebar-text">{{ __('Configurações') }}</span>
                        </div>
                        <svg id="menu-chevron-configuracoes" class="sidebar-chevron w-4 h-4 text-zinc-500 transition-transform duration-200 {{ $isSettingsActive ? 'rotate-180 text-amber-400' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="menu-group-configuracoes" class="space-y-1 pl-3 border-l-2 border-zinc-800 ml-3.5 my-1 transition-all {{ $isSettingsActive ? '' : 'hidden' }}">
                        <a href="{{ route('admin.settings.gateways') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.settings.gateways*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>💳</span>
                            <span class="sidebar-text">{{ __('Gateways & Fiscal') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.platform') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.settings.platform*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🌐</span>
                            <span class="sidebar-text">{{ __('Redes & SEO Sofi') }}</span>
                        </a>
                        <a href="{{ route('api_keys.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('api_keys*') ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
                            <span>🔑</span>
                            <span class="sidebar-text">{{ __('API & Integrações') }}</span>
                        </a>
                    </div>
                </div>
                @endif
            </nav>

            <!-- Bottom: User Profile & Logout -->
            <div class="p-3 border-t border-zinc-800 space-y-2 shrink-0">
                <div class="flex items-center justify-between p-2 rounded-xl bg-zinc-950 border border-zinc-800/80">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-8 h-8 rounded-full bg-orange-600/20 border border-orange-500/30 text-orange-400 font-extrabold text-xs flex items-center justify-center shrink-0">
                            {{ substr(auth()->user()->name ?? 'U', 0, 2) }}
                        </div>
                        <div class="sidebar-text overflow-hidden">
                            <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name ?? 'Usuário' }}</p>
                            <p class="text-[10px] text-zinc-400 truncate">{{ auth()->user()->tenant->name ?? 'NUME Creative' }}</p>
                        </div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="sidebar-text inline">
                        @csrf
                        <button type="submit" class="p-1.5 text-zinc-500 hover:text-rose-400 transition" title="Encerrar Sessão">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- ================= MAIN WRAPPER ================= -->
        <div id="main-wrapper" class="flex-1 flex flex-col min-h-screen min-w-0 overflow-x-hidden transition-all duration-300 bg-zinc-950">

            <!-- Sticky Top Navbar -->
            <header class="sticky top-0 z-30 h-16 bg-zinc-950/80 backdrop-blur-md border-b border-zinc-800 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4 min-w-0">
                
                <!-- Left: Hamburger Mobile & Breadcrumb -->
                <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggleSidebarMobile()" class="xl:hidden p-2 rounded-xl text-zinc-400 hover:text-white hover:bg-zinc-800 transition shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <div class="hidden sm:flex items-center gap-2 text-xs font-medium truncate">
                        <span class="text-zinc-500">Painel</span>
                        <span class="text-zinc-600">/</span>
                        <span class="text-orange-500 font-semibold truncate">@yield('breadcrumb', 'Produções')</span>
                    </div>
                </div>

                <!-- Right: Workspace Tag, Search, Languages & Actions -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    
                    <!-- Workspace Badge -->
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-full bg-zinc-900 border border-zinc-800 text-xs shrink-0 max-w-[200px]">
                        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse shrink-0"></span>
                        <span class="text-zinc-400 font-medium shrink-0">Workspace:</span>
                        <span class="text-white font-bold truncate">{{ auth()->user()->tenant->name ?? 'NUME' }}</span>
                    </div>

                    <!-- Language Switcher -->
                    <div class="flex items-center gap-1.5 bg-zinc-900/80 border border-zinc-800 px-2 py-1 rounded-full text-base shrink-0">
                        <a href="{{ route('lang.switch', 'pt_BR') }}" class="px-1 rounded transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-80 hover:grayscale-0' }}" title="Português">🇧🇷</a>
                        <a href="{{ route('lang.switch', 'es') }}" class="px-1 rounded transition {{ session('locale') === 'es' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-80 hover:grayscale-0' }}" title="Español">🇪🇸</a>
                    </div>

                    <!-- Vitrine Pública Shortcut -->
                    @if(auth()->check() && auth()->user()->tenant)
                        <a href="{{ route('storefront.show', auth()->user()->tenant->slug) }}" target="_blank"
                           class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-zinc-300 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 transition shrink-0">
                            <span>{{ __('Ver Vitrine Pública') }}</span>
                            <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </header>

            <!-- Stripe Warning Banner -->
            @auth
                @if (auth()->user()->isAdmin() && !auth()->user()->tenant->canReceivePayments())
                    <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-b border-amber-500/20 text-amber-300 text-xs font-medium py-2.5 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span>⚡</span>
                            <span>{{ __('Conecte o Stripe Connect para habilitar checkout e recebimento direto dos seus pacotes.') }}</span>
                        </div>
                        <a href="{{ route('stripe-connect.index') }}" class="font-bold underline text-amber-400 hover:text-white transition">
                            {{ __('Conectar Agora →') }}
                        </a>
                    </div>
                @endif
            @endauth

            <!-- Main Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6 min-w-0">
                @if (session('warning'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-medium animate-fadeIn">
                        <span class="text-base">⚠️</span>
                        <div class="flex-1">{{ session('warning') }}</div>
                    </div>
                @endif
                @if (session('success'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-medium animate-fadeIn">
                        <span class="text-base">✓</span>
                        <div class="flex-1">{{ session('success') }}</div>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs font-medium animate-fadeIn">
                        <span class="text-base">✕</span>
                        <div class="flex-1">{{ session('error') }}</div>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="border-t border-zinc-900 py-6 text-center text-xs text-zinc-600">
                &copy; {{ date('Y') }} NUME Creative. {{ __('Plataforma de Gestão Audiovisual & Escala B2B.') }}
            </footer>
        </div>
    </div>

    <script>
        function toggleSidebarDesktop() {
            document.body.classList.toggle('sidebar-collapsed');
        }
        function toggleSidebarMobile() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
        function toggleNavGroup(groupId) {
            const group = document.getElementById('menu-group-' + groupId);
            const chevron = document.getElementById('menu-chevron-' + groupId);
            if (!group) return;
            const isHidden = group.classList.contains('hidden');
            if (isHidden) {
                group.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.add('rotate-180');
                }
            } else {
                group.classList.add('hidden');
                if (chevron) {
                    chevron.classList.remove('rotate-180');
                }
            }
        }
    </script>
</body>
</html>
