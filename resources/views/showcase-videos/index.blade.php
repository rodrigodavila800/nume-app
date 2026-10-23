@extends('layouts.app')

@section('title', __('NUME | Portfólio Público'))
@section('breadcrumb', __('Portfólio'))

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Portfólio da Produtora') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Vídeos e Shorts demonstrativos exibidos publicamente na vitrine da NUME para atração de novos clientes.') }}</p>
        </div>

        @can('create', App\Models\ShowcaseVideo::class)
            <a href="{{ route('showcase-videos.create') }}"
               class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>{{ __('Novo Exemplo') }}</span>
            </a>
        @endcan
    </div>

    <!-- Navegação de Abas do Catálogo & Cadastros -->
    <x-catalog-tabs active="showcase" />

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Abas de Navegação / Filtros de Orientação -->
    <div class="flex items-center gap-2 border-b border-zinc-800/80 pb-3 overflow-x-auto no-scrollbar">
        <a href="{{ route('showcase-videos.index', ['format' => 'vertical']) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold border transition-all duration-200 shrink-0 {{ $currentFormat === 'vertical' ? 'bg-orange-600 text-white border-orange-500 shadow-lg shadow-orange-950/40' : 'bg-zinc-900/90 text-zinc-400 border-zinc-800 hover:text-white hover:bg-zinc-800' }}">
            <span>📱</span>
            <span>{{ __('Verticais (9:16)') }}</span>
            <span class="px-2 py-0.5 text-[10px] font-black rounded-full {{ $currentFormat === 'vertical' ? 'bg-white/20 text-white' : 'bg-zinc-800 text-zinc-400' }}">
                {{ $verticalCount }}
            </span>
        </a>

        <a href="{{ route('showcase-videos.index', ['format' => 'horizontal']) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold border transition-all duration-200 shrink-0 {{ $currentFormat === 'horizontal' ? 'bg-orange-600 text-white border-orange-500 shadow-lg shadow-orange-950/40' : 'bg-zinc-900/90 text-zinc-400 border-zinc-800 hover:text-white hover:bg-zinc-800' }}">
            <span>🎬</span>
            <span>{{ __('Horizontais (16:9)') }}</span>
            <span class="px-2 py-0.5 text-[10px] font-black rounded-full {{ $currentFormat === 'horizontal' ? 'bg-white/20 text-white' : 'bg-zinc-800 text-zinc-400' }}">
                {{ $horizontalCount }}
            </span>
        </a>

        <a href="{{ route('showcase-videos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold border transition-all duration-200 shrink-0 {{ empty($currentFormat) ? 'bg-orange-600 text-white border-orange-500 shadow-lg shadow-orange-950/40' : 'bg-zinc-900/90 text-zinc-400 border-zinc-800 hover:text-white hover:bg-zinc-800' }}">
            <span>🌐</span>
            <span>{{ __('Todos os Vídeos') }}</span>
            <span class="px-2 py-0.5 text-[10px] font-black rounded-full {{ empty($currentFormat) ? 'bg-white/20 text-white' : 'bg-zinc-800 text-zinc-400' }}">
                {{ $totalCount }}
            </span>
        </a>
    </div>

    <!-- Grid de Vídeos -->
    <div class="grid {{ $currentFormat === 'vertical' ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6' }} items-start">
        @forelse ($videos as $video)
            @php
                $thumb = $video->effective_thumbnail_url;
                $isVertical = $video->is_vertical;
            @endphp
            <article class="rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 overflow-hidden flex flex-col justify-between transition-all hover:shadow-xl hover:shadow-black/50 group">
                
                <!-- Player / Thumbnail Container -->
                <div class="relative w-full {{ $isVertical ? 'aspect-[9/16]' : 'aspect-video' }} bg-zinc-950 overflow-hidden">
                    @if ($video->video_type === 'youtube' || $video->video_type === 'vimeo')
                        <div id="player-container-{{ $video->id }}" class="w-full h-full relative">
                            <!-- Thumbnail com Play Button Overlay -->
                            <div id="thumb-cover-{{ $video->id }}" class="w-full h-full relative cursor-pointer group/thumb" onclick="playShowcaseInline({{ $video->id }}, '{{ $video->embed_url }}')">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="{{ $video->title }}" class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform duration-300">
                                @else
                                    <div class="w-full h-full bg-zinc-950 flex flex-col items-center justify-center text-zinc-600">
                                        <span class="text-3xl">{{ $isVertical ? '📱' : '🎬' }}</span>
                                        <span class="text-[11px] mt-1">{{ __('Carregar Vídeo') }}</span>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-black/40 group-hover/thumb:bg-black/20 flex items-center justify-center transition">
                                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-orange-600/90 text-white flex items-center justify-center text-lg sm:text-xl pl-1 shadow-lg shadow-orange-950/60 group-hover/thumb:scale-110 transition-transform">
                                        ▶
                                    </div>
                                </div>
                                @if($isVertical)
                                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-orange-600/95 text-white shadow flex items-center gap-1">
                                        <span>📱</span>
                                        <span>9:16</span>
                                    </span>
                                @else
                                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-zinc-900/90 text-zinc-300 border border-zinc-700/60 shadow flex items-center gap-1">
                                        <span>🎬</span>
                                        <span>16:9</span>
                                    </span>
                                @endif
                            </div>
                            <!-- Target Iframe Slot (Carregado sob demanda) -->
                            <div id="iframe-slot-{{ $video->id }}" class="w-full h-full hidden"></div>
                        </div>
                    @elseif ($video->video_url)
                        <video controls class="w-full h-full object-cover bg-black">
                            <source src="{{ $video->video_url }}" type="video/mp4">
                        </video>
                    @else
                        <div class="w-full h-full bg-zinc-950 flex items-center justify-center text-zinc-600 text-xs">
                            {{ __('Sem vídeo') }}
                        </div>
                    @endif
                </div>

                <!-- Info & Actions -->
                <div class="p-4 sm:p-5 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-xs sm:text-sm font-bold text-white group-hover:text-orange-400 transition line-clamp-1" title="{{ $video->title }}">
                            {{ $video->title }}
                        </h2>
                        <span class="text-[9px] sm:text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-lg border shrink-0 {{ $video->is_published ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-zinc-800 text-zinc-400 border-zinc-700' }}">
                            {{ $video->is_published ? __('Público') : __('Rascunho') }}
                        </span>
                    </div>

                    @if ($video->description)
                        <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed">
                            {{ $video->description }}
                        </p>
                    @endif

                    <div class="flex items-center justify-between text-[11px] text-zinc-500 pt-1">
                        <span class="font-medium text-orange-400/90 truncate max-w-[120px]">🏷️ {{ $video->category_name }}</span>
                        <span>{{ __('Ordem:') }} {{ $video->sort_order }}</span>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="p-3 sm:p-4 pt-0 flex items-center gap-2 border-t border-zinc-800/80 mt-2">
                    <button type="button" onclick="openVideoModal('{{ $video->embed_url }}', '{{ addslashes($video->title) }}', {{ $isVertical ? 'true' : 'false' }})"
                            class="flex-1 text-center py-1.5 sm:py-2 px-2 sm:px-3 rounded-xl bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white font-bold text-[11px] sm:text-xs border border-orange-500/20 transition truncate">
                        {{ __('Assistir') }} ↗
                    </button>
                    @can('update', $video)
                        <a href="{{ route('showcase-videos.edit', $video) }}"
                           class="p-1.5 sm:p-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition" title="Editar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                    @endcan
                    @can('delete', $video)
                        <form method="POST" action="{{ route('showcase-videos.destroy', $video) }}" onsubmit="return confirm('Remover este exemplo do portfólio?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 sm:p-2 rounded-xl bg-zinc-800 hover:bg-rose-950 hover:text-rose-400 text-zinc-400 transition" title="Remover">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    @endcan
                </div>
            </article>
        @empty
            <div class="col-span-full p-12 rounded-2xl bg-zinc-900 border border-zinc-800 text-center space-y-3">
                <p class="text-zinc-400 text-xs">
                    @if($currentFormat === 'vertical')
                        {{ __('Nenhum vídeo vertical (9:16) encontrado no portfólio.') }}
                    @elseif($currentFormat === 'horizontal')
                        {{ __('Nenhum vídeo horizontal (16:9) encontrado no portfólio.') }}
                    @else
                        {{ __('Seu portfólio ainda está vazio. Adicione o primeiro exemplo para exibir na vitrine pública.') }}
                    @endif
                </p>
                <a href="{{ route('showcase-videos.create') }}" class="inline-block text-xs font-bold text-orange-500 hover:text-orange-400">
                    + {{ __('Adicionar Vídeo ao Portfólio') }}
                </a>
            </div>
        @endforelse
    </div>

    <div class="pt-2">
        {{ $videos->links() }}
    </div>
