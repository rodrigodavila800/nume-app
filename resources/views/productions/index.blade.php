@extends('layouts.app')

@section('title', __('NUME | Painel de Produções'))
@section('breadcrumb', __('Produções'))

@section('content')
<div class="space-y-8">

    <!-- ================= 1. PAGE HEADER ================= -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Painel de Produções') }}</span>
                <span class="text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    Modo Criador PRO
                </span>
            </h1>
            <p class="text-xs sm:text-sm text-zinc-400 mt-1">
                {{ __('Gerencie roteiros, esteira de gravação e entregas estratégicas da NUME Creative.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('batch_planner.index') }}"
               class="inline-flex items-center justify-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 transition-all">
                <span>⚡</span>
                <span>{{ __('Sofi Batch Planner') }}</span>
            </a>

            <a href="{{ route('productions.create') }}"
               class="inline-flex items-center justify-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>{{ __('Nova Produção') }}</span>
            </a>
        </div>
    </div>

    <!-- ================= 2. FILA DE AÇÃO OPERACIONAL DA EQUIPE ================= -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- 1. Pedidos de Orçamento de Clientes -->
        <a href="{{ route('quotes.index', ['status' => ($stats['quote_requests'] ?? 0) > 0 ? 'requested' : 'all']) }}"
           class="rounded-2xl p-5 bg-zinc-900 border {{ ($stats['quote_requests'] ?? 0) > 0 ? 'border-orange-500/40 bg-orange-950/10' : 'border-zinc-800' }} hover:border-orange-500/50 transition block group">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Pedidos de Orçamento') }}</p>
                <span class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-sm">🧮</span>
            </div>
            <p class="text-3xl font-extrabold text-white font-mono">{{ $stats['quote_requests'] ?? 0 }}</p>
            <p class="text-[11px] {{ ($stats['quote_requests'] ?? 0) > 0 ? 'text-orange-400 font-bold' : 'text-zinc-500' }} mt-2 flex items-center justify-between">
                <span>{{ ($stats['quote_requests'] ?? 0) > 0 ? __('Aguardando cálculo da equipe') : __('Nenhum pedido pendente') }}</span>
                <span class="group-hover:translate-x-0.5 transition">→</span>
            </p>
        </a>

        <!-- 2. Clientes & Planos de Marketing -->
        <a href="{{ route('clientes.index') }}"
           class="rounded-2xl p-5 bg-zinc-900 border {{ ($stats['plan_adjustments'] ?? 0) > 0 ? 'border-amber-500/40 bg-amber-950/10' : 'border-zinc-800' }} hover:border-amber-500/50 transition block group">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Clientes & Planos') }}</p>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-sm">👥</span>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-extrabold text-white font-mono">{{ $stats['total_clientes'] ?? 0 }}</p>
                @if (($stats['plan_adjustments'] ?? 0) > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        {{ $stats['plan_adjustments'] }} {{ __('ajuste(s) pedido(s)') }}
                    </span>
                @endif
            </div>
            <p class="text-[11px] text-zinc-400 mt-2 flex items-center justify-between">
                <span>{{ __('Abrir Ficha 360º, DNA e Anamnese') }}</span>
                <span class="group-hover:translate-x-0.5 transition">→</span>
            </p>
        </a>

        <!-- 3. Esteira Audiovisual Ativa -->
        <a href="{{ route('productions.index', ['view' => 'kanban']) }}"
           class="rounded-2xl p-5 bg-zinc-900 border border-zinc-800 hover:border-sky-500/50 transition block group">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Em Gravação & Edição') }}</p>
                <span class="w-8 h-8 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sm">🎬</span>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-extrabold text-white font-mono">{{ $stats['in_production'] ?? 0 }}</p>
                <span class="text-xs text-zinc-500">{{ __('de :total produções', ['total' => $stats['total'] ?? $productions->total()]) }}</span>
            </div>
            <p class="text-[11px] text-sky-400 mt-2 flex items-center justify-between font-semibold">
                <span>{{ __(':roteiros roteiros · :videos vídeos', ['roteiros' => $stats['total_roteiros'] ?? 0, 'videos' => $stats['total_videos'] ?? 0]) }}</span>
                <span class="group-hover:translate-x-0.5 transition">→</span>
            </p>
        </a>

        <!-- 4. Chamados de Suporte Abertos -->
        <a href="{{ route('support.index') }}"
           class="rounded-2xl p-5 bg-zinc-900 border {{ ($stats['open_tickets'] ?? 0) > 0 ? 'border-rose-500/40 bg-rose-950/10' : 'border-zinc-800' }} hover:border-rose-500/50 transition block group">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Suporte ao Cliente') }}</p>
                <span class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-sm">🛟</span>
            </div>
            <p class="text-3xl font-extrabold text-white font-mono">{{ $stats['open_tickets'] ?? 0 }}</p>
            <p class="text-[11px] {{ ($stats['open_tickets'] ?? 0) > 0 ? 'text-rose-400 font-bold' : 'text-zinc-500' }} mt-2 flex items-center justify-between">
                <span>{{ ($stats['open_tickets'] ?? 0) > 0 ? __('Chamados abertos aguardando') : __('Sem chamados pendentes') }}</span>
                <span class="group-hover:translate-x-0.5 transition">→</span>
            </p>
        </a>
    </div>

    <!-- ================= 3. CONTROLES: FILTRO DE CLIENTE & SELETOR DE VISUALIZAÇÃO (LISTA vs. KANBAN) ================= -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-zinc-900 border border-zinc-800">
        <!-- Filtro de Cliente -->
        <form method="GET" action="{{ route('productions.index') }}" class="flex items-center gap-3">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <div class="relative">
                <select name="cliente_id" onchange="this.form.submit()"
                        class="bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2 text-xs font-semibold text-zinc-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 pr-8">
                    <option value="">{{ __('Todos os Clientes') }}</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ $clienteId == $c->id ? 'selected' : '' }}>
                            {{ $c->company_name ?: $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if($clienteId)
                <a href="{{ route('productions.index', ['view' => $viewMode]) }}" class="text-xs text-orange-400 hover:underline">
                    {{ __('Limpar filtro') }}
                </a>
            @endif
        </form>

        <!-- Segmented Control: Lista vs. Kanban -->
        <div class="inline-flex p-1 bg-zinc-950 rounded-xl border border-zinc-800 self-start sm:self-auto gap-1">
            <a href="{{ route('productions.index', ['view' => 'list', 'cliente_id' => $clienteId]) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $viewMode !== 'kanban' ? 'bg-orange-600 text-white shadow-xs' : 'text-zinc-400 hover:text-white' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                <span>{{ __('Lista') }}</span>
            </a>
            <a href="{{ route('productions.index', ['view' => 'kanban', 'cliente_id' => $clienteId]) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $viewMode === 'kanban' ? 'bg-orange-600 text-white shadow-xs' : 'text-zinc-400 hover:text-white' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                <span>{{ __('Kanban Pipeline') }}</span>
            </a>
        </div>
    </div>

    <!-- ================= 4. VISUALIZAÇÃO: KANBAN vs. LISTA ================= -->
    @if ($viewMode === 'kanban')
        <!-- KANBAN BOARD (5 COLUNAS OPERACIONAIS) -->
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4 items-start">
            @foreach ($kanbanColumns as $statusKey => $column)
                <div class="bg-zinc-900/70 border border-zinc-800 rounded-2xl p-3.5 space-y-3 flex flex-col min-h-[500px]">
                    <!-- Cabeçalho da Coluna -->
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-800/80">
                        <div class="flex items-center gap-2">
                            <span>{{ $column['icon'] }}</span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-zinc-200">
                                {{ $column['label'] }}
                            </h3>
                        </div>
                        <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full {{ $column['badge'] }} border">
                            {{ $column['items']->count() }}
                        </span>
                    </div>

                    <!-- Cards da Coluna -->
                    <div class="space-y-3 flex-1 overflow-y-auto max-h-[700px] [scrollbar-width:thin]">
                        @forelse ($column['items'] as $production)
                            <div class="p-3.5 bg-zinc-950 border border-zinc-800/90 hover:border-zinc-700 rounded-xl space-y-3 transition-all hover:shadow-lg hover:shadow-black/50 group">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[10px] font-mono text-zinc-500">
                                        <span>REF: {{ strtoupper(substr($production->uuid, 0, 8)) }}</span>
                                        @if($production->target_publication_date)
                                            <span class="text-orange-400 font-bold">
                                                🗓️ {{ $production->target_publication_date->format('d/m') }}
                                            </span>
                                        @endif
                                    </div>
                                    <h4 class="text-xs font-bold text-white group-hover:text-orange-400 transition line-clamp-2">
                                        <a href="{{ route('productions.show', $production) }}">
                                            {{ $production->title }}
                                        </a>
                                    </h4>
                                    <p class="text-[11px] text-zinc-400 truncate">
                                        {{ $production->cliente?->company_name ?: ($production->cliente?->name ?? __('Sem cliente vinculado')) }}
                                    </p>
                                </div>

                                <!-- Meta Tags e Ações Rápidas -->
                                <div class="pt-2 border-t border-zinc-900 flex items-center justify-between text-[10px] text-zinc-500">
                                    <span class="font-medium">
                                        ✍️ {{ $production->roteiros->count() }} | 🎬 {{ $production->videos->count() }}
                                    </span>

                                    <!-- Quick WhatsApp Magic Link se houver roteiro -->
                                    @php
                                        $primaryRoteiro = $production->roteiros->first();
                                    @endphp
                                    @if($primaryRoteiro && method_exists($primaryRoteiro, 'getWhatsAppShareUrl'))
                                        <a href="{{ $primaryRoteiro->getWhatsAppShareUrl() }}" target="_blank"
                                           class="inline-flex items-center gap-1 text-emerald-400 hover:text-emerald-300 font-bold"
                                           title="{{ __('Enviar Magic Link pelo WhatsApp') }}">
                                            <span>💬 WhatsApp</span>
                                        </a>
                                    @endif
                                </div>

                                <!-- Seletor Rápido de Status (Move Card) -->
                                <form method="POST" action="{{ route('productions.update-status', $production) }}" class="pt-1">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" onchange="this.form.submit()"
                                            class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-2 py-1 text-[10px] font-medium text-zinc-300 focus:outline-none focus:border-orange-500">
                                        <option value="draft" {{ $production->status === 'draft' ? 'selected' : '' }}>💡 Planejamento</option>
                                        <option value="pending_review" {{ in_array($production->status, ['pending_review', 'submitted']) ? 'selected' : '' }}>⏳ Revisão Cliente</option>
                                        <option value="in_production" {{ $production->status === 'in_production' ? 'selected' : '' }}>🎬 Em Gravação/Edição</option>
                                        <option value="ready" {{ $production->status === 'ready' ? 'selected' : '' }}>🎞️ Corte Pronto</option>
                                        <option value="approved" {{ $production->status === 'approved' ? 'selected' : '' }}>✓ Aprovado / Pronto</option>
                                        <option value="published" {{ $production->status === 'published' ? 'selected' : '' }}>🚀 Publicado</option>
                                    </select>
                                </form>
                            </div>
                        @empty
                            <div class="p-6 rounded-xl border border-dashed border-zinc-800 text-center text-zinc-600 text-[11px]">
                                {{ __('Nenhum item nesta coluna') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- VISUALIZAÇÃO CLÁSSICA EM GRID / LISTA -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-extrabold text-white">{{ __('Todas as Produções') }}</h2>
                <span class="text-xs text-zinc-500">{{ $productions->total() }} {{ __('registros') }}</span>
            </div>

            @if ($productions->isEmpty())
                <div class="p-12 rounded-2xl bg-zinc-900 border border-zinc-800 text-center space-y-4">
                    <div class="w-14 h-14 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-500 flex items-center justify-center mx-auto">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">{{ __('Nenhuma produção encontrada') }}</h3>
                        <p class="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
                            {{ __('Comece criando sua primeira produção estratégica para adicionar roteiros, gravar e entregar ao cliente.') }}
                        </p>
                    </div>
                    <a href="{{ route('productions.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-lg shadow-orange-900/20 transition">
                        + {{ __('Criar Primeira Produção') }}
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($productions as $production)
                        <div class="rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 p-5 flex flex-col justify-between space-y-4 transition-all hover:shadow-xl hover:shadow-black/40 group">
                            
                            <!-- Top: Title & Status Badge -->
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-base font-bold text-white group-hover:text-orange-400 transition line-clamp-1">
                                        {{ $production->title }}
                                    </h3>

                                    @php
                                        $statusClasses = match($production->status) {
                                            'approved' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                            'in_production' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                            'pending_review' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                                            'published' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                            default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                                        };
                                        $statusLabels = [
                                            'draft' => __('Rascunho'),
                                            'pending_review' => __('Em Revisão'),
                                            'approved' => __('Aprovado'),
                                            'in_production' => __('Em Produção'),
                                            'ready' => __('Pronto'),
                                            'published' => __('Publicado'),
                                        ];
                                    @endphp
                                    <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider rounded-lg border {{ $statusClasses }} shrink-0">
                                        {{ $statusLabels[$production->status] ?? ucfirst($production->status) }}
                                    </span>
                                </div>

                                <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed">
                                    {{ $production->description ?: __('Sem descrição detalhada.') }}
                                </p>
                            </div>

                            <!-- Meta Info -->
                            <div class="space-y-2 pt-3 border-t border-zinc-800/80 text-xs">
                                <div class="flex items-center justify-between text-zinc-400">
                                    <span class="text-zinc-500">{{ __('Cliente:') }}</span>
                                    <span class="font-semibold text-zinc-200">
                                        {{ $production->cliente ? $production->cliente->name : __('Sem cliente') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-zinc-400">
                                    <span class="text-zinc-500">{{ __('Roteiros / Vídeos:') }}</span>
                                    <span class="font-bold text-orange-400">
                                        {{ $production->roteiros_count ?? $production->roteiros->count() }} scripts · {{ $production->videos_count ?? $production->videos->count() }} vídeos
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-zinc-400">
                                    <span class="text-zinc-500">{{ __('Responsável:') }}</span>
                                    <span class="text-zinc-300">{{ $production->creator->name ?? 'Admin' }}</span>
                                </div>
                            </div>

                            <!-- Actions Buttons -->
                            <div class="flex items-center gap-2 pt-2">
                                <a href="{{ route('productions.show', $production) }}"
                                   class="flex-1 text-center py-2 px-3 rounded-xl bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white font-bold text-xs border border-orange-500/20 transition-all">
                                    {{ __('Acessar Produção') }} →
                                </a>

                                @can('update', $production)
                                    <a href="{{ route('productions.edit', $production) }}"
                                       class="p-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition" title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <form action="{{ route('productions.destroy', $production) }}" method="POST" class="inline" onsubmit="return confirm('Deseja excluir esta produção?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 rounded-xl bg-zinc-800 hover:bg-rose-950 hover:text-rose-400 text-zinc-400 transition" title="Excluir">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="pt-4">
                    {{ $productions->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
