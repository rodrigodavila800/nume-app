<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->seo_meta_title ?: ($tenant->name . ' | ' . __('Vídeos Estratégicos que Escalam Marcas')) }}</title>
    <meta name="description" content="{{ $tenant->seo_meta_description ?: ($tenant->description ?: 'Produção audiovisual estratégica, roteiros persuasivos e vídeos de alta conversão.') }}">
    @if($tenant->seo_schema_keywords)
        <meta name="keywords" content="{{ $tenant->seo_schema_keywords }}">
    @endif
    <link rel="canonical" href="{{ $tenant->seo_canonical_url ?: url('/vitrine/' . $tenant->slug) }}">

    <!-- Open Graph / WhatsApp / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $tenant->seo_meta_title ?: $tenant->name }}">
    <meta property="og:description" content="{{ $tenant->seo_og_description ?: ($tenant->seo_meta_description ?: $tenant->description) }}">
    <meta property="og:url" content="{{ $tenant->seo_canonical_url ?: url('/vitrine/' . $tenant->slug) }}">
    <meta property="og:image" content="{{ $tenant->seo_og_image ?: asset('assests/img/logo.png') }}">
    <meta property="og:site_name" content="{{ $tenant->name }}">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tenant->seo_meta_title ?: $tenant->name }}">
    <meta name="twitter:description" content="{{ $tenant->seo_meta_description ?: $tenant->description }}">
    <meta name="twitter:image" content="{{ $tenant->seo_og_image ?: asset('assests/img/logo.png') }}">

    <!-- Schema.org JSON-LD (SEO Local & Organização) -->
    @php
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => $tenant->name,
            'description' => $tenant->seo_meta_description ?: $tenant->description,
            'url' => $tenant->seo_canonical_url ?: url('/vitrine/' . $tenant->slug),
            'logo' => asset('assests/img/logo.png'),
            'image' => $tenant->seo_og_image ?: asset('assests/img/logo.png'),
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'EUR',
                'offerCount' => (string) $packs->count(),
            ],
        ];
        if (!empty($tenant->socialLinks())) {
            $schemaData['sameAs'] = array_values($tenant->socialLinks());
        }
    @endphp
    <script type="application/ld+json">
    {!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        nume: {
                            50: '#fff7ed',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}">
</head>
<body class="bg-zinc-950 text-zinc-100 font-sans antialiased selection:bg-orange-500 selection:text-white">

    <!-- Top Navigation -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-zinc-950/80 border-b border-zinc-800/80">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center">
                <img src="{{ asset('assests/img/logo.png') }}" alt="{{ $tenant->name }}" class="h-8 w-auto object-contain hover:opacity-90 transition">
            </a>

            <div class="flex items-center gap-3">
                <!-- Seletor de Idiomas -->
                <div class="flex items-center gap-1 bg-zinc-900 border border-zinc-800 p-1 rounded-xl">
                    <a href="{{ route('locale.change', 'pt_BR') }}" 
                       class="px-2 py-1 rounded-lg text-xs font-bold transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'bg-orange-600 text-white shadow-sm' : 'text-zinc-400 hover:text-white' }}"
                       title="Português (Brasil)">
                        🇧🇷
                    </a>
                    <a href="{{ route('locale.change', 'es') }}" 
                       class="px-2 py-1 rounded-lg text-xs font-bold transition {{ session('locale') === 'es' ? 'bg-orange-600 text-white shadow-sm' : 'text-zinc-400 hover:text-white' }}"
                       title="Español (España)">
                        🇪🇸
                    </a>
                </div>

                @if(auth('cliente')->check())
                    <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-orange-600/20 text-orange-400 border border-orange-500/30 text-xs font-bold transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>{{ auth('cliente')->user()->name }}</span>
                        <span>→</span>
                    </a>
                @else
                    <a href="{{ route('portal.login') }}" class="hidden sm:inline-block px-3.5 py-1.5 rounded-xl border border-zinc-700 hover:border-zinc-500 text-xs font-bold text-zinc-300 hover:text-white transition">
                        {{ __('Portal do Cliente') }} →
                    </a>
                @endif

                <a href="#pacotes" class="rounded-xl bg-orange-600 px-4 py-1.5 text-xs font-bold text-white shadow-lg shadow-orange-900/20 hover:bg-orange-500 transition">
                    {{ __('Ver Pacotes') }}
                </a>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="mx-auto grid max-w-7xl gap-12 px-6 py-16 lg:grid-cols-2 lg:py-24 items-center">
            <div class="space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-ping"></span>
                    {{ __('Produção Audiovisual Estratégica') }}
                </div>
                <h1 class="text-4xl sm:text-6xl font-black leading-tight tracking-tight text-white">
                    {{ __('Sua marca merece ser impossível de ignorar.') }}
                </h1>
                <p class="text-base sm:text-lg leading-relaxed text-zinc-400">
                    {{ __('Roteirização com ganchos de padrão de interrupção, captação 4K, edição dinâmica e aprovações em tempo real no seu portal exclusivo.') }}
                </p>
                <div class="flex flex-wrap gap-4 pt-2">
                    <a href="#pacotes" class="rounded-xl bg-orange-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-900/30 hover:bg-orange-500 active:scale-95 transition">
                        {{ __('Contratar Pacote de Vídeos') }} →
                    </a>
                    <a href="#portfolio" class="rounded-xl border border-zinc-800 bg-zinc-900/80 px-6 py-3.5 text-sm font-bold text-zinc-300 hover:bg-zinc-800 hover:text-white transition">
                        {{ __('Assistir Exemplos') }}
                    </a>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-1 rounded-3xl bg-gradient-to-r from-orange-600 to-amber-600 opacity-20 blur-xl"></div>
                <div class="relative rounded-3xl border border-zinc-800 bg-zinc-900/90 p-8 shadow-2xl space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-orange-600/20 border border-orange-500/30 text-orange-400 flex items-center justify-center text-2xl font-bold">
                        🎬
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-widest text-orange-400">{{ __('Método NUME Creative') }}</p>
                        <h3 class="text-2xl font-extrabold text-white">{{ __('Do Roteiro ao Vídeo Finalizado') }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            {{ __('Cada vídeo é estruturado em 3 blocos: Gancho de 3s para reter atenção, Corpo com autoridade e Chamada para Ação orientada a conversão.') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-zinc-800/80 text-xs">
                        <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                            <span class="text-emerald-400 font-bold">✓ 100% Online</span>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Aprovações no portal') }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                            <span class="text-orange-400 font-bold">⚡ Alta Escala</span>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Entrega ágil de lotes') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Portfólio Section (Carrossel 3D Coverflow Estilo Apple / Netflix) -->
        @if($videos->isNotEmpty())
            <section id="portfolio" class="border-y border-zinc-800 bg-zinc-950 py-24 overflow-hidden relative">
                <!-- Ambient Background Glow -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-orange-600/10 blur-[150px] pointer-events-none rounded-full"></div>

                <div class="mx-auto max-w-7xl px-6 space-y-10 relative z-10">
                    
                    <!-- Header da Seção -->
                    <div class="text-center max-w-2xl mx-auto space-y-3">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20 shadow-lg shadow-orange-950/20">
                            <span>🎬</span>
                            <span>{{ __('Portfólio em Destaque') }}</span>
                        </div>
                        <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                            {{ __('Exemplos reais projetados para reter e converter') }}
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                            {{ __('Navegue em 3D pelos cases da NUME. Clique no card ou no botão central para assistir ao vídeo.') }}
                        </p>
                    </div>

                    <!-- 3D Swiper Coverflow Slider Container -->
                    <div class="relative py-4">
                        @php
                            $displayVideos = $videos;
                            if ($videos->count() > 0 && $videos->count() < 12) {
                                $multiplier = (int) ceil(12 / max(1, $videos->count()));
                                $collected = collect();
                                for ($i = 0; $i < $multiplier; $i++) {
                                    $collected = $collected->concat($videos);
                                }
                                $displayVideos = $collected;
                            }
                        @endphp
                        <div class="swiper swiper-3d-portfolio py-8 !overflow-visible">
                            <div class="swiper-wrapper">
                                @foreach ($displayVideos as $index => $video)
                                    @php
                                        $realIndex = $videos->count() > 0 ? ($loop->index % $videos->count()) : 0;
                                        $thumb = $video->effective_thumbnail_url;
                                        $isVertical = $video->is_vertical;
                                    @endphp
                                    <div class="swiper-slide !w-[270px] sm:!w-[310px] md:!w-[330px] !h-[480px] sm:!h-[520px] rounded-3xl overflow-hidden border border-zinc-700/60 bg-zinc-900 shadow-2xl relative select-none cursor-pointer group transition-all duration-300"
                                         data-real-index="{{ $realIndex }}"
                                         onclick="openStorefrontVideoModalByIndex({{ $realIndex }})">
                                        
                                        <!-- Thumbnail da Capa -->
                                        @if ($thumb)
                                            <img src="{{ $thumb }}" alt="{{ $video->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                        @else
                                            <div class="w-full h-full bg-zinc-950 flex flex-col items-center justify-center text-5xl text-orange-500">
                                                <span>🎬</span>
                                            </div>
                                        @endif

                                        <!-- Gradiente Escuro Cinematográfico (Overlay) -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent"></div>

                                        <!-- Badges Superiores -->
                                        <div class="absolute top-4 inset-x-4 flex items-center justify-between pointer-events-none">
                                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-orange-600 text-white shadow-lg backdrop-blur-md">
                                                {{ $video->category_name }}
                                            </span>
                                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-bold bg-black/60 text-zinc-300 border border-zinc-700/50 backdrop-blur-md">
                                                {{ $isVertical ? '📱 9:16' : '🎬 16:9' }}
                                            </span>
                                        </div>

                                        <!-- Botão Flutuante de Play no Hover do Card -->
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                            <div class="w-16 h-16 rounded-full bg-orange-600 text-white flex items-center justify-center text-2xl pl-1 shadow-2xl shadow-orange-950 scale-90 group-hover:scale-100 transition-transform">
                                                ▶
                                            </div>
                                        </div>

                                        <!-- Textos e Badges Sobrepostos na Base do Card -->
                                        <div class="absolute bottom-0 inset-x-0 p-6 space-y-2 text-left pointer-events-none">
                                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-orange-400 block drop-shadow">
                                                ⚡ {{ $isVertical ? 'Retenção: 84% (0-3s)' : 'Autoridade B2B' }}
                                            </span>
                                            <h3 class="text-lg sm:text-xl font-black text-white leading-snug drop-shadow-md line-clamp-2">
                                                {{ $video->title }}
                                            </h3>
                                            @if($video->description)
                                                <p class="text-xs text-zinc-300 line-clamp-2 leading-relaxed opacity-85 drop-shadow">
                                                    {{ $video->description }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Dock de Controles 3D (< Play >) -->
                        <div class="flex items-center justify-center gap-4 mt-6">
                            <!-- Prev Button -->
                            <button type="button" aria-label="Anterior"
                                    class="swiper-3d-prev w-12 h-12 rounded-full bg-zinc-900 border border-zinc-700 hover:border-orange-500 text-zinc-300 hover:text-white flex items-center justify-center shadow-xl hover:scale-105 active:scale-95 transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>

                            <!-- Center Play Button -->
                            <button type="button" onclick="playActiveSwiperVideo()" aria-label="Assistir Vídeo Ativo"
                                    class="w-16 h-16 rounded-full bg-orange-600 hover:bg-orange-500 text-white flex items-center justify-center shadow-2xl shadow-orange-900/60 hover:scale-110 active:scale-95 transition-all group"
                                    title="{{ __('Assistir vídeo em destaque') }}">
                                <svg class="w-7 h-7 fill-current pl-1 transition-transform group-hover:scale-110" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </button>

                            <!-- Next Button -->
                            <button type="button" aria-label="Próximo"
                                    class="swiper-3d-next w-12 h-12 rounded-full bg-zinc-900 border border-zinc-700 hover:border-orange-500 text-zinc-300 hover:text-white flex items-center justify-center shadow-xl hover:scale-105 active:scale-95 transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Swiper Dynamic Pagination Dots -->
                        <div class="swiper-3d-pagination flex items-center justify-center gap-2 mt-6"></div>

                        <!-- Botão de Ação / Conversão para Pacotes -->
                        <div class="text-center pt-6 max-w-md mx-auto">
                            <button type="button" onclick="scrollToPackagesAndHighlight('reels')"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-orange-600/15 hover:bg-orange-600 border border-orange-500/30 text-orange-400 hover:text-white text-xs font-extrabold uppercase tracking-wider shadow-lg transition-all active:scale-95">
                                <span>⚡ {{ __('Quero um Vídeo com este Padrão para Minha Marca') }}</span>
                                <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <!-- Pacotes Estratégicos Section -->
        <section id="pacotes" class="mx-auto max-w-7xl px-6 py-24 space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="text-xs font-bold uppercase tracking-widest text-orange-400">{{ __('Planos & Investimento') }}</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white">{{ __('Escolha o pacote ideal para sua marca') }}</h2>
                <p class="text-xs sm:text-sm text-zinc-400">
                    {{ __('Todos os pacotes incluem roteiros personalizados, portal do cliente para aprovação e entregas finalizadas prontas para publicação.') }}
                </p>
            </div>

            @if (session('error'))
                <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold max-w-2xl mx-auto text-center">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse ($packs as $pack)
                    @php
                        $currencySymbol = strtolower($pack->currency) === 'eur' ? '€' : 'R$';
                        $isPopular = str_contains(strtolower($pack->name), 'pro') || str_contains(strtolower($pack->name), 'ads');
                        $packAddons = $pack->effective_addons;
                    @endphp
                    <article class="pack-card rounded-3xl border {{ $isPopular ? 'border-orange-500 shadow-xl shadow-orange-950/40 relative bg-gradient-to-b from-zinc-900 to-zinc-950' : 'border-zinc-800 bg-zinc-900' }} p-6 flex flex-col justify-between space-y-6 group hover:border-zinc-700 transition"
                             data-pack-id="{{ $pack->id }}"
                             data-base-price="{{ (float) $pack->price }}"
                             data-vat-rate="{{ (float) ($pack->vat_rate ?? 21) }}"
                             data-currency="{{ $currencySymbol }}"
                             data-pack-slug="{{ $pack->slug }}"
                             data-login-url="{{ route('portal.login', ['pack' => $pack->slug]) }}">
                        @if($isPopular)
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-orange-600 text-white text-[10px] font-extrabold uppercase tracking-wider px-3.5 py-0.5 rounded-full shadow-lg shadow-orange-950">
                                {{ __('Mais Recomendado') }}
                            </div>
                        @endif

                        <form action="{{ route('storefront.checkout', ['tenant' => $tenant->slug, 'pack' => $pack->slug]) }}" method="POST" class="flex flex-col justify-between h-full space-y-6 pack-checkout-form">
                            @csrf
                            <div class="space-y-4">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-lg bg-orange-500/10 border border-orange-500/20 text-orange-400">
                                        {{ $pack->category_name }}
                                    </span>
                                    <span class="text-[10px] font-bold text-zinc-500">
                                        📦 {{ $pack->video_quantity }} {{ $pack->video_quantity === 1 ? __('vídeo') : __('vídeos') }}
                                    </span>
                                </div>

                                <div>
                                    <h3 class="text-lg font-black text-white group-hover:text-orange-400 transition">{{ __($pack->name) }}</h3>
                                    <p class="text-xs text-zinc-400 leading-relaxed mt-2 min-h-[3rem]">
                                        {{ __($pack->description) }}
                                    </p>
                                </div>

                                <!-- Features Checklist -->
                                @if(!empty($pack->features))
                                    <div class="pt-3 border-t border-zinc-800/80 space-y-1.5">
                                        @foreach($pack->features as $feature)
                                            <div class="flex items-center gap-2 text-xs text-zinc-300">
                                                <span class="text-emerald-400 font-bold">✓</span>
                                                <span>{{ $feature }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Addons (Añade a tu pack / Opcionais) -->
                                @if(!empty($packAddons))
                                    <div class="pt-3 border-t border-zinc-800/80 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-400 block">{{ __('Añade a tu pack / Opcionais') }}</span>
                                            <span class="text-[10px] font-mono text-orange-400 selected-addons-count hidden">0 selecionados</span>
                                        </div>
                                        <div class="space-y-1.5">
                                            @foreach($packAddons as $addonIndex => $addon)
                                                @php
                                                    $aName = is_array($addon) ? ($addon['name'] ?? '') : (string) $addon;
                                                    $aPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                                                    $aDesc = is_array($addon) ? ($addon['description'] ?? '') : '';
                                                @endphp
                                                <label class="flex items-center justify-between p-2.5 rounded-xl bg-zinc-950/60 border border-zinc-800/80 hover:border-orange-500/50 cursor-pointer transition text-[11px] text-zinc-300 select-none addon-checkbox-label">
                                                    <div class="flex items-center gap-2.5">
                                                        <input type="checkbox" name="selected_addons[]" value="{{ $aName }}"
                                                               data-addon-price="{{ (float) $aPrice }}"
                                                               data-addon-name="{{ $aName }}"
                                                               class="pack-addon-checkbox w-3.5 h-3.5 rounded border-zinc-700 bg-zinc-800 text-orange-600 focus:ring-orange-500 shrink-0">
                                                        <div class="flex flex-col">
                                                            <span class="font-medium text-zinc-200">
                                                                <span class="text-orange-500 font-bold mr-1">+</span>{{ $aName }}
                                                            </span>
                                                            @if(!empty($aDesc))
                                                                <span class="text-[10px] text-zinc-500 leading-tight mt-0.5 line-clamp-1" title="{{ $aDesc }}">{{ $aDesc }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($aPrice > 0)
                                                        <span class="text-[10px] font-mono font-bold text-orange-400 bg-orange-950/40 px-1.5 py-0.5 rounded border border-orange-800/30 shrink-0 ml-2">+{{ number_format($aPrice, 0) }}{{ $currencySymbol }}</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="pt-3 border-t border-zinc-800 space-y-2">
                                    <div class="flex items-baseline justify-between gap-1.5">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-xs text-zinc-400 font-semibold pack-price-prefix">{{ __('Desde') }}</span>
                                            <p class="text-3xl font-black text-white tracking-tight">
                                                <span class="pack-price-currency">{{ $currencySymbol }}</span>
                                                <span class="pack-price-amount" data-initial="{{ number_format($pack->price, 0, ',', '.') }}">{{ number_format($pack->price, 0, ',', '.') }}</span>
                                            </p>
                                            <span class="text-xs text-orange-400 font-bold">+ IVA (21%)</span>
                                        </div>
                                    </div>
                                    <div class="pack-addons-summary text-[10px] text-zinc-400 font-mono hidden pt-1">
                                        {{ __('Base:') }} <span class="summary-base">{{ number_format($pack->price, 0) }}{{ $currencySymbol }}</span> + {{ __('Add-ons:') }} <span class="summary-addons text-orange-400 font-bold">0{{ $currencySymbol }}</span>
                                    </div>
                                    <div class="text-[11px] text-emerald-400 font-semibold flex items-center justify-between pt-0.5 border-t border-zinc-800/50">
                                        <span class="text-zinc-400 text-[10px]">{{ __('Total estimado com IVA:') }}</span>
                                        <span class="pack-total-with-iva font-mono font-bold">{{ $currencySymbol }} {{ number_format($pack->price * 1.21, 2, ',', '.') }}</span>
                                    </div>
                                </div>

                                <!-- BADGE INFORMATIVO DE PAGAMENTOS ACEITOS NA PLATAFORMA -->
                                <div class="pt-3 border-t border-zinc-800/80 space-y-1.5">
                                    <div class="flex items-center justify-between text-[11px] px-2.5 py-2 rounded-xl bg-zinc-950/50 border border-zinc-800/60">
                                        <span class="text-[10px] text-zinc-500 font-semibold">{{ __('Meios aceitos:') }}</span>
                                        <div class="flex items-center gap-2 text-[10px] font-bold">
                                            <span class="text-blue-400 flex items-center gap-1" title="Bizum BBVA">⚡ Bizum</span>
                                            <span class="text-zinc-600">·</span>
                                            <span class="text-zinc-300 flex items-center gap-1" title="Cartão de Crédito/Débito BBVA">💳 Tarjeta</span>
                                            <span class="text-zinc-600">·</span>
                                            <span class="text-indigo-400" title="Apple Pay / Stripe">Stripe</span>
                                        </div>
                                    </div>
                                    <p class="text-[9px] text-zinc-500 text-center">
                                        🔒 {{ __('Pagamento seguro com Fatura Oficial (IVA 21% dedutível).') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Botão de Contratação -->
                            <div class="pt-3 border-t border-zinc-800">
                                @if(auth('cliente')->check())
                                    <button type="submit"
                                            class="pack-cta-btn w-full py-3.5 px-4 rounded-xl text-xs font-extrabold tracking-wide uppercase bg-orange-600 hover:bg-orange-500 active:scale-95 text-white shadow-lg shadow-orange-900/20 transition-all flex items-center justify-center gap-2">
                                        <span class="pack-btn-text">{{ __('Contratar Pacote') }}</span>
                                        <span>→</span>
                                    </button>
                                @else
                                    <a href="{{ route('portal.login', ['pack' => $pack->slug]) }}"
                                       class="pack-cta-link w-full inline-flex items-center justify-center gap-2 py-3.5 px-4 rounded-xl text-xs font-extrabold tracking-wide uppercase bg-orange-600 hover:bg-orange-500 active:scale-95 text-white shadow-lg shadow-orange-900/20 transition-all">
                                        <span class="pack-btn-text">{{ __('Contratar Pacote') }}</span>
                                        <span>→</span>
                                    </a>
                                @endif
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="col-span-4 rounded-2xl border border-dashed border-zinc-800 p-12 text-center text-xs text-zinc-500">
                        {{ __('Novos pacotes estarão disponíveis em breve.') }}
                    </p>
                @endforelse
            </div>
        </section>
    </main>

    <!-- Footer com Redes Sociais da Produtora -->
    <footer class="border-t border-zinc-800/80 px-6 py-10 text-center text-xs text-zinc-500 space-y-6">
        @if(!empty($tenant->socialLinks()))
            <div class="flex flex-wrap items-center justify-center gap-3 text-xs font-semibold">
                @if($tenant->social_instagram)
                    <a href="{{ str_starts_with($tenant->social_instagram, 'http') ? $tenant->social_instagram : 'https://instagram.com/' . ltrim($tenant->social_instagram, '@') }}"
                       target="_blank" rel="noopener noreferrer" 
                       class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:border-pink-500/50 hover:bg-pink-500/10 transition-all duration-200 flex items-center gap-2 group">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-pink-500 transition-colors shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                        <span>Instagram</span>
                    </a>
                @endif

                @if($tenant->social_youtube)
                    <a href="{{ $tenant->social_youtube }}" target="_blank" rel="noopener noreferrer" 
                       class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:border-red-500/50 hover:bg-red-500/10 transition-all duration-200 flex items-center gap-2 group">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-red-500 transition-colors shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                        <span>YouTube</span>
                    </a>
                @endif

                @if($tenant->social_linkedin)
                    <a href="{{ $tenant->social_linkedin }}" target="_blank" rel="noopener noreferrer" 
                       class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:border-blue-500/50 hover:bg-blue-500/10 transition-all duration-200 flex items-center gap-2 group">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-blue-500 transition-colors shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451c.979 0 1.778-.773 1.778-1.729V1.73C24 .774 23.205 0 22.225 0z"/>
                        </svg>
                        <span>LinkedIn</span>
                    </a>
                @endif

                @if($tenant->social_tiktok)
                    <a href="{{ str_starts_with($tenant->social_tiktok, 'http') ? $tenant->social_tiktok : 'https://tiktok.com/@' . ltrim($tenant->social_tiktok, '@') }}"
                       target="_blank" rel="noopener noreferrer" 
                       class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:border-cyan-400/50 hover:bg-cyan-400/10 transition-all duration-200 flex items-center gap-2 group">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-cyan-400 transition-colors shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                        <span>TikTok</span>
                    </a>
                @endif

                @if($tenant->social_whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $tenant->social_whatsapp) }}"
                       target="_blank" rel="noopener noreferrer" 
                       class="px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:border-emerald-500/50 hover:bg-emerald-500/10 transition-all duration-200 flex items-center gap-2 group">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-emerald-400 transition-colors shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>WhatsApp</span>
                    </a>
                @endif
            </div>
        @endif

        <div class="flex flex-col sm:flex-row items-center justify-center gap-2">
            <span>© {{ date('Y') }} {{ $tenant->name }}. {{ __('Todos os direitos reservados.') }}</span>
            <span class="hidden sm:inline">·</span>
            <a href="{{ route('legal.privacy') }}" class="text-zinc-400 hover:text-white underline">{{ __('Privacidade & Cookies (RGPD / LGPD)') }}</a>
            <span class="hidden sm:inline">·</span>
            <a href="{{ route('portal.login') }}" class="text-orange-400 hover:underline font-bold">{{ __('Acessar Portal do Cliente') }}</a>
        </div>
    </footer>

    <!-- Assistente Inteligente WhatsApp Flutuante (Sofi / Atendimento Imediato & Conversão) -->
    @if($tenant->social_whatsapp)
        @php
            $waNumber = preg_replace('/[^0-9]/', '', $tenant->social_whatsapp);
            $waMessage = rawurlencode("Olá! Gostaria de entender como a {$tenant->name} pode acelerar a produção de vídeos estratégicos para minha empresa.");
            $waUrl = "https://wa.me/{$waNumber}?text={$waMessage}";
        @endphp

        <div id="sofi-wa-widget" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3 select-none">
            <!-- Balão de Mensagem Inteligente Flutuante (Tooltip Dinâmico) -->
            <div id="sofi-wa-bubble" 
                 class="max-w-xs bg-zinc-900/95 backdrop-blur-md border border-emerald-500/30 text-white rounded-2xl p-4 shadow-2xl shadow-black/80 transform transition-all duration-300 translate-y-2 opacity-0 pointer-events-none sm:pointer-events-auto">
                <div class="flex items-start gap-3">
                    <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-bold text-xs shadow-md">
                            Sofi
                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-400 border-2 border-zinc-900 rounded-full animate-pulse"></span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-400">Atendimento Inteligente</span>
                            <button onclick="dismissWaBubble()" class="text-zinc-500 hover:text-zinc-300 text-xs px-1">✕</button>
                        </div>
                        <p class="text-xs text-zinc-300 leading-relaxed font-normal">
                            Precisa de orientação sobre qual pacote de vídeos se encaixa melhor no seu nicho? Fale conosco agora!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Botão Flutuante Principal -->
            <a href="{{ $waUrl }}" 
               target="_blank" 
               rel="noopener noreferrer"
               id="sofi-wa-btn"
               title="Falar com Especialista no WhatsApp"
               class="relative group flex items-center justify-center w-14 h-14 rounded-full bg-emerald-500 hover:bg-emerald-400 text-white shadow-xl shadow-emerald-950/50 hover:shadow-emerald-500/30 hover:scale-105 active:scale-95 transition-all duration-300">
                
                <!-- Pulso de Ondas Animado (Radar de Atenção) -->
                <span class="absolute inset-0 rounded-full bg-emerald-500 animate-ping opacity-25 pointer-events-none"></span>

                <!-- Ícone Oficial WhatsApp SVG -->
                <svg class="w-7 h-7 fill-current relative z-10 transition-transform duration-300 group-hover:scale-110" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>

                <!-- Badge Ativo / Online -->
                <span class="absolute top-0 right-0 w-3.5 h-3.5 bg-emerald-400 border-2 border-zinc-950 rounded-full"></span>
            </a>
        </div>

        <script>
        setTimeout(() => {
            const bubble = document.getElementById('sofi-wa-bubble');
            if (bubble && !sessionStorage.getItem('dismissed_wa_bubble')) {
                bubble.classList.remove('opacity-0', 'translate-y-2', 'pointer-events-none');
            }
        }, 2500);

        function dismissWaBubble() {
            const bubble = document.getElementById('sofi-wa-bubble');
            if (bubble) {
                bubble.classList.add('opacity-0', 'translate-y-2', 'pointer-events-none');
                sessionStorage.setItem('dismissed_wa_bubble', 'true');
            }
        }
        </script>
    @endif

    <!-- Banner de Consentimento de Cookies & Privacidade (RGPD Espanha / LGPD Brasil) -->
    <div id="cookie-consent-banner" 
         class="fixed bottom-0 inset-x-0 z-40 bg-zinc-950/95 backdrop-blur-md border-t border-zinc-800 p-4 sm:p-5 shadow-2xl transition-all duration-300 transform translate-y-full">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1 max-w-3xl">
                <div class="flex items-center gap-2">
                    <span class="text-sm">🍪</span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-white">Privacidade & Uso de Cookies</h3>
                </div>
                <p class="text-xs text-zinc-400 leading-relaxed">
                    Utilizamos cookies para assegurar a autenticação segura do portal e aprimorar a experiência de navegação conforme as exigências da <strong>RGPD (Espanha / UE)</strong> e da <strong>LGPD (Brasil)</strong>. Você pode ler nossa 
                    <a href="{{ route('legal.privacy') }}" class="text-orange-400 hover:underline font-bold">Política de Privacidade e Proteção de Dados</a>.
                </p>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto shrink-0">
                <button type="button" onclick="acceptCookies('essential')"
                        class="flex-1 md:flex-none px-4 py-2.5 rounded-xl border border-zinc-700 hover:border-zinc-500 text-zinc-300 hover:text-white text-xs font-bold transition">
                    Apenas Essenciais
                </button>
                <button type="button" onclick="acceptCookies('all')"
                        class="flex-1 md:flex-none px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white text-xs font-bold uppercase tracking-wider shadow-lg shadow-orange-950/30 transition">
                    Aceitar Todos
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Universal de Reprodução Stories Snap / Conversão de Vídeos -->
    <div id="storefront-video-modal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md hidden flex items-center justify-center p-3 sm:p-6 transition-all duration-300">
        <div class="relative w-full max-w-4xl bg-zinc-900/95 rounded-3xl border border-zinc-800 overflow-hidden shadow-2xl space-y-4 p-4 sm:p-6 flex flex-col justify-between max-h-[96vh]">
            
            <!-- Top: Título, Contador & Fechar -->
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800 shrink-0">
                <div class="flex items-center gap-2 overflow-hidden">
                    <span id="modal-video-badge" class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-orange-600 text-white shrink-0">9:16</span>
                    <h3 id="storefront-modal-title" class="text-sm sm:text-base font-extrabold text-white truncate max-w-xs sm:max-w-md"></h3>
                    <span id="storefront-modal-counter" class="text-xs text-zinc-500 font-mono hidden sm:inline-block shrink-0"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="navigateStorefrontModal(-1)" aria-label="Vídeo anterior" title="Anterior (←)"
                            class="p-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button type="button" onclick="navigateStorefrontModal(1)" aria-label="Próximo vídeo" title="Próximo (→)"
                            class="p-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button type="button" onclick="closeStorefrontVideoModal()" aria-label="Fechar modal"
                            class="p-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Frame do Player (Responsivo 9:16 ou 16:9) -->
            <div class="flex-1 flex items-center justify-center overflow-hidden py-1">
                <div id="storefront-modal-wrapper" class="w-full flex items-center justify-center bg-black rounded-2xl overflow-hidden shadow-2xl border border-zinc-800/80">
                    <iframe id="storefront-modal-iframe" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
            </div>

            <!-- Footer do Modal: Gatilhos Diretos de Conversão e Venda -->
            <div class="pt-3 border-t border-zinc-800 shrink-0 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p id="storefront-modal-desc" class="text-xs text-zinc-400 truncate max-w-sm hidden md:block"></p>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="button" onclick="ctaFromModalToPackages()"
                            class="flex-1 sm:flex-initial py-2.5 px-4 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-extrabold text-xs shadow-lg shadow-orange-950/40 transition active:scale-95 flex items-center justify-center gap-1.5">
                        <span>⚡ {{ __('Contratar Formato') }}</span>
                        <span>→</span>
                    </button>
                    <a id="modal-wa-link" href="#" target="_blank" rel="noopener noreferrer"
                       class="flex-1 sm:flex-initial py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-lg shadow-emerald-950/40 transition active:scale-95 flex items-center justify-center gap-1.5">
                        <span>💬 {{ __('WhatsApp') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados do Portfólio em JSON para o Player Stories -->
    @php
        $showcaseJson = $videos->map(function($v) use ($tenant) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $tenant->social_whatsapp ?? '');
            $waText = rawurlencode("Olá! Vi o vídeo '{$v->title}' na vitrine da {$tenant->name} e gostaria de um pacote neste padrão para minha empresa.");
            return [
                'id' => $v->id,
                'title' => $v->title,
                'description' => $v->description ?: '',
                'category' => $v->category_name,
                'embed_url' => $v->embed_url,
                'is_vertical' => $v->is_vertical,
                'wa_url' => $cleanPhone ? "https://wa.me/{$cleanPhone}?text={$waText}" : null,
            ];
        })->values();
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    window.storefrontVideos = {!! json_encode($showcaseJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};
    let currentModalVideoIndex = 0;
    let portfolioSwiper = null;

    document.addEventListener('DOMContentLoaded', function() {
        const swiperEl = document.querySelector('.swiper-3d-portfolio');
        if (swiperEl && typeof Swiper !== 'undefined') {
            const slideElements = swiperEl.querySelectorAll('.swiper-slide');
            const totalSlides = slideElements.length;
            const middleIndex = totalSlides > 1 ? Math.floor(totalSlides / 2) : 0;
            const canLoop = totalSlides >= 3;

            portfolioSwiper = new Swiper('.swiper-3d-portfolio', {
                effect: 'coverflow',
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: 'auto',
                initialSlide: middleIndex,
                loop: canLoop,
                loopAdditionalSlides: canLoop ? Math.min(totalSlides, 6) : 0,
                watchSlidesProgress: true,
                observer: true,
                observeParents: true,
                speed: 650,
                coverflowEffect: {
                    rotate: 20,
                    stretch: 0,
                    depth: 140,
                    modifier: 1,
                    slideShadows: true,
                },
                autoplay: totalSlides > 1 ? {
                    delay: 4000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                } : false,
                keyboard: {
                    enabled: true,
                },
                navigation: {
                    nextEl: '.swiper-3d-next',
                    prevEl: '.swiper-3d-prev',
                },
                pagination: {
                    el: '.swiper-3d-pagination',
                    clickable: true,
                },
            });
        }
    });

    function playActiveSwiperVideo() {
        if (!portfolioSwiper) {
            openStorefrontVideoModalByIndex(0);
            return;
        }
        const activeSlide = portfolioSwiper.slides[portfolioSwiper.activeIndex];
        const realIdx = activeSlide ? activeSlide.getAttribute('data-real-index') : null;
        if (realIdx !== null && realIdx !== undefined && !isNaN(parseInt(realIdx, 10))) {
            openStorefrontVideoModalByIndex(parseInt(realIdx, 10));
        } else {
            const fallbackIdx = (portfolioSwiper.realIndex !== undefined ? portfolioSwiper.realIndex : portfolioSwiper.activeIndex) % (window.storefrontVideos?.length || 1);
            openStorefrontVideoModalByIndex(fallbackIdx);
        }
    }

    function openStorefrontVideoModalByIndex(index) {
        if (!window.storefrontVideos || !window.storefrontVideos[index]) return;
        currentModalVideoIndex = index;
        
        // Pausar autoplay do carrossel 3D enquanto o cliente assiste
        if (portfolioSwiper && portfolioSwiper.autoplay && portfolioSwiper.autoplay.running) {
            portfolioSwiper.autoplay.stop();
        }

        renderModalVideo(window.storefrontVideos[index]);
        const modal = document.getElementById('storefront-video-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function renderModalVideo(video) {
        const iframe = document.getElementById('storefront-modal-iframe');
        const titleEl = document.getElementById('storefront-modal-title');
        const descEl = document.getElementById('storefront-modal-desc');
        const counterEl = document.getElementById('storefront-modal-counter');
        const badgeEl = document.getElementById('modal-video-badge');
        const wrapper = document.getElementById('storefront-modal-wrapper');
        const waLink = document.getElementById('modal-wa-link');

        if (titleEl) titleEl.textContent = video.title;
        if (descEl) descEl.textContent = video.description || 'Produção de alta conversão.';
        if (counterEl) counterEl.textContent = `(${currentModalVideoIndex + 1} de ${window.storefrontVideos.length})`;
        
        if (badgeEl) {
            badgeEl.textContent = video.is_vertical ? '9:16 Shorts / Reels' : '16:9 Cinema';
            badgeEl.className = video.is_vertical 
                ? 'px-2 py-0.5 rounded text-[10px] font-black uppercase bg-orange-600 text-white shrink-0'
                : 'px-2 py-0.5 rounded text-[10px] font-black uppercase bg-zinc-800 text-zinc-300 border border-zinc-700 shrink-0';
        }

        if (waLink) {
            if (video.wa_url) {
                waLink.href = video.wa_url;
                waLink.classList.remove('hidden');
            } else {
                waLink.classList.add('hidden');
            }
        }

        if (iframe) {
            const autoplayUrl = video.embed_url.includes('?') ? (video.embed_url + '&autoplay=1') : (video.embed_url + '?autoplay=1');
            iframe.src = autoplayUrl;
        }

        if (wrapper) {
            if (video.is_vertical) {
                wrapper.className = "w-full max-w-[340px] aspect-[9/16] mx-auto flex items-center justify-center bg-black rounded-2xl overflow-hidden shadow-2xl border border-zinc-800";
            } else {
                wrapper.className = "w-full aspect-video max-w-3xl mx-auto flex items-center justify-center bg-black rounded-2xl overflow-hidden shadow-2xl border border-zinc-800";
            }
        }
    }

    function navigateStorefrontModal(direction) {
        if (!window.storefrontVideos || window.storefrontVideos.length === 0) return;
        currentModalVideoIndex = (currentModalVideoIndex + direction + window.storefrontVideos.length) % window.storefrontVideos.length;
        renderModalVideo(window.storefrontVideos[currentModalVideoIndex]);
    }

    function closeStorefrontVideoModal() {
        const modal = document.getElementById('storefront-video-modal');
        const iframe = document.getElementById('storefront-modal-iframe');
        if (modal && iframe) {
            iframe.src = '';
            modal.classList.add('hidden');
        }

        // Retomar autoplay suave do carrossel 3D
        if (portfolioSwiper && portfolioSwiper.autoplay && !portfolioSwiper.autoplay.running) {
            portfolioSwiper.autoplay.start();
        }
    }

    function ctaFromModalToPackages() {
        closeStorefrontVideoModal();
        const activeVideo = window.storefrontVideos[currentModalVideoIndex];
        scrollToPackagesAndHighlight(activeVideo?.is_vertical ? 'reels' : 'geral');
    }

    function scrollToPackagesAndHighlight(type) {
        const pacotesSection = document.getElementById('pacotes');
        if (pacotesSection) {
            pacotesSection.scrollIntoView({ behavior: 'smooth' });
            
            // Adiciona efeito de radar/pulso visual nos cards de pacotes
            setTimeout(() => {
                const popularPack = pacotesSection.querySelector('article.border-orange-500') || pacotesSection.querySelector('article');
                if (popularPack) {
                    popularPack.classList.add('ring-4', 'ring-orange-500', 'ring-offset-4', 'ring-offset-zinc-950', 'transition-all');
                    setTimeout(() => {
                        popularPack.classList.remove('ring-4', 'ring-orange-500', 'ring-offset-4', 'ring-offset-zinc-950');
                    }, 2500);
                }
            }, 600);
        }
    }

    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('storefront-video-modal');
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') {
                closeStorefrontVideoModal();
            } else if (e.key === 'ArrowLeft') {
                navigateStorefrontModal(-1);
            } else if (e.key === 'ArrowRight') {
                navigateStorefrontModal(1);
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (!localStorage.getItem('nume_cookie_consent')) {
            setTimeout(() => {
                const banner = document.getElementById('cookie-consent-banner');
                if (banner) {
                    banner.classList.remove('translate-y-full');
                }
            }, 800);
        }
    });

    function acceptCookies(level) {
        localStorage.setItem('nume_cookie_consent', JSON.stringify({
            level: level,
            timestamp: new Date().toISOString()
        }));
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.classList.add('translate-y-full');
        }
    }

    // Recálculo Reativo em Tempo Real de Preço de Pacotes e Add-ons na Vitrine
    document.addEventListener('DOMContentLoaded', function() {
        const packCards = document.querySelectorAll('.pack-card');

        function formatCurrency(val) {
            return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
        }

        packCards.forEach(card => {
            const basePrice = parseFloat(card.dataset.basePrice) || 0;
            const currency = card.dataset.currency || '€';
            const priceAmountEl = card.querySelector('.pack-price-amount');
            const pricePrefixEl = card.querySelector('.pack-price-prefix');
            const summaryEl = card.querySelector('.pack-addons-summary');
            const summaryAddonsEl = card.querySelector('.summary-addons');
            const countEl = card.querySelector('.selected-addons-count');
            const btnTextEl = card.querySelector('.pack-btn-text');
            const ctaLinkEl = card.querySelector('.pack-cta-link');
            const baseLoginUrl = card.dataset.loginUrl || '';

            const checkboxes = card.querySelectorAll('.pack-addon-checkbox');

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    let addonsTotal = 0;
                    let selectedCount = 0;
                    let selectedNames = [];

                    checkboxes.forEach(box => {
                        const label = box.closest('.addon-checkbox-label');
                        if (box.checked) {
                            const price = parseFloat(box.dataset.addonPrice) || 0;
                            addonsTotal += price;
                            selectedCount++;
                            selectedNames.push(box.value);
                            if (label) {
                                label.classList.add('border-orange-500', 'bg-orange-950/20');
                                label.classList.remove('border-zinc-800/80', 'bg-zinc-950/60');
                            }
                        } else {
                            if (label) {
                                label.classList.remove('border-orange-500', 'bg-orange-950/20');
                                label.classList.add('border-zinc-800/80', 'bg-zinc-950/60');
                            }
                        }
                    });

                    const currentTotal = basePrice + addonsTotal;
                    const totalWithIva = currentTotal * 1.21;

                    // Atualiza o valor na tela com pequena animação de escala
                    if (priceAmountEl) {
                        priceAmountEl.classList.add('scale-110', 'text-orange-400', 'transition-transform');
                        setTimeout(() => {
                            priceAmountEl.innerText = formatCurrency(currentTotal);
                            priceAmountEl.classList.remove('scale-110', 'text-orange-400');
                        }, 120);
                    }

                    const totalWithIvaEl = card.querySelector('.pack-total-with-iva');
                    if (totalWithIvaEl) {
                        totalWithIvaEl.innerText = `${currency} ${new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalWithIva)}`;
                    }

                    // Se tiver addons marcados, muda 'Desde' para 'Total' e exibe resumo
                    if (selectedCount > 0) {
                        if (pricePrefixEl) pricePrefixEl.innerText = 'Total';
                        if (summaryEl) {
                            summaryEl.classList.remove('hidden');
                            if (summaryAddonsEl) summaryAddonsEl.innerText = `+${formatCurrency(addonsTotal)}${currency}`;
                        }
                        if (countEl) {
                            countEl.classList.remove('hidden');
                            countEl.innerText = `${selectedCount} opcional${selectedCount > 1 ? 'is' : ''}`;
                        }
                        if (btnTextEl) {
                            btnTextEl.innerText = `Contratar · ${formatCurrency(currentTotal)} ${currency} + IVA`;
                        }
                    } else {
                        if (pricePrefixEl) pricePrefixEl.innerText = 'Desde';
                        if (summaryEl) summaryEl.classList.add('hidden');
                        if (countEl) countEl.classList.add('hidden');
                        if (btnTextEl) btnTextEl.innerText = 'Contratar Pacote';
                    }

                    // Atualiza link de login com os addons pré-selecionados
                    if (ctaLinkEl && baseLoginUrl) {
                        if (selectedNames.length > 0) {
                            ctaLinkEl.href = `${baseLoginUrl}&addons=${encodeURIComponent(selectedNames.join(','))}`;
                        } else {
                            ctaLinkEl.href = baseLoginUrl;
                        }
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