</div>

<!-- Modal Universal de Reprodução em Tela Cheia / Vertical -->
<div id="video-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="relative w-full max-w-4xl bg-zinc-900 rounded-3xl border border-zinc-800 overflow-hidden shadow-2xl space-y-3 p-4 sm:p-6">
        <div class="flex items-center justify-between pb-2 border-b border-zinc-800">
            <h3 id="modal-video-title" class="text-base font-extrabold text-white truncate max-w-lg"></h3>
            <button type="button" onclick="closeVideoModal()" class="p-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <!-- Modal Frame Container -->
        <div id="modal-frame-wrapper" class="w-full flex items-center justify-center bg-black rounded-2xl overflow-hidden min-h-[450px]">
            <iframe id="modal-iframe" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
function playShowcaseInline(id, embedUrl) {
    const cover = document.getElementById('thumb-cover-' + id);
    const slot = document.getElementById('iframe-slot-' + id);
    if (cover && slot) {
        cover.classList.add('hidden');
        slot.classList.remove('hidden');
        // Adiciona autoplay no iframe sob demanda
        const autoplayUrl = embedUrl.includes('?') ? (embedUrl + '&autoplay=1') : (embedUrl + '?autoplay=1');
        slot.innerHTML = `<iframe src="${autoplayUrl}" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
    }
}

function openVideoModal(embedUrl, title, isVertical) {
    const modal = document.getElementById('video-modal');
    const iframe = document.getElementById('modal-iframe');
    const titleEl = document.getElementById('modal-video-title');
    const wrapper = document.getElementById('modal-frame-wrapper');

    if (modal && iframe) {
        titleEl.textContent = title;
        const autoplayUrl = embedUrl.includes('?') ? (embedUrl + '&autoplay=1') : (embedUrl + '?autoplay=1');
        iframe.src = autoplayUrl;

        if (isVertical) {
            wrapper.className = "w-full max-w-[340px] aspect-[9/16] mx-auto flex items-center justify-center bg-black rounded-2xl overflow-hidden shadow-2xl border border-zinc-800";
        } else {
            wrapper.className = "w-full aspect-video flex items-center justify-center bg-black rounded-2xl overflow-hidden shadow-2xl border border-zinc-800";
        }

        modal.classList.remove('hidden');
    }
}

function closeVideoModal() {
    const modal = document.getElementById('video-modal');
    const iframe = document.getElementById('modal-iframe');
    if (modal && iframe) {
        iframe.src = '';
        modal.classList.add('hidden');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVideoModal();
    }
});
</script>
@endsection
