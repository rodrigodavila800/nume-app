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

    <!-- Card Sofi: Central de Notificação & Acompanhamento de Status da Produção -->
    @if ($production->cliente)
        <div class="p-6 rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-orange-950/30 border border-orange-500/30 shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800/80 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center text-white font-extrabold shadow-md shadow-orange-900/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-extrabold text-white tracking-tight">
                                {{ __('Sofi Agente: Atualização da Esteira para o Cliente') }}
                            </h3>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-orange-500/10 text-orange-400 border border-orange-500/20">
                                {{ ucfirst(str_replace('_', ' ', $production->status)) }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            Destinatário: <strong class="text-zinc-200">{{ $production->cliente->name }}</strong> ({{ $production->cliente->email }})
                            @if ($production->cliente->phone)
                                · WhatsApp: <span class="text-zinc-300 font-mono">{{ $production->cliente->phone }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Disparo de E-mail via Sofi -->
                    <form action="{{ route('productions.notify-cliente', $production) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="channel" value="email">
                        <button type="submit" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 shadow-md shadow-orange-950/40 transition active:scale-95 cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>{{ __('Enviar E-mail de Status') }}</span>
                        </button>
                    </form>

                    <!-- Abertura Direta do WhatsApp -->
                    <a href="{{ $whatsappStatusUrl }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-950/40 transition active:scale-95">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span>{{ __('Abrir WhatsApp') }}</span>
                    </a>

                    <!-- Botão Copiar Mensagem -->
                    <button type="button" onclick="copySofiStatusWhatsAppText()" id="btn-copy-status-whatsapp"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span id="label-copy-status-whatsapp">{{ __('Copiar Mensagem') }}</span>
                    </button>
                </div>
            </div>

            <!-- Preview da Mensagem Sofi Gerada -->
            <div class="rounded-xl bg-zinc-950/60 border border-zinc-800/80 p-3.5 space-y-2">
                <div class="flex items-center justify-between text-[11px] text-zinc-400">
                    <span class="font-bold uppercase tracking-wider text-orange-400/90">{{ __('Prévia da Mensagem Contextualizada:') }}</span>
                    <span class="text-zinc-500">{{ __('Link direto do portal embutido') }}</span>
                </div>
                <pre id="sofi-status-whatsapp-text" class="text-xs text-zinc-300 font-mono whitespace-pre-wrap leading-relaxed max-h-32 overflow-y-auto p-2 rounded-lg bg-zinc-900/50 border border-zinc-800/50">{{ $whatsappStatusMessage }}</pre>
            </div>
        </div>

        <script>
            function copySofiStatusWhatsAppText() {
                const text = document.getElementById('sofi-status-whatsapp-text').innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const label = document.getElementById('label-copy-status-whatsapp');
                    const original = label.innerText;
                    label.innerText = '{{ __('Copiado!') }}';
                    setTimeout(() => { label.innerText = original; }, 2000);
                });
            }
        </script>
    @endif

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
