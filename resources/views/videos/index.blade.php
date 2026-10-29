@extends('layouts.app')

@section('title', 'NUME | Vídeos de ' . $production->title)
@section('breadcrumb', __('Vídeos da Produção'))

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('productions.show', $production) }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5 mb-2">
                ← {{ __('Voltar para') }} {{ $production->title }}
            </a>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ __('Vídeos e Entregáveis') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Peças finalizadas vinculadas à produção:') }} <strong class="text-orange-400">{{ $production->title }}</strong></p>
        </div>

        <div>
            @can('create', \App\Models\Video::class)
                <a href="{{ route('videos.create', $production) }}" 
                   class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all">
                    <span>+ {{ __('Cadastrar Novo Vídeo') }}</span>
                </a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($videos->isEmpty())
        <div class="p-12 rounded-2xl bg-zinc-900 border border-zinc-800 text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-orange-600/20 border border-orange-500/30 text-orange-400 flex items-center justify-center text-2xl font-bold mx-auto">
                
            </div>
            <p class="text-sm font-bold text-white">{{ __('Nenhum vídeo cadastrado nesta produção ainda.') }}</p>
            <p class="text-xs text-zinc-500 max-w-md mx-auto">{{ __('Assim que o roteiro for aprovado e as peças forem gravadas, cadastre os links do YouTube, Shorts ou MP4 aqui.') }}</p>
            @can('create', \App\Models\Video::class)
                <div class="pt-2">
                    <a href="{{ route('videos.create', $production) }}" class="inline-block text-xs font-bold text-orange-500 hover:text-orange-400">
                        + {{ __('Cadastrar Primeiro Vídeo') }}
                    </a>
                </div>
            @endcan
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($videos as $video)
                @php
                    $thumb = $video->effective_thumbnail_url;
                    $statusClasses = match($video->status) {
                        'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'ready' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'processing' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                    };
                @endphp
                <article class="rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 overflow-hidden flex flex-col justify-between transition-all hover:shadow-xl hover:shadow-black/40 group">
                    <div>
                        @if ($thumb)
                            <div class="relative h-44 w-full overflow-hidden bg-zinc-950">
                                <img src="{{ $thumb }}" alt="{{ $video->title }}" class="h-44 w-full object-cover group-hover:scale-105 transition-transform duration-300">
                                @if($video->video_type === 'youtube')
                                    <span class="absolute top-2 right-2 px-2 py-0.5 rounded text-[10px] font-extrabold bg-red-600 text-white shadow">YouTube</span>
                                @elseif($video->video_type === 'vimeo')
                                    <span class="absolute top-2 right-2 px-2 py-0.5 rounded text-[10px] font-extrabold bg-cyan-600 text-white shadow">Vimeo</span>
                                @endif
                            </div>
                        @else
                            <div class="h-44 w-full bg-zinc-950 flex items-center justify-center text-orange-500 text-3xl font-extrabold border-b border-zinc-800">
                                ▶
                            </div>
                        @endif

                        <div class="p-5 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="text-base font-bold text-white group-hover:text-orange-400 transition">{{ $video->title }}</h2>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-lg border {{ $statusClasses }}">
                                    {{ ucfirst(str_replace('_', ' ', $video->status)) }}
                                </span>
                            </div>

                            <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed">
                                {{ $video->description ?: __('Sem descrição adicional.') }}
                            </p>

                            <div class="flex items-center gap-3 text-[11px] text-zinc-500 pt-1">
                                @if ($video->duration_seconds)
                                    <span>{{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ $video->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0 flex items-center gap-2 border-t border-zinc-800/80 mt-4">
                        <a href="{{ route('videos.show', ['production' => $production, 'video' => $video]) }}"
                           class="flex-1 text-center py-2 px-3 rounded-xl bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white font-bold text-xs border border-orange-500/20 transition">
                            {{ __('Ver Player / Detalhes') }} →
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $videos->links() }}
        </div>
    @endif

</div>
@endsection
