@extends('layouts.app')

@section('title', 'NUME | ' . $video->title)
@section('breadcrumb', __('Detalhes do Vídeo'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top: Header, Back Link & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('videos.index', $production) }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5 mb-2">
                ← {{ __('Voltar para Vídeos da Produção') }}
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ $video->title }}</h1>
                @php
                    $statusClasses = match($video->status) {
                        'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'ready' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'processing' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                    };
                @endphp
                <span class="px-3 py-1 text-xs font-extrabold uppercase rounded-lg border {{ $statusClasses }}">
                    {{ ucfirst(str_replace('_', ' ', $video->status)) }}
                </span>
            </div>
            @if($video->description)
                <p class="text-xs text-zinc-400 mt-1 max-w-3xl">{{ $video->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if ($video->video_url)
                <a href="{{ $video->video_url }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-extrabold shadow-lg shadow-red-600/20 transition">
                    @if ($video->video_type === 'youtube' && $video->is_vertical)
                        ▶️ {{ __('Assistir no YouTube Shorts') }} ↗
                    @elseif ($video->video_type === 'youtube')
                        ▶️ {{ __('Assistir no YouTube') }} ↗
                    @elseif ($video->video_type === 'vimeo')
                        ▶️ {{ __('Assistir no Vimeo') }} ↗
                    @else
                        ▶️ {{ __('Abrir Vídeo na Web') }} ↗
                    @endif
                </a>
            @endif

            @can('update', $video)
                <a href="{{ route('videos.edit', ['production' => $production, 'video' => $video]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold transition">
                    ✏️ {{ __('Editar') }}
                </a>

                @if ($video->status === 'processing')
                    <form action="{{ route('videos.mark-ready', ['production' => $production, 'video' => $video]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition">
                            ⚡ {{ __('Marcar como Pronto') }}
                        </button>
                    </form>
                @elseif ($video->status === 'ready')
                    <form action="{{ route('videos.publish', ['production' => $production, 'video' => $video]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                            🚀 {{ __('Publicar Vídeo') }}
                        </button>
                    </form>
                @endif

                @if (!$video->isPublished())
                    <form action="{{ route('videos.destroy', ['production' => $production, 'video' => $video]) }}" method="POST" class="inline" onsubmit="return confirm('Deseja realmente excluir este vídeo?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-950 hover:bg-rose-900 text-rose-300 text-xs font-bold transition">
                            🗑️ {{ __('Excluir') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Card Sofi: Central de Notificação & Aprovação do Cliente -->
    @if ($cliente)
        <div class="p-6 rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-orange-950/30 border border-orange-500/30 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800/80 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center text-white font-extrabold shadow-md shadow-orange-900/30">
                        ✨
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-extrabold text-white tracking-tight">
                                {{ __('Sofi Agente: Notificação de Corte Final') }}
                            </h3>
                            @if ($video->isAssetLocked())
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    🔒 {{ __('Custódia 50/50 Ativa') }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    ✓ {{ __('Ativo Liberado') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            Destinatário: <strong class="text-zinc-200">{{ $cliente->name }}</strong> ({{ $cliente->email }})
                            @if ($cliente->phone)
                                · WhatsApp: <span class="text-zinc-300 font-mono">{{ $cliente->phone }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Disparo de E-mail via Sofi -->
                    <form action="{{ route('videos.notify-cliente', ['production' => $production, 'video' => $video]) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="channel" value="email">
                        <button type="submit" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 shadow-md shadow-orange-950/40 transition active:scale-95 cursor-pointer">
                            <span>✉️</span>
                            <span>{{ __('Enviar E-mail de Aprovação') }}</span>
                        </button>
                    </form>

                    <!-- Abertura Direta do WhatsApp -->
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-950/40 transition active:scale-95">
                        <span>📲</span>
                        <span>{{ __('Abrir WhatsApp') }}</span>
                    </a>

                    <!-- Botão Copiar Texto Sofi -->
                    <button type="button" onclick="copySofiWhatsAppText()" id="btn-copy-whatsapp"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition active:scale-95 cursor-pointer">
                        <span>📋</span>
                        <span id="label-copy-whatsapp">{{ __('Copiar Mensagem') }}</span>
                    </button>
                </div>
            </div>

            <!-- Preview da Mensagem Sofi Gerada -->
            <div class="rounded-xl bg-zinc-950/60 border border-zinc-800/80 p-3.5 space-y-2">
                <div class="flex items-center justify-between text-[11px] text-zinc-400">
                    <span class="font-bold uppercase tracking-wider text-orange-400/90">{{ __('Prévia da Mensagem Personalizada:') }}</span>
                    <span class="text-zinc-500">{{ __('Link seguro de 14 dias embutido') }}</span>
                </div>
                <pre id="sofi-whatsapp-text" class="text-xs text-zinc-300 font-mono whitespace-pre-wrap leading-relaxed max-h-36 overflow-y-auto p-2 rounded-lg bg-zinc-900/50 border border-zinc-800/50">{{ $whatsappMessage }}</pre>
            </div>
        </div>

        <script>
            function copySofiWhatsAppText() {
                const textElem = document.getElementById('sofi-whatsapp-text');
                const btnLabel = document.getElementById('label-copy-whatsapp');
                if (!textElem) return;

                navigator.clipboard.writeText(textElem.innerText).then(() => {
                    if (btnLabel) btnLabel.innerText = 'Copiado!';
                    setTimeout(() => {
                        if (btnLabel) btnLabel.innerText = 'Copiar Mensagem';
                    }, 2500);
                });
            }
        </script>
    @endif

    <!-- Main Player Card -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4 shadow-xl">
        @php
            $isVertical = $video->is_vertical;
        @endphp
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full {{ $video->video_type === 'youtube' ? 'bg-red-500' : ($video->video_type === 'vimeo' ? 'bg-cyan-500' : 'bg-orange-500') }}"></span>
                <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-300">
                    {{ $video->video_type === 'youtube' ? ($isVertical ? 'Player YouTube Shorts (Vertical)' : 'Player YouTube') : ($video->video_type === 'vimeo' ? 'Player Vimeo' : 'Player Direto') }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <!-- Seletor de Proporção -->
                <div class="flex items-center gap-1 bg-zinc-950 border border-zinc-800 p-1 rounded-xl text-[11px] font-bold">
                    <button type="button" onclick="setPlayerAspect('vertical')" id="btn-aspect-vertical"
                            class="px-2.5 py-1 rounded-lg transition {{ $isVertical ? 'bg-orange-600 text-white' : 'text-zinc-400 hover:text-white' }}">
                        📱 9:16 (Vertical)
                    </button>
                    <button type="button" onclick="setPlayerAspect('horizontal')" id="btn-aspect-horizontal"
                            class="px-2.5 py-1 rounded-lg transition {{ !$isVertical ? 'bg-orange-600 text-white' : 'text-zinc-400 hover:text-white' }}">
                        🖥️ 16:9 (Horizontal)
                    </button>
                </div>

                @if ($video->video_url)
                    <a href="{{ $video->video_url }}" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-400 hover:text-orange-300 transition">
                        <span>{{ __('Abrir link original') }}</span> ↗
                    </a>
                @endif
            </div>
        </div>

        <!-- Video Player Container (Suporta YouTube Shorts 9:16, YouTube 16:9, Vimeo e MP4) -->
        <div class="w-full flex items-center justify-center py-2">
            <div id="player-frame-wrapper" class="relative {{ $isVertical ? 'w-full max-w-[340px] aspect-[9/16]' : 'w-full aspect-video' }} rounded-2xl overflow-hidden bg-zinc-950 border border-zinc-800 shadow-2xl transition-all duration-300">
                @if ($video->video_type === 'youtube' || $video->video_type === 'vimeo')
                    <iframe 
                        src="{{ $video->embed_url }}" 
                        class="w-full h-full border-0" 
                        title="{{ $video->title }}"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen>
                    </iframe>
                @elseif ($video->video_url)
                    <video controls class="w-full h-full object-contain bg-black">
                        <source src="{{ $video->video_url }}" type="video/mp4">
                        {{ __('Seu navegador não suporta a tag de vídeo.') }}
                    </video>
                @else
                    <div class="text-center text-zinc-500 text-xs p-8">
                        {{ __('Nenhuma URL de vídeo cadastrada.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    function setPlayerAspect(aspect) {
        const wrapper = document.getElementById('player-frame-wrapper');
        const btnV = document.getElementById('btn-aspect-vertical');
        const btnH = document.getElementById('btn-aspect-horizontal');

        if (aspect === 'vertical') {
            wrapper.className = "relative w-full max-w-[340px] aspect-[9/16] rounded-2xl overflow-hidden bg-zinc-950 border border-zinc-800 shadow-2xl transition-all duration-300";
            btnV.className = "px-2.5 py-1 rounded-lg transition bg-orange-600 text-white";
            btnH.className = "px-2.5 py-1 rounded-lg transition text-zinc-400 hover:text-white";
        } else {
            wrapper.className = "relative w-full aspect-video rounded-2xl overflow-hidden bg-zinc-950 border border-zinc-800 shadow-2xl transition-all duration-300";
            btnH.className = "px-2.5 py-1 rounded-lg transition bg-orange-600 text-white";
            btnV.className = "px-2.5 py-1 rounded-lg transition text-zinc-400 hover:text-white";
        }
    }
    </script>

    <!-- Metadata Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Duração') }}</p>
            <p class="text-sm font-bold text-white mt-1">
                @if ($video->duration_seconds)
                    ⏱️ {{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}
                @else
                    {{ __('Não informada') }}
                @endif
            </p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Plataforma') }}</p>
            <p class="text-sm font-bold text-white mt-1">
                {{ $video->platform ? ucfirst($video->platform) : ($video->video_type === 'youtube' ? 'YouTube' : __('Não especificada')) }}
            </p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Criado em') }}</p>
            <p class="text-sm font-bold text-white mt-1">{{ $video->created_at->format('d/m/Y') }}</p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Publicado em') }}</p>
            <p class="text-sm font-bold text-white mt-1">
                {{ $video->published_at ? $video->published_at->format('d/m/Y H:i') : __('Não publicado') }}
            </p>
        </div>
    </div>

    <!-- Sofi SEO Pack -->
    @if ($video->sofi_seo_title || $video->sofi_seo_description || !empty($video->sofi_hashtags))
        <div class="p-6 rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-orange-950/20 border border-orange-500/20 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">✨</span>
                    <div>
                        <h3 class="text-sm font-extrabold text-white">{{ __('Sofi SEO Pack: Estratégia de Postagem') }}</h3>
                        <p class="text-[11px] text-zinc-400">{{ __('Metadados otimizados para indexação orgânica e retenção de público.') }}</p>
                    </div>
                </div>
                <button type="button" onclick="copySeoPack()" class="px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition flex items-center gap-1">
                    <span id="copy-btn-text">{{ __('Copiar Tudo') }}</span>
                </button>
            </div>

            <div class="space-y-3 text-xs">
                @if ($video->sofi_seo_title)
                    <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">{{ __('Título Otimizado:') }}</span>
                        <p class="font-bold text-white" id="seo-title">{{ $video->sofi_seo_title }}</p>
                    </div>
                @endif

                @if ($video->sofi_seo_description)
                    <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block mb-1">{{ __('Descrição / Legenda:') }}</span>
                        <pre class="font-sans text-zinc-300 whitespace-pre-wrap leading-relaxed" id="seo-desc">{{ $video->sofi_seo_description }}</pre>
                    </div>
                @endif

                @if (!empty($video->sofi_hashtags))
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 mr-1">Hashtags:</span>
                        @foreach ($video->sofi_hashtags as $tag)
                            <span class="px-2 py-0.5 rounded-md bg-zinc-950 border border-orange-500/30 text-orange-400 font-semibold text-[11px]">
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <script>
            function copySeoPack() {
                const title = document.getElementById('seo-title')?.innerText || '';
                const desc = document.getElementById('seo-desc')?.innerText || '';
                const full = `${title}\n\n${desc}`;
                navigator.clipboard.writeText(full).then(() => {
                    const btn = document.getElementById('copy-btn-text');
                    if (btn) {
                        btn.textContent = 'Copiado! ✓';
                        setTimeout(() => btn.textContent = 'Copiar Tudo', 2000);
                    }
                });
            }
        </script>
    @endif

    <!-- Pipeline & Esteira de Distribuição Social (Multi-Postagem) -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6 shadow-xl">
        <!-- Header da Seção -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">📡</span>
                    <h2 class="text-base sm:text-lg font-extrabold text-white">{{ __('Fluxo de Entrega & Distribuição Social Multiplataforma') }}</h2>
                </div>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Entenda a esteira do vídeo: da finalização interna na produtora até a postagem nas redes sociais do cliente.') }}
                </p>
            </div>
            <a href="{{ route('distributions.index') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white text-xs font-bold transition">
                <span>{{ __('Ver Monitor Geral de Distribuição') }}</span> ↗
            </a>
        </div>

        <!-- 3-Step Pipeline Visual -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <!-- Etapa 1: Produção Interna -->
            <div class="p-4 rounded-xl bg-zinc-950 border {{ $video->isPublished() ? 'border-emerald-500/30 bg-emerald-950/10' : 'border-zinc-800' }} flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">1. {{ __('Produção Interna') }}</span>
                        @if ($video->isPublished())
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">✓ {{ __('Entregue') }}</span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-amber-500/20 text-amber-400 border border-amber-500/30">⏳ {{ ucfirst($video->status) }}</span>
                        @endif
                    </div>
                    <p class="text-xs font-bold text-white">{{ __('Corte & Aprovação no SaaS') }}</p>
                    <p class="text-[11px] text-zinc-400 mt-1">
                        @if ($video->published_at)
                            {{ __('Aprovado e publicado internamente em :date.', ['date' => $video->published_at->format('d/m/Y H:i')]) }}
                        @else
                            {{ __('Vídeo aguardando publicação interna pelo time.') }}
                        @endif
                    </p>
                </div>
                @if ($video->video_url)
                    <div class="mt-3 pt-3 border-t border-zinc-900 flex items-center justify-between">
                        <span class="text-[10px] text-zinc-500">{{ __('Destino do Arquivo:') }}</span>
                        <a href="{{ $video->video_url }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-bold text-orange-400 hover:text-orange-300 transition flex items-center gap-1">
                            <span>{{ $video->video_type === 'youtube' ? 'YouTube' : ($video->video_type === 'vimeo' ? 'Vimeo' : 'Web') }}</span> ↗
                        </a>
                    </div>
                @endif
            </div>

            <!-- Etapa 2: Sofi AI SEO -->
            <div class="p-4 rounded-xl bg-zinc-950 border {{ $video->sofi_seo_generated_at ? 'border-orange-500/30 bg-orange-950/10' : 'border-zinc-800' }} flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">2. {{ __('Otimização Sofi AI') }}</span>
                        @if ($video->sofi_seo_generated_at)
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-orange-500/20 text-orange-400 border border-orange-500/30">✨ {{ __('Otimizado') }}</span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-zinc-800 text-zinc-400">⏳ {{ __('Pendente') }}</span>
                        @endif
                    </div>
                    <p class="text-xs font-bold text-white">{{ __('Estratégia de SEO Orgânico') }}</p>
                    <p class="text-[11px] text-zinc-400 mt-1">
                        @if ($video->sofi_seo_generated_at)
                            {{ __('Título, legenda e hashtags calibradas para algoritmo de retenção.') }}
                        @else
                            {{ __('Aguardando publicação para calibração automática pela Sofi.') }}
                        @endif
                    </p>
                </div>
                <div class="mt-3 pt-3 border-t border-zinc-900 flex items-center justify-between">
                    <span class="text-[10px] text-zinc-500">{{ __('Status do Copy:') }}</span>
                    <span class="text-[11px] font-bold {{ $video->sofi_seo_generated_at ? 'text-orange-400' : 'text-zinc-500' }}">
                        {{ $video->sofi_seo_generated_at ? __('Pronto para Postar') : __('Não Gerado') }}
                    </span>
                </div>
            </div>

            <!-- Etapa 3: Distribuição Social -->
            @php
                $hasPublications = $video->publications->isNotEmpty();
                $latestPub = $video->latestPublication;
            @endphp
            <div class="p-4 rounded-xl bg-zinc-950 border {{ $hasPublications ? 'border-blue-500/30 bg-blue-950/10' : 'border-zinc-800' }} flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">3. {{ __('Disparo Multi-Redes') }}</span>
                        @if ($hasPublications)
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                🚀 {{ ucfirst($latestPub->status) }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-md bg-zinc-800 text-zinc-400">{{ __('Não Disparado') }}</span>
                        @endif
                    </div>
                    <p class="text-xs font-bold text-white">{{ __('Postagem nas Redes do Cliente') }}</p>
                    <p class="text-[11px] text-zinc-400 mt-1">
                        @if ($hasPublications)
                            {{ __(':count disparo(s) registrado(s) para este vídeo.', ['count' => $video->publications->count()]) }}
                        @else
                            {{ __('O vídeo está pronto para ser enviado às contas sociais do cliente.') }}
                        @endif
                    </p>
                </div>
                <div class="mt-3 pt-3 border-t border-zinc-900 flex items-center justify-between">
                    <span class="text-[10px] text-zinc-500">{{ __('Redes Alvo:') }}</span>
                    <span class="text-[11px] font-bold {{ $hasPublications ? 'text-blue-400' : 'text-zinc-500' }}">
                        {{ $hasPublications ? implode(', ', $latestPub->plataformas ?? []) : __('Aguardando seleção') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Status de Conexão das Redes do Cliente -->
        <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-orange-600/10 border border-orange-500/20 flex items-center justify-center text-orange-400 font-extrabold text-sm">
                    👤
                </div>
                <div>
                    <p class="text-xs font-bold text-white">
                        {{ __('Cliente Destino:') }} <span class="text-orange-400">{{ $cliente?->name ?? $production->cliente_name ?? __('Cliente da Produção') }}</span>
                    </p>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="text-[10px] text-zinc-500">{{ __('Contas Conectadas:') }}</span>
                        @if ($socialProfile && !empty($socialProfile->platforms_connected))
                            @foreach ($socialProfile->platforms_connected as $connPlat)
                                <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-extrabold uppercase">
                                    ✓ {{ $connPlat }}
                                </span>
                            @endforeach
                        @else
                            <span class="px-2 py-0.5 rounded-md bg-zinc-800 text-zinc-400 text-[10px] font-semibold">
                                {{ __('Nenhuma rede social conectada pelo cliente ainda') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($cliente)
                <div class="flex items-center gap-2">
                    <a href="{{ route('distributions.index', ['search' => $cliente->name]) }}" 
                       class="text-xs font-bold text-zinc-400 hover:text-white transition">
                        {{ __('Filtrar por este cliente') }} →
                    </a>
                </div>
            @endif
        </div>

        <!-- Histórico de Disparos Registrados para este Vídeo -->
        @if ($video->publications->isNotEmpty())
            <div class="space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-400">
                    {{ __('Histórico de Postagens & Disparos nas Redes Sociais') }}
                </h3>
                <div class="divide-y divide-zinc-800 rounded-xl bg-zinc-950 border border-zinc-800 overflow-hidden">
                    @foreach ($video->publications as $pub)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-zinc-900/50 transition">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @php
                                        $pubStatusClasses = match($pub->status) {
                                            'publicado' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                            'agendado' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                            'processando', 'pendente' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                            'falhou' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                            default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded border {{ $pubStatusClasses }}">
                                        {{ ucfirst($pub->status) }}
                                    </span>

                                    @foreach ($pub->plataformas ?? [] as $platName)
                                        <span class="px-2 py-0.5 rounded bg-zinc-900 border border-zinc-700 text-white text-[10px] font-bold">
                                            {{ ucfirst($platName) }}
                                        </span>
                                    @endforeach

                                    <span class="text-[11px] text-zinc-500">
                                        {{ $pub->created_at->format('d/m/Y H:i') }}
                                        @if ($pub->user)
                                            • por <span class="text-zinc-300">{{ $pub->user->name }}</span>
                                        @endif
                                    </span>
                                </div>

                                <p class="text-xs font-bold text-white">{{ $pub->titulo }}</p>

                                @if ($pub->isScheduled() && $pub->scheduled_at)
                                    <p class="text-[11px] text-blue-400">
                                        ⏰ {{ __('Agendado para:') }} {{ $pub->scheduled_at->format('d/m/Y H:i') }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @php
                                    $destinationUrl = $pub->resultado['post_url'] ?? $pub->resultado['url'] ?? $pub->resultado['video_url'] ?? null;
                                @endphp
                                @if ($destinationUrl)
                                    <a href="{{ $destinationUrl }}" target="_blank" rel="noopener noreferrer"
                                       class="px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-orange-400 hover:text-orange-300 text-xs font-bold transition flex items-center gap-1">
                                        <span>{{ __('Acessar Postagem') }}</span> ↗
                                    </a>
                                @endif

                                <form action="{{ route('distributions.refresh', $pub) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" title="{{ __('Atualizar status junto à API social') }}"
                                            class="px-2.5 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-bold transition">
                                        🔄
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/80 flex items-start gap-3">
                <span class="text-lg">ℹ️</span>
                <div>
                    <p class="text-xs font-bold text-white">{{ __('Nenhum disparo social direto registrado para este vídeo ainda') }}</p>
                    <p class="text-[11px] text-zinc-400 mt-0.5">
                        {{ __('O vídeo foi concluído internamente na produtora. Para postar nas redes sociais do cliente com o SEO Pack da Sofi, use o formulário de disparo rápido abaixo.') }}
                    </p>
                </div>
            </div>
        @endif

        <!-- Formulário de Disparo Rápido para Redes Sociais -->
        @can('update', $video)
            <div class="p-5 rounded-xl bg-gradient-to-br from-zinc-950 via-zinc-950 to-orange-950/20 border border-orange-500/20 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-orange-400">
                            🚀 {{ __('Disparo Direto para as Redes do Cliente') }}
                        </h3>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            {{ __('Dispare imediatamente ou agende a postagem deste vídeo utilizando as credenciais e DNA de marca do cliente.') }}
                        </p>
                    </div>
                </div>

                <form action="{{ route('videos.distribute', ['production' => $production, 'video' => $video]) }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Seleção de Plataformas -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-300 uppercase tracking-wider mb-2">
                            {{ __('Selecione as Redes Sociais Alvo:') }}
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach ($platforms as $platformCase)
                                @php
                                    $isConnected = in_array($platformCase->value, $socialProfile?->platforms_connected ?? []);
                                @endphp
                                <label class="flex items-center gap-2 p-2.5 rounded-xl bg-zinc-900 border border-zinc-800 hover:border-orange-500/40 cursor-pointer transition select-none">
                                    <input type="checkbox" name="plataformas[]" value="{{ $platformCase->value }}"
                                           {{ $isConnected ? 'checked' : '' }}
                                           class="w-4 h-4 rounded text-orange-600 focus:ring-orange-500 bg-zinc-950 border-zinc-700">
                                    <span class="text-sm">{{ $platformCase->icon() }}</span>
                                    <div class="leading-tight">
                                        <span class="text-xs font-bold text-white block">{{ ucfirst($platformCase->value) }}</span>
                                        @if ($isConnected)
                                            <span class="text-[9px] text-emerald-400 font-extrabold uppercase">{{ __('Conectado') }}</span>
                                        @else
                                            <span class="text-[9px] text-zinc-500">{{ __('Não Conectado') }}</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Agendamento Opcional -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-[11px] font-bold text-zinc-300 uppercase tracking-wider mb-1.5">
                                {{ __('Agendar Postagem (Opcional):') }}
                            </label>
                            <input type="datetime-local" name="scheduled_at" 
                                   class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-white text-xs font-semibold focus:outline-none focus:border-orange-500 transition">
                            <span class="text-[10px] text-zinc-500 mt-1 block">
                                {{ __('Deixe em branco para disparar imediatamente agora.') }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-zinc-300 uppercase tracking-wider mb-1.5">
                                {{ __('URL do Vídeo a Enviar:') }}
                            </label>
                            <input type="url" name="video_url" value="{{ $video->video_url }}" required
                                   class="w-full px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs font-semibold focus:outline-none focus:border-orange-500 transition">
                            <span class="text-[10px] text-zinc-500 mt-1 block">
                                {{ __('Link direto do arquivo ou YouTube Shorts cadastrado na produção.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Aviso de Metadados Injetados -->
                    <div class="p-3 rounded-xl bg-zinc-900/80 border border-zinc-800 text-[11px] text-zinc-300 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span>✨</span>
                            <span>{{ __('A Sofi injetará automaticamente o título (:title), a legenda completa e as hashtags calibradas.', ['title' => Str::limit($video->sofi_seo_title ?: $video->title, 45)]) }}</span>
                        </div>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold shadow-lg shadow-orange-600/30 transition shrink-0">
                            🚀 {{ __('Disparar Postagem Agora') }}
                        </button>
                    </div>
                </form>
            </div>
        @endcan
    </div>

</div>
@endsection
