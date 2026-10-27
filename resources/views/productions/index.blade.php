@extends('layouts.app')

@section('title', __('NUME | Painel de Produções'))
@section('breadcrumb', __('Produções'))

@section('content')
<div class="space-y-6">

    <!-- 1. Page Header -->
    <div class="page-header">
        <div>
            <h1>{{ __('Painel de Produções') }}</h1>
            <p>{{ __('Gerencie roteiros, esteira de gravação e entregas estratégicas da NUME Creative.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('batch_planner.index') }}" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                    <polyline points="2 17 12 22 22 17"/>
                    <polyline points="2 12 12 17 22 12"/>
                </svg>
                <span>{{ __('Sofi Batch Planner') }}</span>
            </a>

            <a href="{{ route('productions.create') }}" class="btn primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>{{ __('Nova Produção') }}</span>
            </a>
        </div>
    </div>

    <!-- 2. Operational KPI Row (.market-stats / .stat-card) -->
    <div class="market-stats">
        <!-- 1. Pedidos de Orçamento -->
        <a href="{{ route('quotes.index', ['status' => ($stats['quote_requests'] ?? 0) > 0 ? 'requested' : 'all']) }}"
           class="stat-card {{ ($stats['quote_requests'] ?? 0) > 0 ? 'primary' : '' }}">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">{{ __('Pedidos de Orçamento') }}</div>
                <div class="stat-value">{{ $stats['quote_requests'] ?? 0 }}</div>
                <div class="stat-change {{ ($stats['quote_requests'] ?? 0) > 0 ? 'positive' : '' }}">
                    {{ ($stats['quote_requests'] ?? 0) > 0 ? __('Aguardando cálculo da equipe') : __('Nenhum pedido pendente') }}
                </div>
            </div>
        </a>

        <!-- 2. Clientes & Planos -->
        <a href="{{ route('clientes.index') }}" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">{{ __('Clientes & Planos') }}</div>
                <div class="stat-value">{{ $stats['total_clientes'] ?? 0 }}</div>
                <div class="stat-change {{ ($stats['plan_adjustments'] ?? 0) > 0 ? 'negative' : '' }}">
                    @if (($stats['plan_adjustments'] ?? 0) > 0)
                        {{ $stats['plan_adjustments'] }} {{ __('ajuste(s) pedido(s)') }}
                    @else
                        {{ __('Abrir Ficha 360º, DNA e Anamnese') }}
                    @endif
                </div>
            </div>
        </a>

        <!-- 3. Em Gravação & Edição -->
        <a href="{{ route('productions.index', ['view' => 'kanban']) }}" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="23 7 16 12 23 17 23 7"/>
                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">{{ __('Em Gravação & Edição') }}</div>
                <div class="stat-value">{{ $stats['in_production'] ?? 0 }}</div>
                <div class="stat-change positive">
                    {{ __(':roteiros roteiros · :videos vídeos', ['roteiros' => $stats['total_roteiros'] ?? 0, 'videos' => $stats['total_videos'] ?? 0]) }}
                </div>
            </div>
        </a>

        <!-- 4. Suporte ao Cliente -->
        <a href="{{ route('support.index') }}" class="stat-card">
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">{{ __('Suporte ao Cliente') }}</div>
                <div class="stat-value">{{ $stats['open_tickets'] ?? 0 }}</div>
                <div class="stat-change {{ ($stats['open_tickets'] ?? 0) > 0 ? 'negative' : '' }}">
                    {{ ($stats['open_tickets'] ?? 0) > 0 ? __('Chamados abertos aguardando') : __('Sem chamados pendentes') }}
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Filter & View Switcher -->
    <div class="card py-4 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <form method="GET" action="{{ route('productions.index') }}" class="flex items-center gap-3">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <select name="cliente_id" onchange="this.form.submit()"
                    class="bg-zinc-950 border border-zinc-800 rounded-xl px-3.5 py-2 text-xs font-medium text-zinc-200 focus:outline-none">
                <option value="">{{ __('Todos os Clientes') }}</option>
                @foreach($clientes as $c)
                    <option value="{{ $c->id }}" {{ $clienteId == $c->id ? 'selected' : '' }}>
                        {{ $c->company_name ?: $c->name }}
                    </option>
                @endforeach
            </select>
            @if($clienteId)
                <a href="{{ route('productions.index', ['view' => $viewMode]) }}" class="text-xs text-orange-400 hover:underline">
                    {{ __('Limpar filtro') }}
                </a>
            @endif
        </form>

        <div class="filter-tabs">
            <a href="{{ route('productions.index', ['view' => 'list', 'cliente_id' => $clienteId]) }}"
               class="filter-tab {{ $viewMode !== 'kanban' ? 'active' : '' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                <span>{{ __('Lista') }}</span>
            </a>
            <a href="{{ route('productions.index', ['view' => 'kanban', 'cliente_id' => $clienteId]) }}"
               class="filter-tab {{ $viewMode === 'kanban' ? 'active' : '' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="5" height="18" rx="1"/>
                    <rect x="10" y="3" width="5" height="12" rx="1"/>
                    <rect x="17" y="3" width="5" height="15" rx="1"/>
                </svg>
                <span>{{ __('Kanban Pipeline') }}</span>
            </a>
        </div>
    </div>

    <!-- 4. Content: Kanban vs. Table/Grid -->
    @if ($viewMode === 'kanban')
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4 items-start">
            @foreach ($kanbanColumns as $statusKey => $column)
                <div class="card p-4 space-y-3 flex flex-col min-h-[480px]">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                        <h3 class="text-sm font-semibold text-white">{{ $column['label'] }}</h3>
                        <span class="status-pill copper">{{ $column['items']->count() }}</span>
                    </div>

                    <div class="space-y-3 flex-1 overflow-y-auto max-h-[680px]">
                        @forelse ($column['items'] as $production)
                            <div class="p-3.5 bg-zinc-950 border border-zinc-800 rounded-xl space-y-2.5 transition">
                                <div class="flex items-center justify-between text-xs text-zinc-500 font-mono">
                                    <span>#{{ strtoupper(substr($production->uuid, 0, 6)) }}</span>
                                    @if($production->target_publication_date)
                                        <span class="text-orange-400">{{ $production->target_publication_date->format('d/m') }}</span>
                                    @endif
                                </div>
                                <h4 class="text-sm font-semibold text-white">
                                    <a href="{{ route('productions.show', $production) }}" class="hover:underline">
                                        {{ $production->title }}
                                    </a>
                                </h4>
                                <p class="text-xs text-zinc-400 truncate">
                                    {{ $production->cliente?->company_name ?: ($production->cliente?->name ?? __('Sem cliente vinculado')) }}
                                </p>

                                <div class="pt-2 border-t border-zinc-800 flex items-center justify-between text-xs text-zinc-400">
                                    <span>{{ $production->roteiros->count() }} roteiros · {{ $production->videos->count() }} vídeos</span>
                                    @php $primaryRoteiro = $production->roteiros->first(); @endphp
                                    @if($primaryRoteiro && method_exists($primaryRoteiro, 'getWhatsAppShareUrl'))
                                        <a href="{{ $primaryRoteiro->getWhatsAppShareUrl() }}" target="_blank" class="text-emerald-400 hover:underline font-medium">
                                            WhatsApp
                                        </a>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('productions.update-status', $production) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" onchange="this.form.submit()"
                                            class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-300 focus:outline-none">
                                        <option value="draft" {{ $production->status === 'draft' ? 'selected' : '' }}>Planejamento</option>
                                        <option value="pending_review" {{ in_array($production->status, ['pending_review', 'submitted']) ? 'selected' : '' }}>Revisão Cliente</option>
                                        <option value="in_production" {{ $production->status === 'in_production' ? 'selected' : '' }}>Em Gravação/Edição</option>
                                        <option value="ready" {{ $production->status === 'ready' ? 'selected' : '' }}>Corte Pronto</option>
                                        <option value="approved" {{ $production->status === 'approved' ? 'selected' : '' }}>Aprovado</option>
                                        <option value="published" {{ $production->status === 'published' ? 'selected' : '' }}>Publicado</option>
                                    </select>
                                </form>
                            </div>
                        @empty
                            <div class="p-6 rounded-xl border border-dashed border-zinc-800 text-center text-zinc-500 text-xs">
                                {{ __('Nenhum item nesta coluna') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('Todas as Produções') }}</h2>
                <span class="card-description">{{ $productions->total() }} {{ __('registros') }}</span>
            </div>

            @if ($productions->isEmpty())
                <div class="py-12 text-center space-y-4">
                    <h3 class="text-base font-semibold text-white">{{ __('Nenhuma produção encontrada') }}</h3>
                    <p class="text-sm text-zinc-400 max-w-md mx-auto">
                        {{ __('Comece criando sua primeira produção estratégica para adicionar roteiros, gravar e entregar ao cliente.') }}
                    </p>
                    <div>
                        <a href="{{ route('productions.create') }}" class="btn primary">
                            + {{ __('Criar Primeira Produção') }}
                        </a>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="market-table">
                        <thead>
                            <tr>
                                <th>{{ __('Produção') }}</th>
                                <th>{{ __('Cliente') }}</th>
                                <th>{{ __('Conteúdo') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Responsável') }}</th>
                                <th class="text-right">{{ __('Ações') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productions as $production)
                                @php
                                    $statusPill = match($production->status) {
                                        'approved', 'published', 'ready' => 'positive',
                                        'in_production', 'pending_review' => 'copper',
                                        default => '',
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
                                <tr>
                                    <td>
                                        <a href="{{ route('productions.show', $production) }}" class="font-semibold text-white hover:text-orange-400 block">
                                            {{ $production->title }}
                                        </a>
                                        <span class="text-xs text-zinc-400 line-clamp-1">
                                            {{ $production->description ?: __('Sem descrição detalhada.') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-sm font-medium">
                                            {{ $production->cliente ? $production->cliente->name : __('Sem cliente') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-xs text-orange-400 font-medium">
                                            {{ $production->roteiros_count ?? $production->roteiros->count() }} scripts · {{ $production->videos_count ?? $production->videos->count() }} vídeos
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $statusPill }}">
                                            {{ $statusLabels[$production->status] ?? ucfirst($production->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-xs text-zinc-400">{{ $production->creator->name ?? 'Admin' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('productions.show', $production) }}" class="btn text-xs py-1.5 px-3">
                                                {{ __('Acessar Produção') }}
                                            </a>
                                            @can('update', $production)
                                                <a href="{{ route('productions.edit', $production) }}" class="btn text-xs py-1.5 px-2.5" title="Editar">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                    </svg>
                                                </a>
                                                <form action="{{ route('productions.destroy', $production) }}" method="POST" class="inline" onsubmit="return confirm('Deseja excluir esta produção?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn danger text-xs py-1.5 px-2.5" title="Excluir">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="3 6 5 6 21 6"/>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-4">
                    {{ $productions->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
