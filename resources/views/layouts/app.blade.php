<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NUME | Gestão Estratégica de Produções')</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}?v={{ file_exists(public_path('css/app-shell.css')) ? filemtime(public_path('css/app-shell.css')) : time() }}">
    @include('partials.favicons')
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menu">
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    @php
        $pendingQuoteRequestsCount = auth()->check() ? \App\Models\Quote::where('status', 'requested')->count() : 0;
        $openSupportTicketsCount = auth()->check() ? \App\Models\SupportTicket::whereIn('status', \App\Models\SupportTicket::OPEN_STATUSES)->count() : 0;
        $pendingPlanAdjustmentsCount = auth()->check() ? \App\Models\MarketingPlan::where('status', \App\Models\MarketingPlan::STATUS_ADJUSTMENT)->count() : 0;
    @endphp

    <div class="dashboard" id="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <a href="{{ route('productions.index') }}" class="logo">
                <div class="logo-icon">N</div>
                <span class="logo-text">NUME Studio</span>
            </a>

            <!-- 1. Operação -->
            <nav class="nav-section">
                <div class="nav-label">{{ __('Operação') }}</div>

                <a href="{{ route('productions.index') }}" class="nav-item {{ request()->routeIs('productions*', 'roteiros*', 'videos*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                        <span>{{ __('Produções') }}</span>
                    </span>
                </a>

                <a href="{{ route('calendar.index') }}" class="nav-item {{ request()->routeIs('calendar*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>{{ __('Calendário') }}</span>
                    </span>
                </a>

                <a href="{{ route('batch_planner.index') }}" class="nav-item {{ request()->routeIs('batch_planner*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                            <polyline points="2 17 12 22 22 17"/>
                            <polyline points="2 12 12 17 22 12"/>
                        </svg>
                        <span>{{ __('Batch Planner') }}</span>
                    </span>
                    <span class="nav-badge">AI</span>
                </a>

                <a href="{{ route('distributions.index') }}" class="nav-item {{ request()->routeIs('distributions*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"/>
                            <circle cx="6" cy="12" r="3"/>
                            <circle cx="18" cy="19" r="3"/>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                        </svg>
                        <span>{{ __('Distribuição Social') }}</span>
                    </span>
                </a>
            </nav>

            <!-- 2. Comercial & Clientes -->
            <nav class="nav-section">
                <div class="nav-label">{{ __('Comercial & Clientes') }}</div>

                <a href="{{ route('clientes.index') }}" class="nav-item {{ request()->routeIs('clientes*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>{{ __('Clientes') }}</span>
                    </span>
                    @if ($pendingPlanAdjustmentsCount > 0)
                        <span class="nav-badge">{{ $pendingPlanAdjustmentsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('crm.leads.index') }}" class="nav-item {{ request()->routeIs('crm*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        <span>{{ __('CRM & Funil') }}</span>
                    </span>
                </a>

                <a href="{{ route('quotes.index') }}" class="nav-item {{ request()->routeIs('quotes*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>{{ __('Orçamentos') }}</span>
                    </span>
                    @if ($pendingQuoteRequestsCount > 0)
                        <span class="nav-badge">{{ $pendingQuoteRequestsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('support.index') }}" class="nav-item {{ request()->routeIs('support*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>{{ __('Suporte') }}</span>
                    </span>
                    @if ($openSupportTicketsCount > 0)
                        <span class="nav-badge">{{ $openSupportTicketsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('analytics.index') }}" class="nav-item {{ request()->routeIs('analytics*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <span>{{ __('Analytics & BI') }}</span>
                    </span>
                </a>
            </nav>

            <!-- 3. Catálogo & Financeiro -->
            <nav class="nav-section">
                <div class="nav-label">{{ __('Catálogo & Financeiro') }}</div>

                <a href="{{ route('packs.index') }}" class="nav-item {{ request()->routeIs('packs*', 'admin.plans*', 'addons*', 'categories*', 'showcase-videos*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                        <span>{{ __('Catálogo & Vitrine') }}</span>
                    </span>
                </a>

                <a href="{{ route('admin.subscriptions.index') }}" class="nav-item {{ request()->routeIs('admin.subscriptions*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        <span>{{ __('Assinaturas') }}</span>
                    </span>
                </a>

                <a href="{{ route('admin.invoices.index') }}" class="nav-item {{ request()->routeIs('admin.invoices*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <span>{{ __('Facturas & AEAT') }}</span>
                    </span>
                </a>

                <a href="{{ route('stripe-connect.index') }}" class="nav-item {{ request()->routeIs('stripe-connect*', 'billing*') ? 'active' : '' }}">
                    <span class="nav-item-left">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <span>{{ __('Repasses & Plano SaaS') }}</span>
                    </span>
                </a>

                @if(auth()->check() && auth()->user()->isAdmin())
                    <a href="{{ route('admin.settings.gateways') }}" class="nav-item {{ request()->routeIs('admin.settings*', 'api_keys*', 'video_types*', 'ai_usage*') ? 'active' : '' }}">
                        <span class="nav-item-left">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                            </svg>
                            <span>{{ __('Configurações') }}</span>
                        </span>
                    </a>
                @endif
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="theme-toggle">
                    <div class="theme-toggle-label">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"/>
                            <line x1="12" y1="1" x2="12" y2="3"/>
                            <line x1="12" y1="21" x2="12" y2="23"/>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                            <line x1="1" y1="12" x2="3" y2="12"/>
                            <line x1="21" y1="12" x2="23" y2="12"/>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                        </svg>
                        <span>{{ __('Modo Claro') }}</span>
                    </div>
                    <button type="button" class="theme-switch" id="themeSwitch" aria-label="Alternar tema"></button>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        <span>{{ __('Encerrar Sessão') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="main-wrapper">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <div class="header-breadcrumb">
                        <span>NUME Studio</span>
                        <span>/</span>
                        <strong>@yield('breadcrumb', __('Produções'))</strong>
                    </div>
                </div>

                <div class="header-right">
                    @if(auth()->check() && auth()->user()->tenant)
                        <a href="{{ route('storefront.show', auth()->user()->tenant->slug) }}" target="_blank" class="header-pill">
                            <span class="header-pill-dot"></span>
                            <span>{{ __('Vitrine Pública') }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    @endif

                    <div class="lang-switcher">
                        <a href="{{ route('lang.switch', 'pt_BR') }}" class="lang-btn {{ session('locale', 'pt_BR') === 'pt_BR' ? 'active' : '' }}">BR</a>
                        <a href="{{ route('lang.switch', 'es') }}" class="lang-btn {{ session('locale') === 'es' ? 'active' : '' }}">ES</a>
                    </div>

                    <div class="user-menu">
                        <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'N', 0, 2)) }}</div>
                        <div class="user-info">
                            <span class="user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
                            <span class="user-role">{{ auth()->user()->tenant->name ?? 'NUME Creative' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stripe Connect Notice -->
            @auth
                @if (auth()->user()->isAdmin() && !auth()->user()->tenant->canReceivePayments())
                    <div class="card mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3.5 px-5">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="status-pill copper">{{ __('Pagamentos') }}</span>
                            <span class="text-zinc-300">{{ __('Conecte o Stripe Connect para habilitar checkout e recebimento direto dos pacotes.') }}</span>
                        </div>
                        <a href="{{ route('stripe-connect.index') }}" class="btn primary text-xs py-2 px-4">
                            {{ __('Conectar Stripe') }}
                        </a>
                    </div>
                @endif
            @endauth

            <!-- Flash Messages -->
            @if (session('warning'))
                <div class="card mb-6 py-3.5 px-5 flex items-center gap-3 text-sm">
                    <span class="status-pill copper">{{ __('Atenção') }}</span>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif
            @if (session('success'))
                <div class="card mb-6 py-3.5 px-5 flex items-center gap-3 text-sm">
                    <span class="status-pill positive">{{ __('Sucesso') }}</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="card mb-6 py-3.5 px-5 flex items-center gap-3 text-sm">
                    <span class="status-pill negative">{{ __('Erro') }}</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="flex-1">
                @yield('content')
            </div>

            <footer class="copyright">
                &copy; {{ date('Y') }} NUME Studio. {{ __('Plataforma de Gestão Audiovisual & Escala B2B.') }}
            </footer>
        </main>
    </div>

    <script>
        (function() {
            const themeSwitch = document.getElementById('themeSwitch');
            const html = document.documentElement;

            if (themeSwitch) {
                themeSwitch.addEventListener('click', function() {
                    const currentTheme = html.getAttribute('data-theme') || 'dark';
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                });
            }

            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleMobileMenu() {
                if (mobileMenuToggle && sidebar && sidebarOverlay) {
                    mobileMenuToggle.classList.toggle('active');
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                }
            }

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleMobileMenu);
            }
        })();
    </script>
</body>
</html>
