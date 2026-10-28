@extends('layouts.portal')

@section('title', $production->title . ' | Detalhes da Produção NUME')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-8">
    
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-orange-600 transition">
            ← {{ __('Voltar para a Esteira de Produção') }}
        </a>
        <span class="text-xs font-mono font-semibold text-zinc-500 bg-zinc-100 px-2.5 py-1 rounded-lg border border-zinc-200">REF: {{ strtoupper(substr($production->uuid, 0, 8)) }}</span>
    </div>

    <!-- Card Principal do Projeto -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-orange-600">{{ __('Produção Ativa') }}</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 mt-1">
                    {{ $production->title }}
                </h1>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                    @class([
                        'bg-zinc-100 text-zinc-700' => in_array($production->status, ['draft', 'planejamento']),
                        'bg-orange-100 text-orange-800 border border-orange-200' => in_array($production->status, ['in_progress', 'roteirizacao', 'gravacao']),
                        'bg-emerald-100 text-emerald-800 border border-emerald-200' => in_array($production->status, ['completed', 'approved', 'entregue']),
                    ])">
                    Status: {{ $production->status }}
                </span>
            </div>
        </div>

        @if($production->description)
            <div class="bg-zinc-50 rounded-xl p-4 border border-zinc-100 text-sm text-zinc-700 leading-relaxed">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-600 mb-1">{{ __('Briefing & Objetivo:') }}</span>
                {{ $production->description }}
            </div>
        @endif

        @if($production->packPurchase)
            <div class="pt-3 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-zinc-500 font-medium">{{ __('Pacote de Origem:') }}</span>
                    <span class="font-bold text-zinc-800 bg-zinc-100 px-2 py-0.5 rounded">{{ $production->packPurchase->pack?->name ?? __('Pacote Contratado') }}</span>
                </div>
                @if(!empty($production->packPurchase->addons_purchased))
                    <div class="flex items-center flex-wrap gap-1.5">
                        <span class="text-zinc-500 font-medium mr-1">{{ __('Add-ons Ativos:') }}</span>
                        @foreach($production->packPurchase->addons_purchased as $addon)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-orange-50 text-orange-700 border border-orange-200">
                                + {{ $addon }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Seção de Roteiros -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">{{ __('Roteiros & Narrativas de Vendas') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Revise os ganchos, corpo e CTAs estruturados para capturar a atenção nos primeiros 3 segundos.') }}</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($roteiros as $roteiro)
                <a href="{{ route('portal.roteiros.show', $roteiro) }}" 
                   class="block bg-white rounded-xl p-5 border border-zinc-200/80 shadow-sm hover:border-orange-300 hover:shadow-md transition group">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center font-bold text-sm border border-orange-100">
                                
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-zinc-900 group-hover:text-orange-600 transition">
                                    {{ $roteiro->title ?? __('Roteiro Estratégico') }}
                                </h3>
                                <p class="text-xs text-zinc-500 mt-0.5 font-mono">
                                    REF: {{ strtoupper(substr($roteiro->uuid, 0, 8)) }} · {{ $roteiro->updated_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                                @class([
                                    'bg-zinc-100 text-zinc-700' => $roteiro->status === 'draft',
                                    'bg-amber-100 text-amber-800 border border-amber-200 animate-pulse' => $roteiro->status === 'submitted',
                                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => in_array($roteiro->status, ['approved', 'in_production']),
                                    'bg-rose-100 text-rose-800 border border-rose-200' => $roteiro->status === 'rejected',
                                ])">
                                @if($roteiro->status === 'submitted')
                                    Aguarda Sua Aprovação
                                @elseif($roteiro->status === 'approved')
                                    Roteiro Aprovado
                                @elseif($roteiro->status === 'in_production')
                                    Em Gravação / Edição
                                @elseif($roteiro->status === 'rejected')
                                    Ajustes Solicitados
                                @else
                                    {{ $roteiro->status }}
                                @endif
                            </span>
                            <span class="text-xs font-bold text-orange-600 group-hover:translate-x-1 transition">→</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-xl p-8 text-center border border-dashed border-zinc-300">
                    <p class="text-sm font-semibold text-zinc-700">Roteiro em fase de redação estratégica</p>
                    <p class="text-xs text-zinc-500 mt-1">Nossa equipe está desenvolvendo os ganchos e a estrutura ideal para seu público.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Seção de Vídeos Entregues -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">Vídeos Prontos & Edições Finais</h2>
                <p class="text-xs text-zinc-500">Acesse as peças produzidas em alta resolução com padrão de cortes dinâmicos.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            @forelse ($videos as $video)
                <div class="bg-white rounded-2xl p-6 border border-zinc-200/80 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 pb-4">
                        <div class="space-y-1">
                            <h3 class="text-base font-bold text-zinc-900">{{ $video->title }}</h3>
                            <div class="flex items-center gap-2 text-xs text-zinc-500">
                                @if($video->duration_seconds)
                                    <span>{{ gmdate("i:s", $video->duration_seconds) }}</span>
                                    <span>·</span>
                                @endif
                                <span>Entregue em: {{ $video->published_at?->format('d/m/Y') ?? 'Disponível' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center flex-wrap gap-2">
                            @if($video->latestPublication)
                                <a href="{{ route('portal.social.index') }}" 
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider
                                    @class([
                                        'bg-amber-50 text-amber-700 border border-amber-200 animate-pulse' => $video->latestPublication->isPendingOrProcessing(),
                                        'bg-emerald-50 text-emerald-700 border border-emerald-200' => $video->latestPublication->isSuccess(),
                                        'bg-rose-50 text-rose-700 border border-rose-200' => $video->latestPublication->status === 'falhou',
                                    ])">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $video->latestPublication->isSuccess() ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    <span>Distribuído ({{ ucfirst($video->latestPublication->status) }})</span>
                                            @endif

                            @php
                                $isLocked = $video->isAssetLocked();
                                $packPurchase = $production->packPurchase;
                                $balanceDue = $packPurchase ? (float) $packPurchase->balance_amount : 0.00;
                                $currencySymbol = $packPurchase?->pack?->currency ?? 'EUR';
                            @endphp

                            @if($isLocked)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                                    {{ __('Custódia Ativa (Saldo Pendente)') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    {{ $video->status === 'published' ? 'Entregue / Publicado' : ($video->status === 'ready' ? 'Aprovado' : $video->status) }}
                                </span>
                            @endif

                            @if($video->review_url && $video->status !== 'published')
                                <a href="{{ $video->review_url }}" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 hover:bg-sky-100 transition">
                                    ▶ {{ __('Assistir para revisar (Drive)') }} ↗
                                </a>
                            @endif
                            @if($video->delivery_url && ! $isLocked)
                                <a href="{{ $video->delivery_url }}" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                                    {{ __('Arquivo final (Drive)') }} ↗
                                </a>
                            @endif

                            @if($isLocked)
                                <button type="button" onclick="openApproveModal({{ $video->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-extrabold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-sm transition active:scale-95">
                                    <span>{{ __('Aprovar Corte & Liberar (:amount :curr)', ['amount' => number_format($balanceDue, 2, ',', '.'), 'curr' => $currencySymbol]) }}</span>
                                </button>
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-zinc-100 text-zinc-400 border border-zinc-200 cursor-not-allowed" title="{{ __('Liberação após aprovação do corte final') }}">
                                    <span>{{ __('Download Bloqueado') }}</span>
                                </span>
                            @elseif($video->hasPlayableSource())
                                <button type="button" onclick="openDistributeModal({{ $video->id }})" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-xs transition active:scale-95">
                                    <span>{{ __('Publicar nas Redes') }}</span>
                                </button>
                                @if($video->hasSourceFile())
                                    @foreach($composerPlatforms as $composerPlatform)
                                        <a href="{{ route('portal.videos.'.$composerPlatform, $video) }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-zinc-950 hover:bg-zinc-800 shadow-xs transition active:scale-95">
                                            <span>{{ __('Publicar no :rede', ['rede' => \App\Enums\PlataformaAlvo::from($composerPlatform)->label()]) }}</span>
                                        </a>
                                    @endforeach
                                @endif
                                <a href="{{ $video->downloadUrl() }}" target="_blank" rel="noopener" 
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-zinc-950 hover:bg-orange-600 text-white transition">
                                    <span>Download</span> ↗
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($video->description)
                        <p class="text-xs text-zinc-600 leading-relaxed">{{ $video->description }}</p>
                    @endif

                    <!-- Video Player Embutido (YouTube Shorts, YouTube, Vimeo, MP4) -->
                    @if($video->hasPlayableSource())
                        <div class="relative w-full rounded-xl overflow-hidden bg-zinc-950 aspect-video flex items-center justify-center border border-zinc-200 shadow-inner">
                            @if($video->hasSourceFile())
                                <video controls preload="metadata" controlsList="nodownload" class="w-full h-full object-contain bg-black">
                                    <source src="{{ $video->playbackUrl() }}" type="{{ $video->source_mime ?: 'video/mp4' }}">
                                    {{ __('Seu navegador não suporta a tag de vídeo.') }}
                                </video>
                            @elseif($video->video_type === 'youtube' || $video->video_type === 'vimeo')
                                <iframe 
                                    src="{{ $video->embed_url }}" 
                                    class="w-full h-full border-0" 
                                    title="{{ $video->title }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                    allowfullscreen>
                                </iframe>
                            @else
                                <video controls class="w-full h-full object-contain bg-black">
                                    <source src="{{ $video->video_url }}" type="video/mp4">
                                    {{ __('Seu navegador não suporta a tag de vídeo.') }}
                                </video>
                            @endif

                            @if($isLocked)
                                <!-- Marca d'água de proteção de ativo (Asset Escrow) -->
                                <div class="absolute inset-0 pointer-events-none flex items-center justify-center bg-black/25">
                                    <div class="px-4 py-2 rounded-xl bg-zinc-950/80 border border-orange-500/40 text-orange-400 font-extrabold text-xs tracking-wider uppercase backdrop-blur-xs shadow-xl">
                                        {{ __('Prévia de Aprovação — NUME Custódia') }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Sofi AI SEO Pack (Estratégia de Postagem e Retenção Orgânica) -->
                    <div class="bg-gradient-to-br from-orange-50/50 via-amber-50/30 to-zinc-50 rounded-xl p-5 border border-orange-200/60 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-orange-950">
                                        {{ __('Sofi SEO Pack: Estratégia de Postagem Recomendada') }}
                                    </h4>
                                    <p class="text-[11px] text-zinc-600">
                                        {{ __('Calibrado especificamente com o perfil e posicionamento da sua marca para maximizar alcance e conversão.') }}
                                    </p>
                                </div>
                            </div>
                            <button type="button" onclick="copySeoPack({{ $video->id }})" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <span id="copy-btn-{{ $video->id }}">{{ __('Copiar Tudo') }}</span>
                            </button>
                        </div>

                        <div class="space-y-3 text-xs">
                            <!-- Título Sugerido -->
                            <div class="bg-white rounded-lg p-3 border border-orange-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">
                                    {{ __('Título Recomendado (YouTube / Reels / TikTok):') }}
                                </span>
                                <p class="font-bold text-zinc-900" id="seo-title-{{ $video->id }}">
                                    {{ $video->sofi_seo_title ?: ($video->title . ' | ' . ($production->cliente?->company_name ?? '')) }}
                                </p>
                            </div>

                            <!-- Legenda / Descrição -->
                            <div class="bg-white rounded-lg p-3 border border-orange-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">
                                    {{ __('Legenda / Descrição Estruturada:') }}
                                </span>
                                <pre class="font-sans text-zinc-700 whitespace-pre-wrap text-xs leading-relaxed" id="seo-desc-{{ $video->id }}">{{ $video->sofi_seo_description ?: 'Vídeo estratégico focado em autoridade e retenção de clientes.' }}</pre>
                            </div>

                            <!-- Hashtags & Palavras-chave -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 mr-1">Hashtags:</span>
                                @if(!empty($video->sofi_hashtags))
                                    @foreach($video->sofi_hashtags as $tag)
                                        <span class="px-2 py-0.5 rounded-md bg-white border border-orange-200 text-orange-800 font-semibold text-[11px]">
                                            {{ $tag }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-orange-200 text-orange-800 font-semibold text-[11px]">#VideoMarketing</span>
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-orange-200 text-orange-800 font-semibold text-[11px]">#AltaRetencao</span>
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-orange-200 text-orange-800 font-semibold text-[11px]">#NUME</span>
                                @endif
                            </div>

                            @if($video->sofi_seo_keywords)
                                <p class="text-[11px] text-zinc-500">
                                    <strong>Palavras-chave Google/Busca:</strong> {{ $video->sofi_seo_keywords }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white rounded-xl p-8 text-center border border-dashed border-zinc-300">
                    <p class="text-sm font-semibold text-zinc-700">Vídeos em esteira de gravação e pós-produção</p>
                    <p class="text-xs text-zinc-500 mt-1">Assim que os cortes finais forem exportados, os links e o Sofi SEO Pack aparecerão disponíveis aqui.</p>
                </div>
            @endforelse
        </div>

        <script>
        function copySeoPack(videoId) {
            const title = document.getElementById('seo-title-' + videoId)?.innerText || '';
            const desc = document.getElementById('seo-desc-' + videoId)?.innerText || '';
            const fullText = `${title}\n\n${desc}`;

            navigator.clipboard.writeText(fullText).then(() => {
                const btn = document.getElementById('copy-btn-' + videoId);
                if (btn) {
                    const original = btn.textContent;
                    btn.textContent = 'Copiado! ✓';
                    setTimeout(() => btn.textContent = original, 2000);
                }
            });
        }

        function openDistributeModal(videoId) {
            const modal = document.getElementById('distribute-modal-' + videoId);
            if (modal) modal.classList.remove('hidden');
        }

        function closeDistributeModal(videoId) {
            const modal = document.getElementById('distribute-modal-' + videoId);
            if (modal) modal.classList.add('hidden');
        }

        function openApproveModal(videoId) {
            const modal = document.getElementById('approve-modal-' + videoId);
            if (modal) modal.classList.remove('hidden');
        }

        function closeApproveModal(videoId) {
            const modal = document.getElementById('approve-modal-' + videoId);
            if (modal) modal.classList.add('hidden');
        }
        </script>
    </div>
</div>

<!-- Modais de Distribuição Multiplataforma para cada Vídeo -->
@php
    $portalCliente = auth('cliente')->user();
    // Redes conectadas pelo Upload-Post ou direto pela NUME. As de tela própria (TikTok direto)
    // não entram no modal: têm botão "Publicar no TikTok".
    $connectedPlatforms = $portalCliente?->connectedPlatforms() ?? [];
    $hasSocialConnection = $connectedPlatforms !== [];
    $modalPlatforms = array_values(array_diff($connectedPlatforms, $composerPlatforms));
@endphp

@foreach($videos as $video)
    @if($video->hasPlayableSource())
        <div id="distribute-modal-{{ $video->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 border border-zinc-200 shadow-xl space-y-5 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <h3 class="text-base font-extrabold text-zinc-900">
                            {{ __('Publicar Vídeo nas Redes') }}
                        </h3>
                    </div>
                    <button type="button" onclick="closeDistributeModal({{ $video->id }})" class="text-zinc-400 hover:text-zinc-700 text-sm font-bold">
                        ✕
                    </button>
                </div>

                @if(! $hasSocialConnection)
                    <div class="space-y-4 text-center py-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-2xl mx-auto border border-amber-100 shadow-xs">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-zinc-800">{{ __('Nenhuma Rede Social Conectada') }}</h4>
                            <p class="text-xs text-zinc-500 max-w-xs mx-auto">
                                {{ __('Para publicar seus vídeos com 1 clique direto no Instagram, TikTok ou YouTube, conecte suas contas com segurança.') }}
                            </p>
                        </div>
                        <a href="{{ route('portal.social.index') }}" 
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition">
                            <span>{{ __('Conectar Minhas Redes Sociais Agora') }}</span> →
                        </a>
                    </div>
                @elseif(! $portalCliente->canDistributeSocial())
                    <div class="space-y-4 text-center py-4">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-2xl mx-auto border border-purple-100 shadow-xs">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-zinc-800">{{ __('Limite de Publicações Atingido') }}</h4>
                            <p class="text-xs text-zinc-500 max-w-xs mx-auto">
                                {{ __('Você atingiu a cota de envios do seu pacote. Ative o Add-on de Distribuição Automática para envios ilimitados em alta retenção.') }}
                            </p>
                        </div>
                        <a href="{{ route('portal.packs.index') }}" 
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-purple-600 to-orange-500 hover:from-purple-500 hover:to-orange-400 shadow-sm transition">
                            {{ __('Ativar Add-on de Distribuição') }} →
                        </a>
                    </div>
                @else
                    <form action="{{ route('portal.videos.distribute', $video) }}" method="POST" class="space-y-4">
                        @csrf
                        @unless($video->hasSourceFile())
                            <input type="hidden" name="video_url" value="{{ $video->video_url }}">
                        @endunless

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                                {{ __('Título / Legenda do Post:') }}
                            </label>
                            <input type="text" name="titulo" 
                                value="{{ $video->sofi_seo_title ?: $video->title }}" 
                                required maxlength="150"
                                class="w-full text-xs font-semibold px-3 py-2 rounded-xl border border-zinc-200 focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1.5">
                                {{ __('Selecione os canais de destino:') }}
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($modalPlatforms as $plat)
                                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-zinc-200 hover:border-orange-300 transition cursor-pointer text-xs font-bold text-zinc-800 bg-zinc-50/50">
                                        <input type="checkbox" name="plataformas[]" value="{{ strtolower($plat) }}" checked 
                                            class="rounded text-orange-600 focus:ring-orange-500 border-zinc-300">
                                        <span class="uppercase tracking-wider text-[11px]">{{ $plat }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500">
                                    {{ __('Agendar Publicação (Opcional):') }}
                                </label>
                                <span class="text-[10px] text-zinc-400 font-medium">{{ __('Deixe em branco para envio imediato') }}</span>
                            </div>
                            <input type="datetime-local" name="scheduled_at" 
                                min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                                class="w-full text-xs font-semibold px-3 py-2 rounded-xl border border-zinc-200 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-zinc-800 bg-white">
                        </div>

                        <div class="bg-orange-50/60 rounded-xl p-3 border border-orange-100 text-[11px] text-zinc-600 space-y-1">
                            <span class="font-bold text-orange-900 block">Otimização Sofi:</span>
                            <p>{{ __('O arquivo original será enviado sem perdas para os algoritmos parceiros, ativando as tags de SEO pré-configuradas.') }}</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                            <button type="button" onclick="closeDistributeModal({{ $video->id }})" 
                                class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition">
                                {{ __('Cancelar') }}
                            </button>
                            <button type="submit" 
                                class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition active:scale-95">
                                <span>{{ __('Confirmar Publicação ou Agendamento') }}</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endforeach

<!-- Modais de Aprovação & Liquidação de Saldo (Modelo 50/50 Escrow) -->
@foreach($videos as $video)
    @if($video->isAssetLocked())
        @php
            $packPurchase = $production->packPurchase;
            $balanceDue = $packPurchase ? (float) $packPurchase->balance_amount : 0.00;
            $currencySymbol = $packPurchase?->pack?->currency ?? 'EUR';
        @endphp
        <div id="approve-modal-{{ $video->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 border border-zinc-200 shadow-2xl space-y-5 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="text-base font-extrabold text-zinc-900">
                            {{ __('Aprovar Corte Final & Liberar Ativos') }}
                        </h3>
                    </div>
                    <button type="button" onclick="closeApproveModal({{ $video->id }})" class="text-zinc-400 hover:text-zinc-700 text-sm font-bold">
                        ✕
                    </button>
                </div>

                <div class="space-y-3">
                    <p class="text-xs text-zinc-600 leading-relaxed">
                        {{ __('Você está prestes a aprovar a versão final do vídeo :title. Esta ação autoriza a entrega definitiva e remove a marca d\'água de custódia.', ['title' => $video->title]) }}
                    </p>

                    <div class="p-4 rounded-xl bg-orange-50 border border-orange-200 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-orange-900">{{ __('Saldo Final a Liquidar (50%):') }}</span>
                            <span class="text-base font-black text-orange-600">
                                {{ number_format($balanceDue, 2, ',', '.') }} {{ $currencySymbol }}
                            </span>
                        </div>
                        <p class="text-[11px] text-orange-700">
                            {{ __('O valor será debitado no seu método de pagamento cadastrado. Após a liquidação, o download em 4K e o disparo direto para suas redes sociais serão liberados instantaneamente.') }}
                        </p>
                    </div>

                    <div class="space-y-1.5 text-xs text-zinc-600">
                        <p class="font-bold text-zinc-800">Ao confirmar a aprovação você recebe:</p>
                        <div class="flex items-center gap-2 text-[11px] text-zinc-700">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Arquivo original limpo (sem marca d'água de prévia)</span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-zinc-700">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Disparo automático e agendamento para YouTube, Instagram e TikTok</span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-zinc-700">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Estratégia completa de SEO e Copywriting da Sofi</span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('portal.videos.approve-and-release', $video) }}" method="POST" class="pt-3 border-t border-zinc-100 flex items-center justify-end gap-2">
                    @csrf
                    <button type="button" onclick="closeApproveModal({{ $video->id }})" 
                        class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition">
                        {{ __('Revisar Mais Tarde') }}
                    </button>
                    <button type="submit" 
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-md shadow-emerald-600/20 transition active:scale-95">
                        <span>{{ __('Confirmar Aprovação & Liquidar Saldo (:amount :curr)', ['amount' => number_format($balanceDue, 2, ',', '.'), 'curr' => $currencySymbol]) }}</span>
                    </button>
                </form>
            </div>
        </div>
    @endif
@endforeach

<!-- Gancho de Retenção Sofi: Conversão para Plano Recorrente -->
<div class="mt-8 p-6 rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-orange-950/40 border border-orange-500/30 text-white space-y-4 shadow-xl">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <svg class="w-7 h-7 text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <div>
                <h3 class="text-sm sm:text-base font-extrabold text-white">
                    {{ __('Mantenha a Constância da sua Marca com a Sofi') }}
                </h3>
                <p class="text-xs text-zinc-400 mt-1 max-w-xl">
                    {{ __('Gostou da entrega deste vídeo? Para consolidar a autoridade da sua marca nos algoritmos, garanta 4 vídeos estratégicos por mês com roteiro Sofi e 20% de economia assinando nosso Plano Recorrente.') }}
                </p>
            </div>
        </div>
        <a href="{{ route('portal.plans.index') }}" 
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-lg shadow-orange-600/30 transition shrink-0">
            <span>{{ __('Conhecer Planos Mensais') }}</span> →
        </a>
    </div>
</div>
@endsection
