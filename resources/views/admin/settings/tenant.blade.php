@extends('layouts.app')

@section('title', __('NUME | Redes Sociais & SEO Estratégico'))
@section('breadcrumb', __('Redes Sociais & SEO'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-orange-500/10 text-orange-400 border border-orange-500/20 mb-2">
                {{ __('Presença Digital & Tráfego Orgânico') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Redes Sociais & SEO da Plataforma') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Conecte os canais oficiais da produtora e utilize a Sofi AI para calibrar meta tags de autoridade e ranqueamento.') }}
            </p>
        </div>

        <button type="button" id="btn-sofi-seo" onclick="generateSofiSeo()"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-orange-900/20 active:scale-95 transition">
            <span id="btn-sofi-text">{{ __('Sofi: Gerar SEO com Olhar CEO') }}</span>
        </button>
    </div>

    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div id="sofi-alert" class="hidden p-4 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-300 text-xs font-semibold flex items-center gap-2">
        <span id="sofi-alert-msg"></span>
    </div>

    <!-- Navegação por Abas (Tabs) 100% Tailwind -->
    <x-settings-tabs active="platform" />

    <form method="POST" action="{{ route('admin.settings.platform.update') }}" class="space-y-6">
        @csrf

        <!-- Seção 1: Redes Sociais da Produtora -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>{{ __('Canais & Redes Sociais Oficiais') }}</span>
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Estes links serão expostos na vitrine pública e nos rodapés dos relatórios de vídeo.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="social_instagram" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-pink-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                        <span>Instagram (Perfil ou URL)</span>
                    </label>
                    <input type="text" id="social_instagram" name="social_instagram"
                        value="{{ old('social_instagram', $tenant->social_instagram) }}"
                        placeholder="@numecreative ou https://instagram.com/numecreative"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="social_youtube" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                        <span>Canal do YouTube</span>
                    </label>
                    <input type="text" id="social_youtube" name="social_youtube"
                        value="{{ old('social_youtube', $tenant->social_youtube) }}"
                        placeholder="https://youtube.com/@numecreative"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="social_linkedin" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451c.979 0 1.778-.773 1.778-1.729V1.73C24 .774 23.205 0 22.225 0z"/>
                        </svg>
                        <span>LinkedIn (Company ou Perfil)</span>
                    </label>
                    <input type="text" id="social_linkedin" name="social_linkedin"
                        value="{{ old('social_linkedin', $tenant->social_linkedin) }}"
                        placeholder="https://linkedin.com/company/numecreative"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="social_tiktok" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-cyan-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                        <span>TikTok</span>
                    </label>
                    <input type="text" id="social_tiktok" name="social_tiktok"
                        value="{{ old('social_tiktok', $tenant->social_tiktok) }}"
                        placeholder="@numecreative"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="social_whatsapp" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>WhatsApp Comercial (Número com DDI)</span>
                    </label>
                    <input type="text" id="social_whatsapp" name="social_whatsapp"
                        value="{{ old('social_whatsapp', $tenant->social_whatsapp) }}"
                        placeholder="+34 600 000 000 ou +55 11 99999-9999"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="social_facebook" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400">
                        <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        <span>Facebook</span>
                    </label>
                    <input type="text" id="social_facebook" name="social_facebook"
                        value="{{ old('social_facebook', $tenant->social_facebook) }}"
                        placeholder="https://facebook.com/numecreative"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>
            </div>
        </div>

        <!-- Seção 1.5: Nume Social Engine — Credenciais OAuth Nativas das 8 Redes -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span>{{ __('Nume Social Engine — Credenciais OAuth das Redes') }}</span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-1">
                        {{ __('Configure aqui o Client ID e Client Secret oficiais de cada rede (criptografados com AES-256 no banco). Assim que preenchidos, os botões "Conectar" ficam ativos no Portal do Cliente.') }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0">
                    AES-256 Encrypted
                </span>
            </div>

            <div class="space-y-4">
                @foreach ($oauthProviders ?? [] as $provider)
                    <div class="bg-zinc-950/70 border border-zinc-800/90 rounded-xl p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <span class="text-xs font-extrabold text-white tracking-wide">{{ $provider['label'] }}</span>
                            @if ($provider['configured'])
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    {{ __('OAuth Ativo') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-zinc-800 text-zinc-400 border border-zinc-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-500"></span>
                                    {{ __('OAuth Pendente') }}
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="{{ $provider['id_field'] }}" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                                    {{ $provider['id_label'] }}
                                </label>
                                <input type="text"
                                    id="{{ $provider['id_field'] }}"
                                    name="{{ $provider['id_field'] }}"
                                    value="{{ old($provider['id_field'], $provider['id_value']) }}"
                                    placeholder="{{ __('Cole o Client ID / App ID...') }}"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-mono">
                            </div>

                            <div class="space-y-1">
                                <label for="{{ $provider['secret_field'] }}" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                                    {{ $provider['secret_label'] }}
                                </label>
                                <input type="password"
                                    id="{{ $provider['secret_field'] }}"
                                    name="{{ $provider['secret_field'] }}"
                                    autocomplete="new-password"
                                    placeholder="{{ $provider['has_secret'] ? __('•••••••••••• (já configurado — preencha apenas para substituir)') : __('Cole o Client Secret / App Secret...') }}"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-mono">
                            </div>
                        </div>

                        @if (! empty($provider['extra_field']))
                            <div class="space-y-1">
                                <label for="{{ $provider['extra_field'] }}" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                                    {{ $provider['extra_label'] }}
                                </label>
                                <input type="text"
                                    id="{{ $provider['extra_field'] }}"
                                    name="{{ $provider['extra_field'] }}"
                                    value="{{ old($provider['extra_field'], $provider['extra_value'] ?? '') }}"
                                    placeholder="{{ __('Opcional — usado no Meta Login for Business') }}"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-mono">
                            </div>
                        @endif

                        <div class="pt-1 border-t border-zinc-900 flex flex-col gap-1">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
                                {{ __('Redirect URI para cadastrar no Console da Rede:') }}
                            </span>
                            @foreach ($provider['callbacks'] as $cb)
                                <code class="text-[11px] font-mono text-orange-300/90 bg-zinc-900/90 px-2.5 py-1 rounded border border-zinc-800 select-all break-all">{{ $cb }}</code>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Seção 2: SEO da Plataforma (calibrado pela Sofi) -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 space-y-5">
            <div class="border-b border-zinc-800/80 pb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span>{{ __('Meta Tags SEO & Compartilhamento Social') }}</span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-1">
                        {{ __('Otimizado para indexação no Google, visualização no WhatsApp e preview em redes sociais.') }}
                    </p>
                </div>
                @if($tenant->seo_last_generated_at)
                    <span class="text-[11px] font-mono text-zinc-500">
                        Última calibração: {{ $tenant->seo_last_generated_at->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>

            <div class="space-y-4">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="seo_meta_title" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                            {{ __('Título SEO (Meta Title)') }}
                        </label>
                        <span class="text-[10px] text-zinc-500 font-mono" id="title-counter">max 70 chars</span>
                    </div>
                    <input type="text" id="seo_meta_title" name="seo_meta_title" maxlength="70"
                        value="{{ old('seo_meta_title', $tenant->seo_meta_title) }}"
                        placeholder="NUME Vídeos Estratégicos | Produção Audiovisual de Alta Conversão"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-medium">
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="seo_meta_description" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                            {{ __('Descrição SEO (Meta Description)') }}
                        </label>
                        <span class="text-[10px] text-zinc-500 font-mono" id="desc-counter">max 160 chars</span>
                    </div>
                    <textarea id="seo_meta_description" name="seo_meta_description" rows="2" maxlength="160"
                        placeholder="Vídeos estratégicos pensados para transformar audiência em clientes..."
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition leading-relaxed">{{ old('seo_meta_description', $tenant->seo_meta_description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="seo_og_description" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                            {{ __('Descrição Social (Open Graph / WhatsApp)') }}
                        </label>
                        <input type="text" id="seo_og_description" name="seo_og_description" maxlength="200"
                            value="{{ old('seo_og_description', $tenant->seo_og_description) }}"
                            placeholder="Transforme visualizações em vendas com roteiros persuasivos..."
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                    </div>

                    <div class="space-y-1.5">
                        <label for="seo_og_image" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                            {{ __('Imagem de Compartilhamento (URL OG Image)') }}
                        </label>
                        <input type="text" id="seo_og_image" name="seo_og_image"
                            value="{{ old('seo_og_image', $tenant->seo_og_image) }}"
                            placeholder="https://sua-plataforma.com/assests/img/og-cover.png"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-mono text-[11px]">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="seo_schema_keywords" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ __('Palavras-Chave de Relevância Orgânica (separadas por vírgula)') }}
                    </label>
                    <input type="text" id="seo_schema_keywords" name="seo_schema_keywords"
                        value="{{ old('seo_schema_keywords', $tenant->seo_schema_keywords) }}"
                        placeholder="video marketing, reels pyme, produtora de videos zaragoza, autoridade b2b"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition">
                </div>

                <div class="space-y-1.5">
                    <label for="seo_canonical_url" class="block text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ __('URL Canônica Oficial') }}
                    </label>
                    <input type="text" id="seo_canonical_url" name="seo_canonical_url"
                        value="{{ old('seo_canonical_url', $tenant->seo_canonical_url ?? url('/vitrine/' . $tenant->slug)) }}"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:border-orange-500 focus:outline-none transition font-mono text-[11px]">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="submit"
                class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white font-bold text-xs uppercase tracking-wider shadow-md shadow-orange-900/30 transition">
                {{ __('Salvar Alterações') }}
            </button>
        </div>
    </form>
</div>

<script>
function generateSofiSeo() {
    const btn = document.getElementById('btn-sofi-seo');
    const txt = document.getElementById('btn-sofi-text');
    const alertBox = document.getElementById('sofi-alert');
    const alertMsg = document.getElementById('sofi-alert-msg');

    btn.disabled = true;
    btn.classList.add('opacity-75');
    txt.textContent = 'Sofi analisando perfil...';

    fetch('{{ route('admin.settings.platform.regenerate-seo') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success' && res.data) {
            document.getElementById('seo_meta_title').value = res.data.seo_meta_title || '';
            document.getElementById('seo_meta_description').value = res.data.seo_meta_description || '';
            document.getElementById('seo_og_description').value = res.data.seo_og_description || '';
            document.getElementById('seo_og_image').value = res.data.seo_og_image || '';
            document.getElementById('seo_schema_keywords').value = res.data.seo_schema_keywords || '';
            document.getElementById('seo_canonical_url').value = res.data.seo_canonical_url || '';

            alertBox.classList.remove('hidden');
            alertMsg.textContent = res.message;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao conectar com a Sofi AI.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-75');
        txt.textContent = 'Sofi: Gerar SEO com Olhar CEO';
    });
}
</script>
@endsection
