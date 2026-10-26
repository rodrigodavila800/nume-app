@extends('layouts.portal')

@section('title', __('Perfil da Sua Marca & Redes | Portal NUME'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ __('Perfil da Sua Marca & Redes Sociais') }}</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-50 text-orange-700 border border-orange-200 shadow-sm">
                    <svg class="w-3.5 h-3.5 text-orange-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Sofi Copilot AI</span>
                </span>
            </div>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Cadastre seu site, redes e diferenciais para que a Sofi estruture roteiros de alta conversão calibrados exatamente para o seu negócio.') }}
            </p>
        </div>
        <a href="{{ route('portal.dashboard') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition flex items-center gap-1.5 group">
            <svg class="w-3.5 h-3.5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>{{ __('Voltar à esteira') }}</span>
        </a>
    </div>

    @if (session('warning'))
        <div class="bg-amber-50 border-2 border-amber-400 text-amber-950 px-5 py-4 rounded-xl text-sm font-semibold flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <p class="font-bold text-amber-900">{{ __('Atenção: Acesso Restrito à Produção') }}</p>
                <p class="text-xs text-amber-800 font-normal mt-0.5">{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-xs font-semibold">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Seção Sofi IA: Dossiê Estratégico de Posicionamento e Rastreamento de Site -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 text-white flex items-center justify-center shadow-md shadow-orange-500/20">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-extrabold text-zinc-900 tracking-tight">
                            {{ __('Dossiê Estratégico & Crawler de Site Sofi IA') }}
                        </h2>
                        @if ($cliente->brand_study)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Calibrado') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                {{ __('Pendente de Leitura') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        {{ __('A Sofi lê o seu site e as redes que você conectou (perfil e posts recentes) e extrai serviços, diferenciais, dores e o que já funciona no seu conteúdo.') }}
                    </p>
                </div>
            </div>

            <!-- Botão de Ação / Re-escanear -->
            <form action="{{ route('portal.profile.analyze-dna') }}" method="POST" class="shrink-0" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button span').innerText = '{{ __('Analisando site...') }}';">
                @csrf
                <input type="hidden" name="website" value="{{ $cliente->website }}">
                <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-zinc-900 hover:bg-orange-600 transition shadow-sm">
                    <svg class="w-4 h-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ $cliente->brand_study ? __('Re-escanear Site & Calibrar') : __('Escanear Site & Gerar Dossiê') }}</span>
                </button>
            </form>
        </div>

        @if ($cliente->brand_study)
            @php
                $study = $cliente->brand_study;
            @endphp
            <div class="space-y-4">
                <!-- UVP -->
                @if (! empty($study['unique_value_proposition']))
                    <div class="p-4 rounded-xl bg-orange-50/50 border border-orange-100 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-orange-600">{{ __('Proposta Única de Valor (UVP)') }}</span>
                        <p class="text-xs text-zinc-800 font-semibold leading-relaxed">
                            {{ $study['unique_value_proposition'] }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Ofertas Principais Detectadas -->
                    @if (! empty($study['flagship_offerings']) && is_array($study['flagship_offerings']))
                        <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200/80 space-y-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-600 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ __('Produtos & Serviços Principais (Site)') }}
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($study['flagship_offerings'] as $offering)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-white text-zinc-800 border border-zinc-200 shadow-2xs">
                                        {{ $offering }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Dores Mapeadas -->
                    @if (! empty($study['target_audience_pains']) && is_array($study['target_audience_pains']))
                        <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200/80 space-y-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-600 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ __('Dores Mapeadas do Público (Ganchos)') }}
                            </span>
                            <ul class="text-xs text-zinc-700 space-y-1">
                                @foreach ($study['target_audience_pains'] as $pain)
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-orange-500 font-bold">•</span>
                                        <span>{{ $pain }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if (! empty($study['content_pillars']) && is_array($study['content_pillars']))
                    <!-- Pilares Editoriais -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Pilares de Conteúdo Calibrados') }}</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                            @foreach ($study['content_pillars'] as $pillarItem)
                                @php
                                    $pTitle = is_array($pillarItem) ? ($pillarItem['pillar'] ?? '') : $pillarItem;
                                    $pFocus = is_array($pillarItem) ? ($pillarItem['focus'] ?? '') : '';
                                @endphp
                                <div class="p-3 rounded-xl bg-white border border-zinc-200 shadow-2xs">
                                    <p class="text-xs font-bold text-zinc-900">{{ $pTitle }}</p>
                                    @if ($pFocus)
                                        <p class="text-[11px] text-zinc-500 mt-0.5 line-clamp-2">{{ $pFocus }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($study['social_insights']['accounts']))
                    <!-- Redes lidas pela Sofi -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Redes analisadas') }}</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($study['social_insights']['accounts'] as $account)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs border {{ ($account['status'] ?? '') === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-zinc-50 text-zinc-500 border-zinc-200' }}">
                                    <span class="font-bold">{{ ucfirst($account['platform']) }}</span>
                                    @if (($account['status'] ?? '') === 'ok')
                                        @isset($account['followers'])
                                            <span>{{ number_format($account['followers'], 0, ',', '.') }} {{ __('seguidores') }}</span>
                                        @endisset
                                        <span>· {{ trans_choice(':count post lido|:count posts lidos', count($account['posts'] ?? []), ['count' => count($account['posts'] ?? [])]) }}</span>
                                    @else
                                        <span>{{ __('leitura indisponível agora') }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($cliente->brand_study_generated_at)
                    <div class="flex items-center justify-between text-[11px] text-zinc-400 pt-2 border-t border-zinc-100">
                        <span>{{ __('Último estudo gerado em:') }} {{ $cliente->brand_study_generated_at->format('d/m/Y H:i') }}</span>
                        @if (! empty($study['crawler_summary']['crawled_url']))
                            <span class="truncate max-w-xs">{{ __('Fonte:') }} {{ $study['crawler_summary']['crawled_url'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        @else
            <!-- Estado Inicial: Nenhum estudo ainda -->
            <div class="p-6 rounded-xl bg-zinc-50 border border-dashed border-zinc-300 text-center space-y-3">
                <div class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-900">{{ __('Nenhum estudo de site gerado ainda') }}</h3>
                    <p class="text-xs text-zinc-500 max-w-md mx-auto mt-1">
                        {{ __('Insira o site da sua empresa no formulário abaixo e clique em "Escanear Site & Gerar Dossiê" para calibrar automaticamente a Sofi IA com seus produtos e serviços reais.') }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    <form action="{{ route('portal.profile.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Bloco 1: Informações Institucionais & Contato -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-zinc-100">
                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900">
                    {{ __('Dados da Empresa & Responsável') }}
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Nome do Responsável') }} <span class="text-orange-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $cliente->name) }}"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                </div>

                <div class="space-y-1.5">
                    <label for="company_name" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Nome da Empresa / Marca') }}
                    </label>
                    <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $cliente->company_name) }}"
                           placeholder="{{ __('Ex: Zaragoza Tech S.L. ou Clínica Estética Aragão') }}"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                </div>

                <div class="space-y-1.5">
                    <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Telefone / WhatsApp Comercial') }}
                    </label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $cliente->phone) }}"
                           placeholder="+34 976 000 000 / +55 11 99999-9999"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                </div>

                <div class="space-y-1.5">
                    <label for="website" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Site Oficial / Loja Online (URL)') }}
                    </label>
                    <input type="text" name="website" id="website" value="{{ old('website', $cliente->website) }}"
                           placeholder="https://minhaempresa.com"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                </div>
            </div>
        </div>

        <!-- Bloco 2: Redes Sociais da Marca (Para CTAs e Ganchos) -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-zinc-100">
                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900">
                        {{ __('Canais & Redes Sociais Ativas') }}
                    </h2>
                </div>
            </div>
            <p class="text-xs text-zinc-500">
                {{ __('Preencha apenas os canais que sua empresa utiliza ativamente (não é obrigatório ter site nem todas as redes — basta 1 canal ou perfil). A Sofi usará seus canais para criar CTAs personalizadas nos vídeos.') }}
            </p>

            @php($socialProfile = $cliente->socialProfile)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (\App\Enums\PlataformaAlvo::cases() as $plataforma)
                    @php($field = $plataforma->clienteField())
                    @php($isConnected = (bool) $socialProfile?->isPlatformConnected($plataforma->value))
                    @php($connectedHandle = $socialProfile?->connectedHandle($plataforma->value))
                    <div class="space-y-1.5">
                        <label for="{{ $field }}" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-700">
                            @include('portal.profile._social-icon', ['platform' => $plataforma->value])
                            <span>{{ __($plataforma->label()) }}</span>
                        </label>
                        <input type="text" name="{{ $field }}" id="{{ $field }}" value="{{ old($field, $cliente->{$field}) }}"
                               placeholder="{{ $plataforma->placeholder() }}"
                               class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                        @if ($isConnected)
                            <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    {{ __('Conectado via Ecossistema') }}@if ($connectedHandle) ({{ $connectedHandle }})@endif
                                </span>
                                @if ($connectedHandle && $connectedHandle !== $cliente->{$field})
                                    <button type="button" data-social-fill="{{ $field }}" data-social-value="{{ $connectedHandle }}"
                                            class="font-semibold text-orange-600 hover:text-orange-700 underline underline-offset-2">
                                        {{ __('Usar este perfil') }}
                                    </button>
                                @endif
                                <a href="{{ route('portal.social.index') }}" class="font-semibold text-zinc-500 hover:text-zinc-700 underline underline-offset-2">
                                    {{ __('Gerenciar conexão') }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <script>
                document.querySelectorAll('[data-social-fill]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var input = document.getElementById(button.dataset.socialFill);
                        if (input) { input.value = button.dataset.socialValue; input.focus(); }
                    });
                });
            </script>
        </div>

        <!-- Bloco 3: Inteligência de Posicionamento para a Sofi AI -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-zinc-100">
                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900">
                    {{ __('DNA da Marca & Segmento de Mercado') }}
                </h2>
            </div>
            <p class="text-xs text-zinc-500">
                {{ __('Essas diretrizes calibram o vocabulário, dores e objeções que a Sofi explorará nos ganchos de 3 segundos.') }}
            </p>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="brand_niche" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                            {{ __('Nicho de Atuação / Ramo de Negócio') }} <span class="text-orange-500">*</span>
                        </label>
                        <span class="text-[10px] font-bold text-orange-600 uppercase">{{ __('Obrigatório') }}</span>
                    </div>
                    <input type="text" name="brand_niche" id="brand_niche" required value="{{ old('brand_niche', $cliente->brand_niche) }}"
                           placeholder="{{ __('Ex: Clínica Odontológica de Alto Padrão, Indústria Metalúrgica B2B, E-commerce de Moda Sustentável...') }}"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition">
                </div>

                <div class="space-y-1.5">
                    <label for="target_audience" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Público-Alvo & Perfil do Comprador Ideal (ICP)') }}
                    </label>
                    <textarea name="target_audience" id="target_audience" rows="3"
                              placeholder="{{ __('Ex: Diretores de Logística e CEOs de empresas de Zaragoza que buscam reduzir custos operacionais... ou Mulheres de 25 a 45 anos que valorizam estética avançada.') }}"
                              class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition leading-relaxed">{{ old('target_audience', $cliente->target_audience) }}</textarea>
                </div>

                <div class="space-y-1.5">
                    <label for="key_differentials" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                        {{ __('Principais Diferenciais Competitivos') }}
                    </label>
                    <textarea name="key_differentials" id="key_differentials" rows="3"
                              placeholder="{{ __('Ex: Mais de 15 anos no mercado, entrega em 24h para toda a Espanha, equipamentos importados de última geração, atendimento humanizado...') }}"
                              class="w-full bg-zinc-50 border border-zinc-200 text-zinc-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:bg-white transition leading-relaxed">{{ old('key_differentials', $cliente->key_differentials) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Ação -->
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-8 py-3 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar Perfil da Marca') }}
            </button>
            <a href="{{ route('portal.dashboard') }}"
               class="inline-flex items-center justify-center bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-semibold text-sm px-6 py-3 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
