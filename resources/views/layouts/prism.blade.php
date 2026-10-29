@php
    $tenantWa = isset($tenant) ? ($tenant->social_whatsapp ?? null) : null;
    $waPhone = preg_replace('/\D/', '', $tenantWa ?: config('services.whatsapp.contact_number', '34664274151'));
    $waText = urlencode(__('Olá Sofi! Gostaria de saber mais sobre a produção de vídeos da NUME.'));
    $waUrl = "https://wa.me/{$waPhone}?text={$waText}";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="GWfdvsDaKOduOzhrNH8iA_7r3R1ubFF7Ug2H5NQsQzg" />
    <title>@yield('title', 'NUME Creative | ' . __('Vídeos Estratégicos que Escalam Marcas'))</title>
    
    @yield('meta')

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Prism Flux CSS -->
    <link rel="stylesheet" href="{{ asset('themes/prism/templatemo-prism-flux.css') }}?v={{ file_exists(public_path('themes/prism/templatemo-prism-flux.css')) ? filemtime(public_path('themes/prism/templatemo-prism-flux.css')) : time() }}">

    <!-- Global I18n Bridge for JavaScript -->
    <script>
        window.NUME_I18N = {
            'watch_shorts': {!! json_encode(__('Assistir Shorts'), JSON_UNESCAPED_UNICODE) !!},
            'watch_video': {!! json_encode(__('Assistir Vídeo'), JSON_UNESCAPED_UNICODE) !!},
            'retention_badge': {!! json_encode(__('94% Retenção'), JSON_UNESCAPED_UNICODE) !!},
            'no_videos_found': {!! json_encode(__('Nenhum vídeo encontrado para este formato no momento.'), JSON_UNESCAPED_UNICODE) !!},
            'total': {!! json_encode(__('Total'), JSON_UNESCAPED_UNICODE) !!},
            'from_price': {!! json_encode(__('Desde'), JSON_UNESCAPED_UNICODE) !!},
            'optional_singular': {!! json_encode(__('opcional'), JSON_UNESCAPED_UNICODE) !!},
            'optional_plural': {!! json_encode(__('opcionais'), JSON_UNESCAPED_UNICODE) !!},
            'hire_cta': {!! json_encode(__('Contratar'), JSON_UNESCAPED_UNICODE) !!},
            'hire_pack': {!! json_encode(__('Contratar Este Pacote'), JSON_UNESCAPED_UNICODE) !!},
            'selected': {!! json_encode(__('selecionados'), JSON_UNESCAPED_UNICODE) !!}
        };
        window.__ = function(key, fallback) {
            return (window.NUME_I18N && window.NUME_I18N[key] !== undefined) ? window.NUME_I18N[key] : (fallback !== undefined ? fallback : key);
        };
    </script>

    @stack('styles')
    @include('partials.favicons')
