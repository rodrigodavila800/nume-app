@extends('layouts.prism')

@section('title', $tenant->seo_meta_title ?: ($tenant->name . ' | ' . __('Vídeos Estratégicos que Escalam Marcas')))

@section('meta')
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

    <!-- Schema.org JSON-LD -->
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
@endsection

@section('content')

    <!-- Inject Dynamic Portfolio Videos into Prism 3D Engine -->
    <script>
        window.numePortfolioData = [
            @forelse($videos as $index => $v)
            {
                id: {{ $index + 1 }},
                title: @json($v->translated_title ?? $v->title),
                description: @json(Str::limit(($v->translated_description ?? $v->description) ?: __('Vídeo estratégico de alta retenção produzido com direção de arte e Sofi Copilot.'), 90)),
                image: @json($v->effective_thumbnail_url ?: ($v->thumbnail_url ?: asset('themes/prism/images/neural-network.jpg'))),
                video_url: @json($v->video_url),
                embed_url: @json($v->embed_url),
                is_vertical: {{ $v->is_vertical ? 'true' : 'false' }},
                tech: [
                    '{{ $v->is_vertical ? "📱 9:16" : "🎬 16:9" }}',
                    '{{ $v->category?->name ?? "Showcase" }}',
                    '{{ __("Retenção: 94%") }}'
                ]
            }@if(!$loop->last),@endif
            @empty
            {
                id: 1,
                title: @json(__('NUME Originals — Reels de Alta Retenção')),
                description: @json(__('Vídeos projetados para interromper o scroll nos primeiros 3 segundos e conduzir a audiência à conversão.')),
                image: '{{ asset("themes/prism/images/neural-network.jpg") }}',
                video_url: 'https://youtube.com/shorts/Ovla_x3EAgc',
                embed_url: 'https://www.youtube-nocookie.com/embed/Ovla_x3EAgc?autoplay=1&playsinline=1&rel=0&enablejsapi=1',
                is_vertical: true,
                tech: ['📱 9:16', '{{ __("Reels Virais") }}', '4K HDR']
            },
            {
                id: 2,
                title: @json(__('Autoridade & Podcasts Corporativos')),
                description: @json(__('Cortes estratégicos para empresários e marcas que buscam posicionamento de liderança.')),
                image: '{{ asset("themes/prism/images/quantum-cloud.jpg") }}',
                video_url: 'https://youtube.com/shorts/Ovla_x3EAgc',
                embed_url: 'https://www.youtube-nocookie.com/embed/Ovla_x3EAgc?autoplay=1&playsinline=1&rel=0&enablejsapi=1',
                is_vertical: true,
                tech: ['📱 9:16', 'B2B', 'Sofi IA']
            }
            @endforelse
        ];

        window.numeSkillsData = [
            { name: 'Reels & TikTok', icon: '⚡', level: 98, category: 'frontend' },
            { name: 'Roteiros Sofi IA', icon: '🤖', level: 95, category: 'backend' },
            { name: 'Captação 4K HDR', icon: '🎥', level: 99, category: 'cloud' },
            { name: 'Edição Dinâmica', icon: '✂️', level: 96, category: 'frontend' },
            { name: 'Sound Design 3D', icon: '🎧', level: 92, category: 'backend' },
            { name: 'Color Grading', icon: '🎨', level: 94, category: 'cloud' },
            { name: 'Copywriting VSL', icon: '📝', level: 95, category: 'emerging' },
            { name: 'Retenção 0-3s', icon: '🎯', level: 97, category: 'emerging' }
        ];
    </script>

    <!-- Hero Section with 3D Interactive Carousel -->
    <section class="hero" id="home">
        <!-- Header Text (Perfeitamente posicionado acima do carrossel) -->
        <div class="hero-header-content">
            <div class="hero-badge">
                ⚡ {{ __('Vídeo com Direção Estratégica & Sofi IA') }}
            </div>
            <h1 class="hero-title">
                {{ __('Sua marca merece ser impossível de ignorar.') }}
            </h1>
            <p class="hero-subtitle">
                {{ __('A NUME transforma estratégia, roteiros de alta retenção (0-3s) e produção 4K em conteúdo irresistível no feed — gerando autoridade e conversão para o seu negócio.') }}
            </p>
        </div>

        <!-- Filtros de Formato do Portfólio (Pill Tabs Glassmorphic) -->
        <div class="portfolio-format-tabs" id="portfolio">
            <button type="button" class="portfolio-tab-btn active" data-filter="all" onclick="if(window.filterPortfolio) window.filterPortfolio('all', this)">
                <span>🌐</span>
                <span>{{ __('Todos os Formatos') }}</span>
            </button>

            <button type="button" class="portfolio-tab-btn" data-filter="vertical" onclick="if(window.filterPortfolio) window.filterPortfolio('vertical', this)">
                <span>📱</span>
                <span>{{ __('Shorts & Reels (9:16)') }}</span>
            </button>

            <button type="button" class="portfolio-tab-btn" data-filter="horizontal" onclick="if(window.filterPortfolio) window.filterPortfolio('horizontal', this)">
                <span>🎬</span>
                <span>{{ __('Vídeos Horizontais (16:9)') }}</span>
            </button>
        </div>

        <!-- 3D Orbital Ring Carousel (Template 2167 Orbital Engine) -->
        <div class="orbital-carousel-wrapper visuals-on" id="orbitalWrapper">
            <div class="orbital-stage" id="orbitalStage">
                <div class="orbital-parallax" id="orbitalParallax">
                    <div class="orbital-ring-tilt" id="orbitalRingTilt">
                        <div class="orbital-ring" id="carousel">
                            <!-- Orbital panels dynamically built by Orbital 3D Engine -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="orbital-controls">
                <div class="orbital-ctrl">
                    <span class="orbital-ctrl-label">{{ __('VISUALS') }}</span>
                    <button class="orbital-switch" id="visualsSwitch" type="button" role="switch" aria-checked="true" aria-label="{{ __('Alternar miniaturas e fichas técnicas') }}">
                        <span class="orbital-knob"></span>
                    </button>
                </div>
                <div class="orbital-ctrl">
                    <span class="orbital-ctrl-label">{{ __('ZOOM') }}</span>
                    <button class="orbital-switch" id="zoomSwitch" type="button" role="switch" aria-checked="false" aria-label="{{ __('Alternar zoom do anel orbital') }}">
                        <span class="orbital-knob"></span>
                    </button>
                </div>
                <div class="orbital-ctrl">
                    <span class="orbital-ctrl-label">{{ __('SPACING') }}</span>
                    <div class="orbital-spacing" role="group" aria-label="{{ __('Espaçamento do anel') }}">
                        <button class="orbital-spacing-step" type="button" data-space="0" aria-label="Compacto"><i></i></button>
                        <button class="orbital-spacing-step is-active" type="button" data-space="1" aria-label="Padrão"><i></i></button>
                        <button class="orbital-spacing-step" type="button" data-space="2" aria-label="Amplo"><i></i></button>
                    </div>
                </div>
            </div>

            <div class="orbital-drag-hint">
                <span>⟷ {{ __('Arraste para girar o anel 3D · Clique em qualquer vídeo para assistir') }}</span>
            </div>
        </div>

        <!-- Conversão Imediata (Abaixo do Carrossel) -->
        <div class="hero-cta-wrapper">
            <a href="#pacotes" class="contract-btn hero-cta-btn">
                <span>⚡ {{ __('Quero um Vídeo Neste Padrão') }}</span>
                <span class="hero-cta-arrow">→</span>
            </a>
        </div>
    </section>

    <!-- Philosophy Section (Metodologia NUME) -->
    <section class="philosophy-section" id="metodologia">
        <div class="philosophy-container">
            <div class="prism-line prism-line-nume"></div>
            
            <h2 class="philosophy-headline">
                {{ __('A Metodologia NUME') }}<br>
                <span class="philosophy-highlight">{{ __('Estratégia antes da Câmera') }}</span>
            </h2>
            
            <p class="philosophy-subheading">
                {{ __('Não gravamos vídeos aleatórios. Cada segundo é planejado para capturar a atenção nos primeiros 3 segundos, reter a audiência e direcionar o espectador para a compra.') }}
            </p>
            
            <div class="philosophy-pillars">
                <div class="pillar">
                    <div class="pillar-icon">💎</div>
                    <h3 class="pillar-title">01. {{ __('DNA & Posicionamento') }}</h3>
                    <p class="pillar-description">
                        {{ __('Calibramos o público-alvo, oferta e diferenciais no portal para alimentar a Sofi IA com o tom de voz perfeito da sua marca.') }}
                    </p>
                </div>
                
                <div class="pillar">
                    <div class="pillar-icon">🤖</div>
                    <h3 class="pillar-title">02. {{ __('Roteiro Sofi Copilot') }}</h3>
                    <p class="pillar-description">
                        {{ __('Geração automática de ganchos visuais e roteiros persuasivos de alta retenção (0-3s), aprovados com 1 clique no seu celular.') }}
                    </p>
                </div>
                
                <div class="pillar">
                    <div class="pillar-icon">🎥</div>
                    <h3 class="pillar-title">03. {{ __('Captação 4K & Escala') }}</h3>
                    <p class="pillar-description">
                        {{ __('Captação cinematográfica 4K HDR, cortes dinâmicos, legendas magnéticas e sound design imersivo prontos para publicação.') }}
                    </p>
                </div>
            </div>
            
            <div class="philosophy-particles" id="particles">
                <!-- Floating ambient particles -->
            </div>
        </div>
    </section>

    <!-- Stats Section with Live Performance Metrics -->
    <section class="stats-section" id="metricas">
        <div class="section-header">
            <h2 class="section-title">{{ __('Telemetria Audiovisual & Performance') }}</h2>
            <p class="section-subtitle">{{ __('Métricas comprovadas de engajamento, retenção e conversão') }}</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card stat-card-custom">
                <div class="stat-icon stat-icon-custom">🚀</div>
                <div class="stat-number stat-number-bright-orange" data-target="150">0</div>
                <div class="stat-label">{{ __('Vídeos Entregues') }}</div>
                <p class="stat-description">{{ __('Produções de alta retenção rodando no feed com 100% de pontualidade na entrega.') }}</p>
            </div>
            
            <div class="stat-card stat-card-custom">
                <div class="stat-icon stat-icon-custom">⚡</div>
                <div class="stat-number stat-number-orange" data-target="94">0</div>
                <div class="stat-label">{{ __('Retenção Média 0-3s (%)') }}</div>
                <p class="stat-description">{{ __('Ganchos visuais e quebras de padrão estruturados pela inteligência da Sofi IA.') }}</p>
            </div>
            
            <div class="stat-card stat-card-custom">
                <div class="stat-icon stat-icon-custom">🏆</div>
                <div class="stat-number stat-number-yellow" data-target="100">0</div>
                <div class="stat-label">{{ __('Aprovação Ágil no Portal (%)') }}</div>
                <p class="stat-description">{{ __('Feedback e aprovação centralizados na esteira exclusiva do cliente sem ruídos.') }}</p>
            </div>
            
            <div class="stat-card stat-card-custom">
                <div class="stat-icon stat-icon-custom">💎</div>
                <div class="stat-number stat-number-green" data-target="4">0</div>
                <div class="stat-label">{{ __('Qualidade 4K HDR (Cinema)') }}</div>
                <p class="stat-description">{{ __('Câmeras profissionais, iluminação calibrada e gradação de cores de alto padrão.') }}</p>
            </div>
        </div>
    </section>

    <!-- Enhanced Skills Section / Arsenal Técnico de Nichos -->
    <section class="skills-section" id="arsenal">
        <div class="skills-container">
            <div class="section-header">
                <h2 class="section-title">{{ __('Arsenal Técnico & Nichos Audiovisuais') }}</h2>
                <p class="section-subtitle">{{ __('Especialidades formatadas para dominar os feeds do Instagram, TikTok e YouTube') }}</p>
            </div>
            
            <div class="skill-categories">
                <div class="category-tab active" data-category="all">{{ __('Todos os Nichos') }}</div>
                <div class="category-tab" data-category="frontend">{{ __('Reels Virais') }}</div>
                <div class="category-tab" data-category="backend">{{ __('VSL & Vendas') }}</div>
                <div class="category-tab" data-category="cloud">{{ __('Autoridade & Podcasts') }}</div>
                <div class="category-tab" data-category="emerging">{{ __('B2B Corporativo') }}</div>
            </div>

            <div class="skills-hexagon-grid" id="skillsGrid">
                <!-- Hexagonal skills dynamically rendered by Prism engine -->
            </div>
        </div>
    </section>

    <!-- Official Retainer Plans Section -->
    <section class="contact-section pricing-section-wrapper" id="planos">
        <div class="section-header">
            <span class="pricing-badge-label">
                {{ __('Planos & Investimento') }}
            </span>
            <h2 class="section-title">{{ __('Planos Recorrentes de Conteúdo') }}</h2>
            <p class="section-subtitle">
                {{ __('Esteira contínua de vídeos mensais com renovação de cota a cada 30 dias, fidelidade estratégica e Sofi Copilot.') }}
            </p>
        </div>

        <!-- Grid 1: Planos Recorrentes (Retainers) -->
        <div id="storefront-grid-recurring" class="packs-grid-container">
            @forelse($recurringPlans as $pack)
                <div id="pack-{{ $pack->id }}" class="pack-card pack-card-custom transition-all duration-300" data-base-price="{{ (float) $pack->price }}" data-currency="€" data-login-url="{{ route('portal.login', ['intended_pack_slug' => $pack->slug]) }}">
                    <div>
                        <!-- Header do Card -->
                        <div class="pack-card-header">
                            <span class="pack-category-badge">
                                {{ $pack->category_name }}
                            </span>
                            <span class="pack-subscription-badge">
                                🔄 {{ __('Contrato · :months Meses', ['months' => $pack->commitment_months ?: 3]) }}
                            </span>
                            <span class="pack-video-qty">
                                📦 {{ $pack->video_quantity }} {{ __('vídeos/mês') }}
                            </span>
                        </div>

                        <h3 class="pack-name-title">
                            {{ $pack->translated_name ?? $pack->name }}
                        </h3>
                        <p class="pack-description-text">
                            {{ $pack->translated_description ?? $pack->description }}
                        </p>

                        @if($pack->showcaseVideo)
                            <div class="mb-3">
                                <button type="button" 
                                        onclick="openVideoModal('{{ $pack->showcaseVideo->video_url }}', '{{ addslashes($pack->showcaseVideo->title) }}', {{ $pack->showcaseVideo->is_vertical ? 'true' : 'false' }}, '{{ $pack->showcaseVideo->embed_url }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-orange-400 hover:text-white bg-orange-950/40 hover:bg-orange-600 border border-orange-500/30 transition-all shadow-sm cursor-pointer">
                                    <span>🎬</span>
                                    <span>{{ __('Ver Vídeo de Exemplo (4K)') }}</span>
                                </button>
                            </div>
                        @endif

                        <!-- Bloco de Preço & IVA 21% -->
                        <div class="pack-price-box">
                            <div class="pack-price-row">
                                <span class="pack-price-prefix">{{ __('Desde') }}</span>
                                <span class="pack-price-value">
                                    € <span class="pack-price-amount" data-initial="{{ number_format($pack->price, 0, ',', '.') }}">{{ number_format($pack->price, 0, ',', '.') }}</span>
                                    <span class="pack-price-period">/ {{ $pack->billing_interval === 'quarter' ? __('trimestre') : __('mês') }}</span>
                                </span>
                                <span class="pack-price-vat-tag">+ IVA (21%)</span>
                            </div>
                            <div class="pack-subscription-period-note">
                                <span>🔄</span>
                                <span>{{ __('Contrato de :months meses · :videos vídeos renovados a cada 30 dias', ['months' => $pack->commitment_months ?: 3, 'videos' => $pack->video_quantity]) }}</span>
                            </div>
                            <div class="pack-addons-summary">
                                {{ __('Base:') }} <span class="summary-base">€ {{ number_format($pack->price, 0) }}</span> + {{ __('Add-ons:') }} <span class="summary-addons">0€</span>
                            </div>
                            <div class="pack-total-iva-row">
                                <span class="pack-total-iva-label">{{ __('Total mensal com IVA:') }}</span>
                                <span class="pack-total-with-iva font-mono font-bold">€ {{ number_format($pack->price * 1.21, 2, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- Entregáveis Inclusos -->
                        <ul class="pack-deliverables-list">
                            <li><span class="pack-check-icon">✓</span><strong>{{ $pack->video_quantity }} {{ __('vídeos verticais/mês') }}</strong> {{ __('em 4K HDR') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Roteiros persuasivos com Sofi AI Copilot') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Renovação de cota a cada 30 dias') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Aprovação de roteiros com 1 clique no Portal') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Factura oficial AEAT c/ desglose de IVA') }}</li>
                        </ul>

                        <!-- Addons Opcionais -->
                        @php
                            $packAddons = $pack->effective_addons;
                        @endphp
                        @if(!empty($packAddons))
                            <div class="pack-addons-container">
                                <div class="pack-addons-header">
                                    <span class="pack-addons-title">
                                        {{ __('Añade a tu pack / Opcionais') }}
                                    </span>
                                    <span class="selected-addons-count pack-addons-count-badge">
                                        0 {{ __('selecionados') }}
                                    </span>
                                </div>

                                <div class="pack-addons-list">
                                    @foreach($packAddons as $addonIndex => $addon)
                                        @php
                                            $aName = is_array($addon) ? ($addon['name'] ?? '') : (string) $addon;
                                            $aPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                                            $aDesc = is_array($addon) ? ($addon['description'] ?? '') : '';
                                        @endphp
                                        <label class="addon-checkbox-label">
                                            <div class="addon-left-col">
                                                <input type="checkbox" name="selected_addons[]" value="{{ $aName }}"
                                                       data-addon-price="{{ (float) $aPrice }}"
                                                       data-addon-name="{{ $aName }}"
                                                       class="pack-addon-checkbox addon-checkbox-input">
                                                <div class="addon-meta-col">
                                                    <span class="addon-name-text">
                                                        <span class="addon-plus-symbol">+</span>{{ $aName }}
                                                    </span>
                                                    @if(!empty($aDesc))
                                                        <span class="addon-desc-text">{{ $aDesc }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($aPrice > 0)
                                                <span class="addon-price-tag">
                                                    +€{{ number_format($aPrice, 0) }}
                                                </span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <!-- Trust Badges -->
                        <div class="pack-trust-badges">
                            @php
                                $badgeList = [];
                                if (!empty($activePaymentMethods['bizum'])) $badgeList[] = '⚡ Bizum BBVA';
                                if (!empty($activePaymentMethods['redsys_card'])) $badgeList[] = '💳 Tarjeta Redsys';
                                if (!empty($activePaymentMethods['stripe'])) $badgeList[] = '🌐 Stripe';
                            @endphp
                            {{ !empty($badgeList) ? implode(' · ', $badgeList) : '🔒 ' . __('Pagamento Seguro') }}
                        </div>

                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-center text-xs font-medium text-amber-300">
                            🔒 {{ __('Novas assinaturas temporariamente indisponíveis') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="pack-empty-notification">
                    {{ __('Nenhum plano recorrente disponível no momento.') }}
                </div>
            @endforelse
        </div>
    </section>

    <!-- Official One-off Packs Section -->
    <section class="contact-section pricing-section-wrapper" id="pacotes">
        <div class="section-header">
            <span class="pricing-badge-label">
                📦 {{ __('Produção Pontual & Avulsa') }}
            </span>
            <h2 class="section-title">{{ __('Pacotes de Vídeos Estratégicos') }}</h2>
            <p class="section-subtitle">
                {{ __('Projetos fechados e produções sob demanda, com pagamento único e sem renovação recorrente.') }}
            </p>
        </div>

        <!-- Grid 2: Produções Avulsas (One-off) -->
        <div id="storefront-grid-oneoff" class="packs-grid-container">
            @forelse($oneOffPacks as $pack)
                <div id="pack-{{ $pack->id }}" class="pack-card pack-card-custom transition-all duration-300" data-base-price="{{ (float) $pack->price }}" data-currency="€" data-login-url="{{ route('portal.login', ['intended_pack_slug' => $pack->slug]) }}">
                    <div>
                        <!-- Header do Card -->
                        <div class="pack-card-header">
                            <span class="pack-category-badge">
                                {{ $pack->category_name }}
                            </span>
                            <span class="pack-subscription-badge">
                                📦 {{ __('Pagamento Único') }}
                            </span>
                            <span class="pack-video-qty">
                                📦 {{ $pack->video_quantity }} {{ __('vídeos') }}
                            </span>
                        </div>

                        <h3 class="pack-name-title">
                            {{ $pack->translated_name ?? $pack->name }}
                        </h3>
                        <p class="pack-description-text">
                            {{ $pack->translated_description ?? $pack->description }}
                        </p>

                        @if($pack->showcaseVideo)
                            <div class="mb-3">
                                <button type="button" 
                                        onclick="openVideoModal('{{ $pack->showcaseVideo->video_url }}', '{{ addslashes($pack->showcaseVideo->title) }}', {{ $pack->showcaseVideo->is_vertical ? 'true' : 'false' }}, '{{ $pack->showcaseVideo->embed_url }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-orange-400 hover:text-white bg-orange-950/40 hover:bg-orange-600 border border-orange-500/30 transition-all shadow-sm cursor-pointer">
                                    <span>🎬</span>
                                    <span>{{ __('Ver Vídeo de Exemplo (4K)') }}</span>
                                </button>
                            </div>
                        @endif

                        <!-- Bloco de Preço & IVA 21% -->
                        <div class="pack-price-box">
                            <div class="pack-price-row">
                                <span class="pack-price-prefix">{{ __('Preço') }}</span>
                                <span class="pack-price-value">
                                    € <span class="pack-price-amount" data-initial="{{ number_format($pack->price, 0, ',', '.') }}">{{ number_format($pack->price, 0, ',', '.') }}</span>
                                </span>
                                <span class="pack-price-vat-tag">+ IVA (21%)</span>
                            </div>
                            <div class="pack-subscription-period-note">
                                <span>🛡️</span>
                                <span>{{ __('Projeto fechado sem cobranças futuras ou mensalidades recorrentes') }}</span>
                            </div>
                            <div class="pack-addons-summary">
                                {{ __('Base:') }} <span class="summary-base">€ {{ number_format($pack->price, 0) }}</span> + {{ __('Add-ons:') }} <span class="summary-addons">0€</span>
                            </div>
                            <div class="pack-total-iva-row">
                                <span class="pack-total-iva-label">{{ __('Total estimado com IVA:') }}</span>
                                <span class="pack-total-with-iva font-mono font-bold">€ {{ number_format($pack->price * 1.21, 2, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- Entregáveis Inclusos -->
                        <ul class="pack-deliverables-list">
                            <li><span class="pack-check-icon">✓</span><strong>{{ $pack->video_quantity }} {{ __('vídeos verticais') }}</strong> {{ __('em 4K HDR') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Roteiros persuasivos com Sofi AI Copilot') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Sem renovação automática de cobrança') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Aprovação de roteiros com 1 clique no Portal') }}</li>
                            <li><span class="pack-check-icon">✓</span>{{ __('Factura oficial AEAT c/ desglose de IVA') }}</li>
                        </ul>

                        <!-- Addons Opcionais -->
                        @php
                            $packAddons = $pack->effective_addons;
                        @endphp
                        @if(!empty($packAddons))
                            <div class="pack-addons-container">
                                <div class="pack-addons-header">
                                    <span class="pack-addons-title">
                                        {{ __('Añade a tu pack / Opcionais') }}
                                    </span>
                                    <span class="selected-addons-count pack-addons-count-badge">
                                        0 {{ __('selecionados') }}
                                    </span>
                                </div>

                                <div class="pack-addons-list">
                                    @foreach($packAddons as $addonIndex => $addon)
                                        @php
                                            $aName = is_array($addon) ? ($addon['name'] ?? '') : (string) $addon;
                                            $aPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                                            $aDesc = is_array($addon) ? ($addon['description'] ?? '') : '';
                                        @endphp
                                        <label class="addon-checkbox-label">
                                            <div class="addon-left-col">
                                                <input type="checkbox" name="selected_addons[]" value="{{ $aName }}"
                                                       data-addon-price="{{ (float) $aPrice }}"
                                                       data-addon-name="{{ $aName }}"
                                                       class="pack-addon-checkbox addon-checkbox-input">
                                                <div class="addon-meta-col">
                                                    <span class="addon-name-text">
                                                        <span class="addon-plus-symbol">+</span>{{ $aName }}
                                                    </span>
                                                    @if(!empty($aDesc))
                                                        <span class="addon-desc-text">{{ $aDesc }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($aPrice > 0)
                                                <span class="addon-price-tag">
                                                    +€{{ number_format($aPrice, 0) }}
                                                </span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <!-- Trust Badges -->
                        <div class="pack-trust-badges">
                            @php
                                $badgeList = [];
                                if (!empty($activePaymentMethods['bizum'])) $badgeList[] = '⚡ Bizum BBVA';
                                if (!empty($activePaymentMethods['redsys_card'])) $badgeList[] = '💳 Tarjeta Redsys';
                                if (!empty($activePaymentMethods['stripe'])) $badgeList[] = '🌐 Stripe';
                            @endphp
                            {{ !empty($badgeList) ? implode(' · ', $badgeList) : '🔒 ' . __('Pagamento Seguro') }}
                        </div>

                        <button type="button" 
                                onclick="openStorefrontCheckout({{ $pack->id }}, '{{ addslashes($pack->name) }}', {{ (float) $pack->price }}, '{{ route('storefront.checkout', ['tenant' => $tenant->slug, 'pack' => $pack->slug]) }}', 'one_time', 1, 'month')"
                                class="contract-btn pack-cta-link pack-cta-btn-block cursor-pointer">
                            <span>📦</span>
                            <span class="pack-btn-text">{{ __('Contratar Produção Avulsa') }}</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>
            @empty
                <div class="pack-empty-notification">
                    {{ __('Nenhuma produção avulsa disponível no momento.') }}
                </div>
            @endforelse
        </div>
    </section>

    <!-- Modal Universal de Vídeo Adaptativo (Projetado para caber 100% no Viewport) -->
    <div id="storefront-video-modal" class="storefront-video-modal-overlay">
        <div id="storefront-modal-card" class="storefront-modal-card-box">
            <div class="storefront-modal-header-row">
                <div class="storefront-modal-title-group">
                    <span id="storefront-modal-format-badge" class="storefront-modal-format-pill">📱 9:16</span>
                    <h3 id="storefront-modal-title" class="storefront-modal-title-heading"></h3>
                </div>
                <button type="button" onclick="closeVideoModal()" class="storefront-modal-close-btn" aria-label="Fechar">✕</button>
            </div>
            
            <div id="storefront-modal-iframe-box" class="storefront-modal-iframe-wrapper">
                <iframe id="storefront-modal-iframe" class="storefront-modal-iframe-el" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>

            <div class="storefront-modal-cta-wrapper">
                <a href="#pacotes" onclick="closeVideoModal()" class="contract-btn storefront-modal-cta-btn">
                    <span>⚡ {{ __('Quero um Vídeo Neste Padrão') }}</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Modal de Checkout Transparente da Vitrine (Prism Flux Design System) -->
    <div id="storefront-checkout-modal" class="storefront-checkout-modal-overlay hidden">
        <div class="storefront-checkout-card">
            <div class="storefront-checkout-header">
                <div class="storefront-modal-title-group">
                    <span class="storefront-modal-format-pill">⚡ CHECKOUT</span>
                    <span id="checkout-modal-subscription-badge" class="pack-subscription-badge hidden"></span>
                    <div>
                        <h3 class="storefront-modal-title-heading">{{ __('Finalizar Contratação de Vídeos') }}</h3>
                        <p id="checkout-modal-pack-name" class="pack-modal-pack-subtitle"></p>
                    </div>
                </div>
                <button type="button" onclick="closeStorefrontCheckout()" class="storefront-modal-close-btn" aria-label="Fechar">✕</button>
            </div>

            <div class="storefront-checkout-body">
                <!-- Resumo dos Valores -->
                <div class="storefront-checkout-summary-box">
                    <div class="storefront-checkout-summary-row">
                        <span>{{ __('Base Imponible:') }}</span>
                        <span id="checkout-summary-base" class="summary-val-mono">€ 0,00</span>
                    </div>
                    <div id="checkout-summary-addons-row" class="storefront-checkout-summary-row hidden">
                        <span id="checkout-summary-addons-label">{{ __('Add-ons selecionados:') }}</span>
                        <span id="checkout-summary-addons-val" class="summary-val-orange">+€ 0,00</span>
                    </div>
                    <div class="storefront-checkout-summary-row">
                        <span>{{ __('IVA Geral (21% Espanha):') }}</span>
                        <span id="checkout-summary-vat" class="summary-val-mono">€ 0,00</span>
                    </div>
                    <div id="checkout-summary-irpf-row" class="storefront-checkout-summary-row hidden text-orange-400">
                        <span id="checkout-summary-irpf-label">{{ __('Retenção IRPF') }} (-{{ number_format($fiscalSettings['irpf_rate'] ?? 15, 0) }}%):</span>
                        <span id="checkout-summary-irpf-val" class="summary-val-orange">-€ 0,00</span>
                    </div>
                    <div class="storefront-checkout-summary-total-row">
                        <span class="summary-total-label">{{ __('Total Líquido:') }}</span>
                        <span id="checkout-summary-total" class="summary-total-amount">€ 0,00</span>
                    </div>
                </div>

                <!-- Formulário de Compra -->
                <form id="storefront-checkout-form" method="POST" action="" class="storefront-checkout-form">
                    @csrf
                    <div id="checkout-addons-hidden-inputs"></div>

                    @if($currentCliente)
                        <div class="storefront-checkout-user-badge">
                            <span>👤</span>
                            <div>
                                <span class="badge-sub-label">{{ __('Autenticado como:') }}</span>
                                <span class="badge-user-name">{{ $currentCliente->name }} ({{ $currentCliente->email }})</span>
                            </div>
                        </div>
                    @else
                        <!-- Abas de Autenticação Rápida -->
                        <div class="storefront-checkout-auth-tabs">
                            <button type="button" class="storefront-checkout-auth-tab active" id="tab-btn-register" onclick="switchCheckoutAuthTab('register')">
                                <span>✨</span> {{ __('Novo Cliente') }}
                            </button>
                            <button type="button" class="storefront-checkout-auth-tab" id="tab-btn-login" onclick="switchCheckoutAuthTab('login')">
                                <span>👤</span> {{ __('Já sou Cliente') }}
                            </button>
                        </div>

                        <!-- Cadastro Rápido Inline -->
                        <div id="checkout-field-name-group" class="storefront-checkout-field">
                            <label class="storefront-checkout-label">
                                {{ __('Seu Nome Completo') }} <span class="text-accent">*</span>
                            </label>
                            <input type="text" name="name" id="checkout-input-name" required placeholder="{{ __('Ex: Alejandro Martínez') }}"
                                   class="storefront-checkout-input">
                        </div>

                        <div class="storefront-checkout-field-row">
                            <div class="storefront-checkout-field">
                                <label class="storefront-checkout-label">
                                    {{ __('E-mail Corporativo') }} <span class="text-accent">*</span>
                                </label>
                                <input type="email" name="email" required placeholder="alejandro@empresa.com"
                                       class="storefront-checkout-input">
                            </div>
                            <div class="storefront-checkout-field">
                                <label class="storefront-checkout-label">
                                    {{ __('Senha de Acesso ao Portal') }} <span class="text-accent">*</span>
                                </label>
                                <input type="password" name="password" required minlength="6" placeholder="******"
                                       class="storefront-checkout-input">
                            </div>
                        </div>
                        <p id="checkout-auth-notice" class="pack-modal-pack-subtitle">
                            {{ __('Sua conta de acesso ao portal será criada automaticamente com estes dados.') }}
                        </p>
                    @endif

                    <!-- Dados Fiscais de Faturamento Opcionais -->
                    <details class="storefront-checkout-fiscal-details">
                        <summary class="storefront-checkout-fiscal-summary">
                            <span>📋 {{ __('¿Deseas Factura Oficial con NIF / CIF?') }}</span>
                            <span class="fiscal-chevron">▾</span>
                        </summary>
                        <div class="fiscal-content-box">
                            <input type="hidden" name="billing_type" value="particular" id="storefront-billing-type">
                            <input type="hidden" name="billing_country" value="ES">
                            <div class="storefront-checkout-field-row">
                                <div class="storefront-checkout-field">
                                    <label class="fiscal-input-label">{{ __('NIF / CIF / DNI') }}</label>
                                    <input type="text" name="billing_tax_id" id="storefront-tax-id" placeholder="B12345678"
                                           class="storefront-checkout-input">
                                </div>
                                <div class="storefront-checkout-field">
                                    <label class="fiscal-input-label">{{ __('Razón Social / Empresa') }}</label>
                                    <input type="text" name="billing_legal_name" placeholder="Mi Empresa S.L."
                                           class="storefront-checkout-input">
                                </div>
                            </div>
                        </div>
                    </details>

                    @php
                        $storefrontAvailableCount = collect($activePaymentMethods ?? ['bizum' => true, 'redsys_card' => true, 'stripe' => true])->filter()->count();
                        $storefrontFirstActiveMethod = collect($activePaymentMethods ?? ['bizum' => true, 'redsys_card' => true, 'stripe' => true])->filter()->keys()->first();
                    @endphp

                    @if ($storefrontAvailableCount > 0)
                        <!-- Seleção de Forma de Pagamento com Destaques Visuais -->
                        <div class="storefront-checkout-field">
                            <label class="storefront-checkout-label">
                                {{ __('Forma de Pagamento') }}
                            </label>
                            <div class="storefront-checkout-payment-grid">
                                @if (!empty($activePaymentMethods['bizum']))
                                    <label class="storefront-checkout-payment-option method-bizum {{ $storefrontFirstActiveMethod === 'bizum' ? 'selected' : '' }}" data-method="bizum">
                                        <input type="radio" name="payment_method" value="bizum" {{ $storefrontFirstActiveMethod === 'bizum' ? 'checked' : '' }} class="sr-only">
                                        <span class="payment-badge badge-bizum">⚡ {{ __('Mais Rápido') }}</span>
                                        <span class="payment-icon">⚡</span>
                                        <span class="payment-method-name">Bizum</span>
                                        <span class="payment-gateway-sub">BBVA</span>
                                    </label>
                                @endif
                                @if (!empty($activePaymentMethods['redsys_card']))
                                    <label class="storefront-checkout-payment-option method-card {{ $storefrontFirstActiveMethod === 'redsys_card' ? 'selected' : '' }}" data-method="redsys_card">
                                        <input type="radio" name="payment_method" value="redsys_card" {{ $storefrontFirstActiveMethod === 'redsys_card' ? 'checked' : '' }} class="sr-only">
                                        <span class="payment-badge badge-card">💳 {{ __('3D Secure') }}</span>
                                        <span class="payment-icon">💳</span>
                                        <span class="payment-method-name">{{ __('Tarjeta') }}</span>
                                        <span class="payment-gateway-sub">BBVA Redsys</span>
                                    </label>
                                @endif
                                @if (!empty($activePaymentMethods['stripe']))
                                    <label class="storefront-checkout-payment-option method-stripe {{ $storefrontFirstActiveMethod === 'stripe' ? 'selected' : '' }}" data-method="stripe">
                                        <input type="radio" name="payment_method" value="stripe" {{ $storefrontFirstActiveMethod === 'stripe' ? 'checked' : '' }} class="sr-only">
                                        <span class="payment-badge badge-stripe">🌐 {{ __('Global') }}</span>
                                        <span class="payment-icon">🌐</span>
                                        <span class="payment-method-name">Stripe</span>
                                        <span class="payment-gateway-sub">Apple / Cartões</span>
                                    </label>
                                @endif
                            </div>

                            <!-- Caixa de Instrução Contextual Dinâmica -->
                            <div id="storefront-checkout-payment-hint" class="storefront-checkout-payment-hint hint-bizum">
                                <span id="hint-icon">⚡</span>
                                <span id="hint-text">
                                    <strong>Bizum (BBVA):</strong> {{ __('Pagamento instantâneo via celular espanhol. Na próxima tela oficial do banco você informará seu número e confirmará a notificação no app com total segurança.') }}
                                </span>
                            </div>
                        </div>

                        <button type="submit" id="storefront-checkout-submit-btn" class="storefront-checkout-submit-btn btn-bizum">
                            <span id="submit-btn-icon">⚡</span>
                            <span id="submit-btn-text">{{ __('Pagar com Bizum (BBVA)') }}</span>
                            <span id="submit-btn-arrow">→</span>
                        </button>
                    @else
                        <div class="storefront-checkout-field">
                            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs">
                                ⚠️ {{ __('Nenhum método de pagamento disponível no momento. Entre em contato com a produtora.') }}
                            </div>
                        </div>

                        <button type="button" disabled class="storefront-checkout-submit-btn opacity-50 cursor-not-allowed">
                            <span>🔒</span>
                            <span>{{ __('Pagamentos Indisponíveis') }}</span>
                        </button>
                    @endif
                    <p class="checkout-footer-legal-notice">
                        {{ __('Ambiente seguro com criptografia bancária BBVA Redsys PCI-DSS e provisionamento imediato no Portal.') }}
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts de Interação do Vídeo Modal e Checkout Transparente -->
    <script>
        // Navegação direta para seções de Planos Recorrentes vs Pacotes Avulsos
        function switchStorefrontPackTab(type) {
            const targetId = (type === 'recurring') ? 'planos' : 'pacotes';
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Funções de Checkout Transparente da Vitrine
        function openStorefrontCheckout(packId, packName, basePrice, checkoutUrl, billingType, commitmentMonths, billingInterval) {
            const modal = document.getElementById('storefront-checkout-modal');
            const form = document.getElementById('storefront-checkout-form');
            const nameEl = document.getElementById('checkout-modal-pack-name');
            const subBadge = document.getElementById('checkout-modal-subscription-badge');
            const hiddenInputsContainer = document.getElementById('checkout-addons-hidden-inputs');

            if (!modal || !form) return;

            form.action = checkoutUrl;
            if (nameEl) nameEl.textContent = packName;
            if (hiddenInputsContainer) hiddenInputsContainer.innerHTML = '';

            const isSubscription = (billingType === 'subscription');
            const months = commitmentMonths || 3;
            if (subBadge) {
                if (isSubscription) {
                    subBadge.classList.remove('hidden');
                    subBadge.textContent = `🔄 Contrato de ${months} Meses · Recorrente`;
                } else {
                    subBadge.classList.add('hidden');
                }
            }

            // Coleta os add-ons marcados no card daquele pacote específico
            const packCard = document.getElementById('pack-' + packId);
            let addonsTotal = 0;
            let selectedCount = 0;

            if (packCard && hiddenInputsContainer) {
                const checkboxes = packCard.querySelectorAll('.pack-addon-checkbox:checked');
                checkboxes.forEach(cb => {
                    const price = parseFloat(cb.dataset.addonPrice || 0);
                    addonsTotal += price;
                    selectedCount++;

                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_addons[]';
                    hiddenInput.value = cb.value;
                    hiddenInputsContainer.appendChild(hiddenInput);
                });
            }

            window.currentModalSubtotal = basePrice + addonsTotal;
            window.currentModalIsSubscription = isSubscription;
            window.currentModalBillingInterval = billingInterval;

            const baseEl = document.getElementById('checkout-summary-base');
            if (baseEl) {
                const periodSuffix = isSubscription ? (billingInterval === 'quarter' ? ' / trimestre' : ' / mês') : '';
                baseEl.textContent = '€ ' + basePrice.toFixed(2) + periodSuffix;
            }
            
            const addonsRow = document.getElementById('checkout-summary-addons-row');
            if (addonsRow) {
                if (addonsTotal > 0) {
                    addonsRow.classList.remove('hidden');
                    const labelEl = document.getElementById('checkout-summary-addons-label');
                    const valEl = document.getElementById('checkout-summary-addons-val');
                    if (labelEl) labelEl.textContent = 'Add-ons (' + selectedCount + '):';
                    if (valEl) valEl.textContent = '+€ ' + addonsTotal.toFixed(2);
                } else {
                    addonsRow.classList.add('hidden');
                }
            }

            recalculateStorefrontFiscal();

            modal.classList.remove('hidden');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        window.NUME_FISCAL = {
            regime: '{{ $fiscalSettings["regime"] ?? "autonomo" }}',
            irpf_rate: {{ (float) ($fiscalSettings["irpf_rate"] ?? 15.00) }},
            iva_rate: {{ (float) ($fiscalSettings["iva_rate"] ?? 21.00) }}
        };

        function recalculateStorefrontFiscal() {
            const fiscalConfig = window.NUME_FISCAL || { regime: 'autonomo', irpf_rate: 15.0, iva_rate: 21.0 };
            const subtotal = window.currentModalSubtotal || 0;
            const isSubscription = window.currentModalIsSubscription || false;
            const billingInterval = window.currentModalBillingInterval || 'month';

            const taxIdInput = document.getElementById('storefront-tax-id');
            const billingTypeInput = document.getElementById('storefront-billing-type');
            const fiscalDetails = document.querySelector('.storefront-checkout-fiscal-details');
            const irpfRow = document.getElementById('checkout-summary-irpf-row');
            const irpfVal = document.getElementById('checkout-summary-irpf-val');
            const vatEl = document.getElementById('checkout-summary-vat');
            const totalEl = document.getElementById('checkout-summary-total');

            const hasTaxId = taxIdInput && taxIdInput.value.trim().length > 0;
            const isCompany = fiscalDetails && fiscalDetails.hasAttribute('open') && hasTaxId;
            if (billingTypeInput) {
                billingTypeInput.value = isCompany ? 'empresa_autonomo' : 'particular';
            }

            const vatAmount = subtotal * (fiscalConfig.iva_rate / 100);
            if (vatEl) vatEl.textContent = '€ ' + vatAmount.toFixed(2);

            let irpfAmount = 0;
            if (fiscalConfig.regime === 'autonomo' && isCompany && fiscalConfig.irpf_rate > 0) {
                irpfAmount = subtotal * (fiscalConfig.irpf_rate / 100);
                if (irpfRow) irpfRow.classList.remove('hidden');
                if (irpfVal) irpfVal.textContent = '-€ ' + irpfAmount.toFixed(2);
            } else {
                if (irpfRow) irpfRow.classList.add('hidden');
            }

            const finalTotal = subtotal + vatAmount - irpfAmount;
            const periodTag = isSubscription ? (billingInterval === 'quarter' ? ' / trimestre' : ' / mês') : '';
            const formattedTotal = '€ ' + finalTotal.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + periodTag;
            if (totalEl) totalEl.textContent = formattedTotal;
            window.currentCheckoutTotal = formattedTotal;

            const checkedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
            if (checkedMethod) {
                updatePaymentMethodUI(checkedMethod);
            }
        }

        document.getElementById('storefront-tax-id')?.addEventListener('input', recalculateStorefrontFiscal);
        document.querySelector('.storefront-checkout-fiscal-details')?.addEventListener('toggle', recalculateStorefrontFiscal);

        function closeStorefrontCheckout() {
            const modal = document.getElementById('storefront-checkout-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('active');
            }
            document.body.style.overflow = '';
        }

        document.getElementById('storefront-checkout-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeStorefrontCheckout();
            }
        });

        // Alternância de Abas de Autenticação (Novo Cliente vs Já sou Cliente)
        function switchCheckoutAuthTab(tab) {
            const tabRegister = document.getElementById('tab-btn-register');
            const tabLogin = document.getElementById('tab-btn-login');
            const nameGroup = document.getElementById('checkout-field-name-group');
            const nameInput = document.getElementById('checkout-input-name');
            const noticeEl = document.getElementById('checkout-auth-notice');

            if (tab === 'login') {
                tabLogin?.classList.add('active');
                tabRegister?.classList.remove('active');
                if (nameGroup) nameGroup.classList.add('hidden');
                if (nameInput) nameInput.required = false;
                if (noticeEl) noticeEl.textContent = '{{ __("Acesse com seu e-mail e senha cadastrados para vincular o pacote à sua conta.") }}';
            } else {
                tabRegister?.classList.add('active');
                tabLogin?.classList.remove('active');
                if (nameGroup) nameGroup.classList.remove('hidden');
                if (nameInput) nameInput.required = true;
                if (noticeEl) noticeEl.textContent = '{{ __("Sua conta de acesso ao portal será criada automaticamente com estes dados.") }}';
            }
        }

        // Atualização da UI e Feedback Contextual do Método de Pagamento
        function updatePaymentMethodUI(method) {
            const hintBox = document.getElementById('storefront-checkout-payment-hint');
            const hintIcon = document.getElementById('hint-icon');
            const hintText = document.getElementById('hint-text');
            const submitBtn = document.getElementById('storefront-checkout-submit-btn');
            const totalStr = window.currentCheckoutTotal || '€ 0,00';

            if (!hintBox || !submitBtn) return;

            // Remove classes anteriores
            hintBox.classList.remove('hint-bizum', 'hint-card', 'hint-stripe');
            submitBtn.classList.remove('btn-bizum', 'btn-card', 'btn-stripe');

            if (method === 'bizum') {
                hintBox.classList.add('hint-bizum');
                if (hintIcon) hintIcon.textContent = '⚡';
                if (hintText) hintText.innerHTML = '<strong>Bizum (BBVA):</strong> {{ __("Pagamento instantâneo via celular espanhol. Na próxima tela oficial você informará seu número e confirmará a notificação no app com total segurança.") }}';
                submitBtn.classList.add('btn-bizum');
                submitBtn.innerHTML = `<span>⚡</span> <span>{{ __("Pagar") }} ${totalStr} {{ __("com Bizum (BBVA)") }}</span> <span>→</span>`;
            } else if (method === 'redsys_card') {
                hintBox.classList.add('hint-card');
                if (hintIcon) hintIcon.textContent = '💳';
                if (hintText) hintText.innerHTML = '<strong>Tarjeta (BBVA Redsys):</strong> {{ __("Ambiente bancário com 3D Secure 2.2. Aceita cartões de crédito e débito com autenticação biométrica ou SMS oficial.") }}';
                submitBtn.classList.add('btn-card');
                submitBtn.innerHTML = `<span>💳</span> <span>{{ __("Pagar") }} ${totalStr} {{ __("com Tarjeta Segura") }}</span> <span>→</span>`;
            } else if (method === 'stripe') {
                hintBox.classList.add('hint-stripe');
                if (hintIcon) hintIcon.textContent = '🌐';
                if (hintText) hintText.innerHTML = '<strong>Stripe Internacional:</strong> {{ __("Checkout universal com conversão automática. Pague com 1 clique via Apple Pay, Google Pay ou cartões globais.") }}';
                submitBtn.classList.add('btn-stripe');
                submitBtn.innerHTML = `<span>🌐</span> <span>{{ __("Pagar") }} ${totalStr} {{ __("via Stripe Global") }}</span> <span>→</span>`;
            }
        }

        // Alternância visual e interativa das opções de pagamento no modal
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.storefront-checkout-payment-option');
            paymentOptions.forEach(opt => {
                opt.addEventListener('click', function() {
                    paymentOptions.forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    const radio = opt.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        updatePaymentMethodUI(radio.value);
                    }
                });
            });

            // Prevenção de duplo clique e estado de carregamento ao submeter o checkout
            const checkoutForm = document.getElementById('storefront-checkout-form');
            checkoutForm?.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('storefront-checkout-submit-btn');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="checkout-spinner"></span> <span>{{ __("Conectando com o ambiente bancário seguro...") }}</span>';
                }
            });
        });
        function resolveEmbedUrl(url, explicitEmbedUrl) {
            let targetUrl = (explicitEmbedUrl && explicitEmbedUrl.trim()) ? explicitEmbedUrl : url;

            if (!targetUrl || targetUrl === '#' || targetUrl.trim() === '') {
                return 'https://www.youtube-nocookie.com/embed/Ovla_x3EAgc?autoplay=1&playsinline=1&rel=0&enablejsapi=1';
            }

            // Normaliza autoplay se já vier com autoplay=0
            if (targetUrl.includes('autoplay=0')) {
                targetUrl = targetUrl.replace('autoplay=0', 'autoplay=1');
            }

            // Se for do youtube-nocookie
            if (targetUrl.includes('youtube-nocookie.com/embed/')) {
                let cleanUrl = targetUrl;
                if (!cleanUrl.includes('autoplay=1')) cleanUrl += (cleanUrl.includes('?') ? '&autoplay=1' : '?autoplay=1');
                if (!cleanUrl.includes('playsinline=1')) cleanUrl += '&playsinline=1';
                if (!cleanUrl.includes('enablejsapi=1')) cleanUrl += '&enablejsapi=1';
                return cleanUrl;
            }

            // Regex universal para capturar YouTube ID (shorts, watch?v=, youtu.be, embed)
            const ytRegex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i;
            const ytMatch = targetUrl.match(ytRegex);
            if (ytMatch && ytMatch[1]) {
                return `https://www.youtube-nocookie.com/embed/${ytMatch[1]}?autoplay=1&playsinline=1&rel=0&enablejsapi=1&modestbranding=1`;
            }

            // Vimeo
            const vimeoRegex = /vimeo\.com\/(?:video\/)?([0-9]+)/i;
            const vimeoMatch = targetUrl.match(vimeoRegex);
            if (vimeoMatch && vimeoMatch[1]) {
                return `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1&playsinline=1&badge=0`;
            }

            // Converte qualquer youtube.com/embed/ para youtube-nocookie.com
            if (targetUrl.includes('youtube.com/embed/')) {
                let converted = targetUrl.replace('youtube.com/embed/', 'youtube-nocookie.com/embed/');
                if (!converted.includes('autoplay=1')) converted += (converted.includes('?') ? '&autoplay=1' : '?autoplay=1');
                if (!converted.includes('playsinline=1')) converted += '&playsinline=1';
                return converted;
            }

            return targetUrl;
        }

        function openVideoModal(url, title, isVertical, embedUrl) {
            const modal = document.getElementById('storefront-video-modal');
            const modalCard = document.getElementById('storefront-modal-card');
            const iframeBox = document.getElementById('storefront-modal-iframe-box');
            const iframe = document.getElementById('storefront-modal-iframe');
            const titleEl = document.getElementById('storefront-modal-title');
            const badgeEl = document.getElementById('storefront-modal-format-badge');
            
            if (!modal || !iframe) return;

            const resolvedUrl = resolveEmbedUrl(url, embedUrl);
            iframe.src = resolvedUrl;
            if (titleEl) titleEl.textContent = title || 'NUME Showcase';

            const vertical = (isVertical !== false);
            if (vertical) {
                if (modalCard) {
                    modalCard.style.maxWidth = '360px';
                    modalCard.style.padding = '12px 14px';
                }
                if (iframeBox) {
                    iframeBox.style.paddingBottom = '0';
                    iframeBox.style.height = 'min(56vh, 440px)';
                    iframeBox.style.width = '100%';
                    iframeBox.style.maxWidth = '250px';
                    iframeBox.style.aspectRatio = '9/16';
                    iframeBox.style.maxHeight = '56vh';
                }
                if (badgeEl) {
                    badgeEl.textContent = '📱 9:16 SHORTS';
                    badgeEl.style.background = '#ea580c';
                }
            } else {
                if (modalCard) {
                    modalCard.style.maxWidth = '780px';
                    modalCard.style.padding = '16px 20px';
                }
                if (iframeBox) {
                    iframeBox.style.paddingBottom = '0';
                    iframeBox.style.height = 'auto';
                    iframeBox.style.width = '100%';
                    iframeBox.style.maxWidth = '100%';
                    iframeBox.style.aspectRatio = '16/9';
                    iframeBox.style.maxHeight = '56vh';
                }
                if (badgeEl) {
                    badgeEl.textContent = '🎬 16:9 CINEMA';
                    badgeEl.style.background = '#27272a';
                }
            }

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeVideoModal() {
            const modal = document.getElementById('storefront-video-modal');
            const iframe = document.getElementById('storefront-modal-iframe');
            if (iframe) iframe.src = '';
            if (modal) modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Exporta explicitamente no window para chamadas inline e scripts externos
        window.openVideoModal = openVideoModal;
        window.closeVideoModal = closeVideoModal;
        window.resolveEmbedUrl = resolveEmbedUrl;

        // Fechar ao tocar no backdrop escuro do modal
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('storefront-video-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closeVideoModal();
                });
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeVideoModal();
        });

        // Cálculo Dinâmico de Add-ons e IVA nos Pacotes
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
                                    label.style.borderColor = 'rgba(234, 88, 12, 0.6)';
                                    label.style.background = 'rgba(234, 88, 12, 0.12)';
                                }
                            } else {
                                if (label) {
                                    label.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                                    label.style.background = 'rgba(8, 8, 10, 0.7)';
                                }
                            }
                        });

                        const currentTotal = basePrice + addonsTotal;
                        const totalWithIva = currentTotal * 1.21;

                        if (priceAmountEl) {
                            priceAmountEl.innerText = formatCurrency(currentTotal);
                        }

                        const totalWithIvaEl = card.querySelector('.pack-total-with-iva');
                        if (totalWithIvaEl) {
                            totalWithIvaEl.innerText = `${currency} ${new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalWithIva)}`;
                        }

                        if (selectedCount > 0) {
                            if (pricePrefixEl) pricePrefixEl.innerText = (window.__ ? window.__('total', 'Total') : 'Total');
                            if (summaryEl) {
                                summaryEl.style.display = 'block';
                                if (summaryAddonsEl) summaryAddonsEl.innerText = `+${formatCurrency(addonsTotal)}${currency}`;
                            }
                            if (countEl) {
                                countEl.style.display = 'inline-block';
                                const optLabel = selectedCount > 1 
                                    ? (window.__ ? window.__('optional_plural', 'opcionais') : 'opcionais')
                                    : (window.__ ? window.__('optional_singular', 'opcional') : 'opcional');
                                countEl.innerText = `${selectedCount} ${optLabel}`;
                            }
                            if (btnTextEl) {
                                const hireLabel = window.__ ? window.__('hire_cta', 'Contratar') : 'Contratar';
                                btnTextEl.innerText = `${hireLabel} · ${formatCurrency(currentTotal)} ${currency} + IVA`;
                            }
                        } else {
                            if (pricePrefixEl) pricePrefixEl.innerText = (window.__ ? window.__('from_price', 'Desde') : 'Desde');
                            if (summaryEl) summaryEl.style.display = 'none';
                            if (countEl) countEl.style.display = 'none';
                            if (btnTextEl) btnTextEl.innerText = (window.__ ? window.__('hire_pack', 'Contratar Este Pacote') : 'Contratar Este Pacote');
                        }

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

    <!-- Sofi Sales Copilot Floating Widget -->
    @include('components.sofi-sales-widget', ['tenant' => $tenant])

@endsection
