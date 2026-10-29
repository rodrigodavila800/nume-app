@extends('layouts.app')

@section('title', 'NUME | ' . $production->title)
@section('breadcrumb', __('Detalhes da Produção'))

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h1>{{ $production->title }}</h1>
                @php
                    $statusPill = match($production->status) {
                        'approved', 'published', 'ready' => 'positive',
                        'in_production', 'pending_review' => 'copper',
                        default => '',
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
                <span class="status-pill {{ $statusPill }}">
                    {{ $statusLabels[$production->status] ?? ucfirst($production->status) }}
                </span>
            </div>
            <p>{{ $production->description ?: __('Sem descrição detalhada cadastrada.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @can('update', $production)
                <form action="{{ route('productions.update-status', $production) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <select name="status" onchange="this.form.submit()" aria-label="{{ __('Mudar etapa da produção') }}"
                            class="bg-zinc-900 border border-zinc-800 text-xs font-semibold text-zinc-200 rounded-xl px-3.5 py-2.5 focus:outline-none cursor-pointer">
                        <option value="draft" @selected($production->status === 'draft')>{{ __('Etapa: Rascunho') }}</option>
                        <option value="pending_review" @selected($production->status === 'pending_review')>{{ __('Etapa: Revisão de Roteiro') }}</option>
                        <option value="in_production" @selected($production->status === 'in_production')>{{ __('Etapa: Em Gravação & Edição') }}</option>
                        <option value="ready" @selected($production->status === 'ready')>{{ __('Etapa: Corte Pronto') }}</option>
                        <option value="approved" @selected($production->status === 'approved')>{{ __('Etapa: Aprovado') }}</option>
                        <option value="published" @selected($production->status === 'published')>{{ __('Etapa: Publicado') }}</option>
                    </select>
                </form>

                <a href="{{ route('productions.edit', $production) }}" class="btn text-xs py-2 px-4">
                    {{ __('Editar Produção') }}
                </a>
                @if ($production->isReadyForProduction() && $production->status !== 'approved')
                    <form action="{{ route('productions.mark-approved', $production) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="btn primary text-xs py-2 px-4">
                            {{ __('Marcar como Aprovada') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <!-- Esteira Visual de 5 Etapas -->
    @php
        $pipelineOrder = ['draft' => 1, 'pending_review' => 2, 'in_production' => 3, 'ready' => 4, 'approved' => 4, 'published' => 5];
        $currentStage = $pipelineOrder[$production->status] ?? 1;
        $pipelineStages = [
            1 => ['label' => __('1. Planejamento'), 'desc' => __('Pauta & Briefing')],
            2 => ['label' => __('2. Revisão Roteiro'), 'desc' => __('Aprovação do Cliente')],
            3 => ['label' => __('3. Gravação & Edição'), 'desc' => __('Captação / Ilha')],
            4 => ['label' => __('4. Corte Pronto'), 'desc' => __('Revisão Final / Aprovado')],
            5 => ['label' => __('5. Publicado'), 'desc' => __('Distribuído nas Redes')],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach($pipelineStages as $stepNumber => $stageInfo)
            @php
                $isCompleted = $stepNumber < $currentStage || $production->status === 'published';
                $isActive = $stepNumber === $currentStage && $production->status !== 'published';
            @endphp
            <div class="card p-4 {{ $isCompleted ? 'border-emerald-500/30' : ($isActive ? 'border-orange-500/40' : '') }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold {{ $isCompleted ? 'text-emerald-400' : ($isActive ? 'text-orange-400' : 'text-zinc-500') }}">
                        {{ $stageInfo['label'] }}
                    </span>
                    <span class="text-xs">
                        @if($isCompleted) ✓ @elseif($isActive) ● @else ○ @endif
                    </span>
                </div>
                <p class="text-xs text-zinc-400 mt-1">{{ $stageInfo['desc'] }}</p>
            </div>
        @endforeach
    </div>

    <!-- Metadata Row -->
    <div class="market-stats">
        <div class="card p-4 flex items-center justify-between gap-2">
            <div>
                <div class="stat-label">{{ __('Cliente Vinculado') }}</div>
                <div class="text-base font-semibold text-white">{{ $production->cliente ? $production->cliente->name : __('Interno') }}</div>
            </div>
            @if($production->cliente)
                <a href="{{ route('clientes.show', $production->cliente) }}" class="btn text-xs py-1.5 px-3">
                    {{ __('Ficha 360º →') }}
                </a>
            @endif
        </div>
        <div class="card p-4">
            <div class="stat-label">{{ __('Criado Por') }}</div>
            <div class="text-base font-semibold text-white">{{ $production->creator->name }}</div>
        </div>
        <div class="card p-4">
            <div class="stat-label">{{ __('Data de Entrega Alvo') }}</div>
            <div class="text-base font-semibold text-white">{{ $production->target_publication_date ? $production->target_publication_date->format('d/m/Y') : __('Não definida') }}</div>
        </div>
        <div class="card p-4">
            <div class="stat-label">{{ __('Criada em') }}</div>
            <div class="text-base font-semibold text-white">{{ $production->created_at->format('d/m/Y') }}</div>
        </div>
    </div>

    @if($production->packPurchase)
        <div class="card py-4 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-zinc-400">{{ __('Pacote de Origem:') }}</span>
                <span class="status-pill copper">
                    {{ $production->packPurchase->pack?->name ?? __('Pacote Contratado') }}
                </span>
            </div>
            @if(!empty($production->packPurchase->addons_purchased))
                <div class="flex items-center flex-wrap gap-1.5">
                    <span class="text-zinc-400 text-xs mr-1">{{ __('Add-ons Contratados:') }}</span>
                    @foreach($production->packPurchase->addons_purchased as $addon)
                        <span class="status-pill">+ {{ $addon }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- ROTEIROS SECTION -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('Roteiros da Produção') }}</h2>
                <p class="card-description">{{ __('Textos estratégicos e estruturas narrativas vinculadas.') }}</p>
            </div>
            <a href="{{ route('roteiros.create', $production) }}" class="btn primary text-xs py-2 px-4">
                + {{ __('Novo Roteiro') }}
            </a>
        </div>

        @if ($roteiros->isEmpty())
            <div class="py-8 text-center text-sm text-zinc-500">
                {{ __('Nenhum roteiro cadastrado para esta produção ainda.') }}
            </div>
        @else
            <div class="divide-y divide-zinc-800">
                @foreach ($roteiros as $roteiro)
                    <div class="list-row">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="font-semibold text-sm text-white">{{ __('Roteiro #') }}{{ $roteiro->id }}</span>
                                <span class="status-pill {{ $roteiro->status === 'approved' ? 'positive' : ($roteiro->status === 'rejected' ? 'negative' : 'copper') }}">
                                    {{ ucfirst(str_replace('_', ' ', $roteiro->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-400 mt-1">{{ __('Criado por:') }} {{ $roteiro->creator->name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $roteiro->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
                               class="btn text-xs py-1.5 px-3">
                                {{ __('WhatsApp Aprovação') }}
                            </a>
                            <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}"
                               class="btn primary text-xs py-1.5 px-3.5">
                                {{ __('Ver Roteiro') }} →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="pt-4">
                {{ $roteiros->links() }}
            </div>
        @endif
    </div>

    <!-- VÍDEOS SECTION -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('Vídeos e Entregáveis') }}</h2>
                <p class="card-description">{{ __('Arquivos finais, links de entrega e status de publicação.') }}</p>
            </div>
            @can('create', \App\Models\Video::class)
                <a href="{{ route('videos.create', $production) }}" class="btn primary text-xs py-2 px-4">
                    + {{ __('Novo Vídeo') }}
                </a>
            @endcan
        </div>

        @if ($videos->isEmpty())
            <div class="py-8 text-center text-sm text-zinc-500 space-y-3">
                <p>{{ __('Nenhum vídeo cadastrado nesta produção ainda.') }}</p>
                @can('create', \App\Models\Video::class)
                    <div>
                        <a href="{{ route('videos.create', $production) }}" class="btn primary text-xs py-2 px-4 inline-flex items-center gap-1.5">
                            + {{ __('Cadastrar Vídeo para Aprovação do Cliente') }}
                        </a>
                    </div>
                @endcan
            </div>
        @else
            <div class="divide-y divide-zinc-800">
                @foreach ($videos as $video)
                    <div class="list-row">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <span class="font-semibold text-sm text-white">{{ $video->title }}</span>
                                <span class="status-pill {{ $video->status === 'approved' ? 'positive' : 'copper' }}">
                                    {{ ucfirst(str_replace('_', ' ', $video->status)) }}
                                </span>
                            </div>
                            @if ($video->platform)
                                <p class="text-xs text-zinc-400 mt-1">{{ __('Plataforma:') }} {{ ucfirst($video->platform) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $video->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
                               class="btn text-xs py-1.5 px-3">
                                {{ __('WhatsApp Vídeo') }}
                            </a>
                            <a href="{{ route('videos.show', ['production' => $production, 'video' => $video]) }}"
                               class="btn primary text-xs py-1.5 px-3.5">
                                {{ __('Ver Vídeo') }} →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="pt-4">
                {{ $videos->links() }}
            </div>
        @endif
    </div>

    <div>
        <a href="{{ route('productions.index') }}" class="text-xs font-medium text-zinc-400 hover:text-white transition">
            ← {{ __('Voltar para todas as produções') }}
        </a>
    </div>
</div>
@endsection