</head>
<body>
    <!-- Loading Screen -->
    <div class="loader loader-clickable" id="loader" onclick="this.classList.add('hidden'); this.style.display='none';" title="Clique para entrar">
        <div class="loader-content">
            <div class="loader-prism">
                <div class="prism-face prism-face-orange-1"></div>
                <div class="prism-face prism-face-orange-2"></div>
                <div class="prism-face prism-face-orange-3"></div>
            </div>
            <div class="loader-brand-tagline">
                NUME CREATIVE &bull; SOFI IA
            </div>
        </div>
    </div>

    <!-- Navigation Header -->
    <header class="header" id="header">
        <nav class="nav-container">
            <a href="{{ route('marketplace.index') }}" class="logo">
                <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="header-logo-img">
            </a>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="#home" class="nav-link active">{{ __('Início') }}</a></li>
                <li><a href="#portfolio" class="nav-link">{{ __('Portfólio') }}</a></li>
                <li><a href="#metodologia" class="nav-link">{{ __('Metodologia') }}</a></li>
                <li><a href="#metricas" class="nav-link">{{ __('Métricas') }}</a></li>
                <li><a href="#planos" class="nav-link">{{ __('Planos') }}</a></li>
                <li><a href="#pacotes" class="nav-link">{{ __('Pacotes') }}</a></li>

                <!-- Language Switcher -->
                <li class="nav-lang-item">
                    <a href="{{ route('lang.switch', 'pt_BR') }}" class="lang-link {{ session('locale', 'pt_BR') === 'pt_BR' ? 'opacity-100' : 'opacity-40' }}" title="Português">BR</a>
                    <span class="nav-lang-divider">|</span>
                    <a href="{{ route('lang.switch', 'es') }}" class="lang-link {{ session('locale') === 'es' ? 'opacity-100' : 'opacity-40' }}" title="Español">ES</a>
                </li>

                <!-- WhatsApp Direct Sofi Button -->
                <li>
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="whatsapp-nav-btn" title="{{ __('Falar com a Sofi no WhatsApp') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>WhatsApp Sofi</span>
                    </a>
                </li>

                <!-- Portal do Cliente / Login -->
                @if(auth('cliente')->check())
                    <li><a href="{{ route('portal.dashboard') }}" class="portal-btn">{{ __('Portal do Cliente') }}</a></li>
                @elseif(auth('web')->check())
                    <li><a href="{{ route('productions.index') }}" class="portal-btn">{{ __('Área da Produtora') }}</a></li>
                @else
                    <li><a href="{{ route('portal.login') }}" class="portal-btn">{{ __('Portal do Cliente') }}</a></li>
                @endif

                <li><a href="#planos" class="contract-btn">{{ __('Ver Planos') }}</a></li>
            </ul>
            
            <div class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    @yield('content')

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="footer-logo-img">
                </div>
                <p class="footer-description">
                    {{ __('Transformando estratégia, ganchos visuais e produção 4K em vídeos de alta retenção no feed. Inteligência audiovisual com Sofi Copilot.') }}
                </p>
                <div class="footer-social footer-trust-note">
                    {{ __('Pagamentos via Bizum BBVA, Tarjeta Redsys e Stripe') }}
                </div>
            </div>
            
            <div class="footer-section">
                <h4>{{ __('Especialidades') }}</h4>
                <div class="footer-links">
                    <a href="#portfolio">{{ __('Reels & TikTok Virais') }}</a>
                    <a href="#portfolio">{{ __('Vídeos de Vendas (VSL)') }}</a>
                    <a href="#portfolio">{{ __('Autoridade & Entrevistas') }}</a>
                    <a href="#portfolio">{{ __('Institucional B2B') }}</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4>{{ __('Plataforma') }}</h4>
                <div class="footer-links">
                    <a href="#metodologia">{{ __('Metodologia NUME') }}</a>
                    <a href="#planos">{{ __('Planos de Continuidade') }}</a>
                    <a href="#pacotes">{{ __('Pacotes de Vídeos') }}</a>
                    <a href="{{ route('portal.login') }}">{{ __('Portal do Cliente') }}</a>
                    <a href="{{ route('legal.privacy') }}">{{ __('Privacidade') }}</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4>{{ __('Sede & Contato') }}</h4>
                <div class="footer-links">
                    <span class="footer-sede-info">Zaragoza (España)</span>
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="footer-wa-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span>WhatsApp Sofi: +34 664 274 151</span>
                    </a>
                    <span class="footer-sede-info">NIF B50987654</span>
                    <span class="footer-sede-info">{{ __('Facturación Oficial conforme al RD 1619/2012') }}</span>
                    <span class="footer-sede-iva">IVA General 21%</span>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="copyright">
                © {{ date('Y') }} <strong>NUME Creative S.L.</strong> — {{ __('Todos os direitos reservados.') }}
            </div>
            <div class="footer-credits">
                {{ __('Potencializado por Sofi IA Audiovisual') }}
            </div>
        </div>
    </footer>

    <!-- Prism Flux Engine Scripts -->
    <script src="{{ asset('themes/prism/templatemo-prism-scripts.js') }}?v={{ file_exists(public_path('themes/prism/templatemo-prism-scripts.js')) ? filemtime(public_path('themes/prism/templatemo-prism-scripts.js')) : time() }}"></script>
    
    <script>
        // Failsafe: fecha o loader caso o evento load já tenha disparado
        function autoDismissLoader() {
            var loader = document.getElementById('loader');
            if (loader && !loader.classList.contains('hidden')) {
                loader.classList.add('hidden');
                setTimeout(function() { loader.style.display = 'none'; }, 400);
            }
        }
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(autoDismissLoader, 400);
        } else {
            document.addEventListener('DOMContentLoaded', function() { setTimeout(autoDismissLoader, 400); });
            window.addEventListener('load', function() { setTimeout(autoDismissLoader, 200); });
        }
        setTimeout(autoDismissLoader, 1000);
    </script>
    
    @stack('scripts')
</body>
</html>
