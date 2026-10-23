@extends('layouts.app')

@section('title', 'NUME | ' . $production->title)
@section('breadcrumb', __('Detalhes da Produção'))

@section('content')
<div class="space-y-6">

    <!-- Top: Header & Badges -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ $production->title }}</h1>
                @php
                    $statusClasses = match($production->status) {
                        'approved' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'in_production' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        'pending_review' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                        'published' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                    };
                    $statusLabels = [
                        'draft' => __('Rascunho'),
                        'pending_review' => __('Aguardando Revisão'),
                        'approved' => __('Aprovado'),
                        'in_production' => __('Em Produção'),
                        'ready' => __('Pronto'),
                        'published' => __('Publicado'),
                    ];
                @endphp
                <span class="px-3 py-1 text-xs font-extrabold uppercase rounded-lg border {{ $statusClasses }}">
                    {{ $statusLabels[$production->status] ?? ucfirst($production->status) }}
                </span>
            </div>
            <p class="text-xs text-zinc-400 max-w-2xl">{{ $production->description ?: __('Sem descrição detalhada cadastrada.') }}</p>
        </div>

        <div class="flex items-center gap-2">
            @can('update', $production)
                <a href="{{ route('productions.edit', $production) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-bold transition">
                    ✏️ {{ __('Editar Produção') }}
                </a>
                @if ($production->isReadyForProduction() && $production->status !== 'approved')
                    <form action="{{ route('productions.mark-approved', $production) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                            ✓ {{ __('Marcar como Aprovada') }}
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

    <!-- Metadata Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Cliente Vinculado') }}</p>
            <p class="text-sm font-bold text-white mt-1">{{ $production->cliente ? $production->cliente->name : __('Interno') }}</p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Criado Por') }}</p>
            <p class="text-sm font-bold text-white mt-1">{{ $production->creator->name }}</p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Data de Entrega Alvo') }}</p>
            <p class="text-sm font-bold text-white mt-1">{{ $production->target_publication_date ? $production->target_publication_date->format('d/m/Y') : __('Não definida') }}</p>
        </div>
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Criada em') }}</p>
            <p class="text-sm font-bold text-white mt-1">{{ $production->created_at->format('d/m/Y') }}</p>
        </div>
    </div>

    @if($production->packPurchase)
        <div class="p-4 rounded-xl bg-zinc-900 border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-zinc-500 font-medium">{{ __('Pacote de Origem:') }}</span>
                <span class="font-bold text-white bg-zinc-800 px-2.5 py-1 rounded-lg border border-zinc-700">
                    📦 {{ $production->packPurchase->pack?->name ?? __('Pacote Contratado') }}
                </span>
            </div>
            @if(!empty($production->packPurchase->addons_purchased))
                <div class="flex items-center flex-wrap gap-1.5">
                    <span class="text-zinc-400 font-bold uppercase tracking-wider text-[10px] mr-1">{{ __('Add-ons Contratados:') }}</span>
                    @foreach($production->packPurchase->addons_purchased as $addon)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-orange-950/40 text-orange-300 border border-orange-800/40">
                            + {{ $addon }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- ROTEIROS SECTION -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-white">{{ __('Roteiros da Produção') }}</h2>
                <p class="text-xs text-zinc-400 mt-0.5">{{ __('Textos estratégicos e estruturas narrativas vinculadas.') }}</p>
            </div>
            <a href="{{ route('roteiros.create', $production) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition">
                + {{ __('Novo Roteiro') }}
            </a>
        </div>

        @if ($roteiros->isEmpty())
            <div class="p-8 rounded-xl bg-zinc-950 border border-zinc-800/80 text-center text-xs text-zinc-500">
                {{ __('Nenhum roteiro cadastrado para esta produção ainda.') }}
            </div>
        @else
            <div class="space-y-3">
                @foreach ($roteiros as $roteiro)
                    <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 flex items-center justify-between gap-4 transition">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-white">{{ __('Roteiro #') }}{{ $roteiro->id }}</span>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-zinc-800 text-zinc-300">
                                    {{ ucfirst(str_replace('_', ' ', $roteiro->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-1">{{ __('Criado por:') }} {{ $roteiro->creator->name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $roteiro->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white text-xs font-bold border border-emerald-500/30 transition"
                               title="{{ __('Enviar link de aprovação sem senha direto no WhatsApp do cliente') }}">
                                <span>📲</span>
                                <span>{{ __('WhatsApp Aprovação') }}</span>
                            </a>
                            <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}"
                               class="px-3 py-1.5 rounded-lg bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white text-xs font-bold border border-orange-500/20 transition">
                                {{ __('Ver Roteiro') }} →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="pt-2">
                {{ $roteiros->links() }}
            </div>
        @endif
    </div>

    <!-- VÍDEOS SECTION -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-white">{{ __('Vídeos e Entregáveis') }}</h2>
                <p class="text-xs text-zinc-400 mt-0.5">{{ __('Arquivos finais, links de entrega e status de publicação.') }}</p>
            </div>
            @if ($production->roteiros->contains('status', 'approved'))
                <a href="{{ route('videos.create', $production) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition">
                    + {{ __('Novo Vídeo') }}
                </a>
            @endif
        </div>

        @if ($videos->isEmpty())
            <div class="p-8 rounded-xl bg-zinc-950 border border-zinc-800/80 text-center text-xs text-zinc-500">
                {{ __('Nenhum vídeo cadastrado ainda.') }}
                @if (!$production->roteiros->contains('status', 'approved'))
                    <span class="block text-zinc-600 mt-1">{{ __('(Necessário ter ao menos 1 roteiro aprovado para cadastrar o vídeo final)') }}</span>
                @endif
            </div>
        @else
            <div class="space-y-3">
                @foreach ($videos as $video)
                    <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 flex items-center justify-between gap-4 transition">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-white">{{ $video->title }}</span>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-zinc-800 text-zinc-300">
                                    {{ ucfirst(str_replace('_', ' ', $video->status)) }}
                                </span>
                            </div>
                            @if ($video->platform)
                                <p class="text-xs text-zinc-500 mt-1">{{ __('Plataforma:') }} {{ ucfirst($video->platform) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $video->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white text-xs font-bold border border-emerald-500/30 transition"
                               title="{{ __('Enviar link de aprovação do vídeo no WhatsApp do cliente') }}">
                                <span>📲</span>
                                <span>{{ __('WhatsApp Vídeo') }}</span>
                            </a>
                            <a href="{{ route('videos.show', ['production' => $production, 'video' => $video]) }}"
                               class="px-3 py-1.5 rounded-lg bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white text-xs font-bold border border-orange-500/20 transition">
                                {{ __('Ver Vídeo') }} →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="pt-2">
                {{ $videos->links() }}
            </div>
        @endif
    </div>

    <div>
        <a href="{{ route('productions.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition">
            ← {{ __('Voltar para todas as produções') }}
        </a>
    </div>
</div>
@endsection
