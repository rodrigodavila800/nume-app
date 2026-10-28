<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', 'NUME Creative | ' . __('Vídeos Verticais & Roteiros de Alta Retenção'))</title>
    
    @yield('meta')

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap Core CSS -->
    <link href="{{ asset('themes/liberty/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">

    <!-- FontAwesome, Animations, Carousels & Lightbox -->
    <link rel="stylesheet" href="{{ asset('themes/liberty/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/liberty/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/liberty/css/owl.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css">

    <!-- Liberty Market Base CSS -->
    <link rel="stylesheet" href="{{ asset('themes/liberty/css/templatemo-liberty-market.css') }}">

    <!-- NUME Creative Brand Theme Override -->
    <link rel="stylesheet" href="{{ asset('themes/liberty/css/nume-theme.css') }}">

    @stack('styles')
    @include('partials.favicons')
</head>
<body>

    <!-- ***** Preloader Start ***** -->
    <div id="js-preloader" class="js-preloader">
        <div class="preloader-inner">
            <span class="dot"></span>
            <div class="dots">
            </div>
        </div>
    </div>
    <!-- ***** Preloader End ***** -->

    <!-- ***** Header Area Start ***** -->
    <header class="header-area header-sticky">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <nav class="main-nav">
                        <!-- ***** Logo Start ***** -->
                        <a href="{{ route('marketplace.index') }}" class="logo">
                            <img src="{{ asset('assests/img/logo.png') }}" alt="NUME Creative">
                        </a>
                        <!-- ***** Logo End ***** -->

                        <!-- ***** Menu Start ***** -->
                        <ul class="nav">
                            <li><a href="#top" class="active">{{ __('Início') }}</a></li>
                            <li><a href="#portfolio">{{ __('Portfólio') }}</a></li>
                            <li><a href="#pacotes">{{ __('Pacotes') }}</a></li>
                            <li><a href="#metodologia">{{ __('Metodologia') }}</a></li>

                            <!-- Language Switcher -->
                            <li class="d-flex align-items-center px-2">
                                <a href="{{ route('lang.switch', 'pt_BR') }}" class="p-1 text-decoration-none fw-bold small {{ session('locale', 'pt_BR') === 'pt_BR' ? 'opacity-100 text-white' : 'opacity-50 text-light' }}" title="Português">BR</a>
                                <span class="text-secondary mx-1">|</span>
                                <a href="{{ route('lang.switch', 'es') }}" class="p-1 text-decoration-none fw-bold small {{ session('locale') === 'es' ? 'opacity-100 text-white' : 'opacity-50 text-light' }}" title="Español">ES</a>
                            </li>

                            <!-- Portal do Cliente / Login -->
                            @if(auth('cliente')->check())
                                <li><a href="{{ route('portal.dashboard') }}" class="text-warning">{{ __('Portal do Cliente') }}</a></li>
                            @elseif(auth('web')->check())
                                <li><a href="{{ route('productions.index') }}" class="text-warning">{{ __('Área da Produtora') }}</a></li>
                            @else
                                <li><a href="{{ route('portal.login') }}">{{ __('Acessar Portal do Cliente') }}</a></li>
                            @endif

                            <li>
                                <div class="main-button">
                                    <a href="#pacotes">{{ __('Contratar Vídeos') }}</a>
                                </div>
                            </li>
                        </ul>

                        <a class='menu-trigger'>
                            <span>Menu</span>
                        </a>
                        <!-- ***** Menu End ***** -->
                    </nav>
                </div>
            </div>
        </div>
    </header>
    <!-- ***** Header Area End ***** -->

    <!-- Main Content -->
    @yield('content')

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start mb-3 mb-lg-0">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME Creative" class="mb-2 h-7 w-auto object-contain">
                    <p class="mb-0">
                        © {{ date('Y') }} <strong>NUME Creative S.L.</strong> — {{ __('Produção Audiovisual Estratégica, Roteiros de Alta Retenção & Sofi AI.') }}
                    </p>
                    <p class="text-muted small mt-1">
                        Zaragoza (España) · NIF B50987654 · {{ __('Facturación Oficial conforme al RD 1619/2012') }}
                    </p>
                </div>
                <div class="col-lg-6 text-center text-lg-end">
                    <div class="d-flex justify-content-center justify-content-lg-end gap-3 text-secondary small mb-2">
                        <a href="{{ route('legal.privacy') }}" class="text-secondary text-decoration-none hover:text-white">{{ __('Privacidade') }}</a>
                        <span>·</span>
                        <a href="#pacotes" class="text-secondary text-decoration-none hover:text-white">{{ __('Pacotes & Planos') }}</a>
                        <span>·</span>
                        <a href="{{ route('portal.login') }}" class="text-secondary text-decoration-none hover:text-white">{{ __('Portal do Cliente') }}</a>
                    </div>
                    <p class="small text-muted mb-0">
                        {{ __('Pagamentos via Bizum BBVA, Tarjeta Redsys e Stripe') }}
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Core Scripts -->
    <script src="{{ asset('themes/liberty/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('themes/liberty/vendor/bootstrap/js/bootstrap.min.js') }}"></script>

    <!-- Plugins -->
    <script src="{{ asset('themes/liberty/js/isotope.min.js') }}"></script>
    <script src="{{ asset('themes/liberty/js/owl-carousel.js') }}"></script>
    <script src="{{ asset('themes/liberty/js/tabs.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js"></script>
    <script src="{{ asset('themes/liberty/js/custom.js') }}"></script>

    <!-- Setup Magnific Popup para Vídeos em Lightbox -->
    <script>
        $(document).ready(function() {
            $('.popup-video, .play-video-badge, .video-lightbox').magnificPopup({
                type: 'iframe',
                mainClass: 'mfp-fade',
                removalDelay: 160,
                preloader: false,
                fixedContentPos: false,
                iframe: {
                    patterns: {
                        youtube: {
                            index: 'youtube.com/',
                            id: function(url) {
                                // Suporte a YouTube Shorts e URLs tradicionais
                                var m = url.match(/(?:shorts\/|v=|\/embed\/|youtu\.be\/|\/v\/)([^#\&\?]*).*/);
                                return m ? m[1] : null;
                            },
                            src: 'https://www.youtube.com/embed/%id%?autoplay=1&rel=0'
                        },
                        vimeo: {
                            index: 'vimeo.com/',
                            id: '/',
                            src: 'https://player.vimeo.com/video/%id%?autoplay=1'
                        }
                    }
                }
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
