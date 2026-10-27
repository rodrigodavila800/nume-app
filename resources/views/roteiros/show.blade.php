@extends('layouts.app')

@section('title', __('NUME | Visualizar Roteiro'))
@section('breadcrumb', __('Roteiro'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Topo / Navegação e Status -->
    <div class="page-header">
        <div>
            <a href="{{ route('roteiros.index', $production) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-400 hover:text-orange-400 transition mb-2">
                <span>←</span>
                <span>{{ __('Voltar para Roteiros da Produção') }}</span>
            </a>
            <div class="flex items-center gap-3">
                <h1>{{ __('Roteiro') }} #{{ $roteiro->id }}</h1>
                @php
                    $statusPill = match($roteiro->status) {
                        'approved' => 'positive',
                        'rejected' => 'negative',
                        'submitted', 'in_production' => 'copper',
                        default => '',
                    };
                @endphp
                <span class="status-pill {{ $statusPill }}">
                    {{ ucfirst(str_replace('_', ' ', $roteiro->status)) }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @can('update', $roteiro)
                @if (in_array($roteiro->status, ['draft', 'rejected']))
                    <a href="{{ route('roteiros.edit', ['production' => $production, 'roteiro' => $roteiro]) }}" class="btn primary text-xs py-2 px-4">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        <span>{{ $roteiro->status === 'rejected' ? __('Aplicar Ajustes (Sofi ou Manual)') : __('Editar Roteiro (Sofi ou Manual)') }}</span>
                    </a>
                @elseif ($roteiro->status === 'submitted')
                    <span class="status-pill copper" title="{{ __('Em revisão pelo cliente: edição bloqueada até o cliente aprovar ou sugerir mudanças') }}">
                        {{ __('Em Revisão pelo Cliente (Edição Bloqueada)') }}
                    </span>
                @else
                    <span class="status-pill" title="{{ __('Roteiro aprovado não permite edição direta') }}">
                        {{ __('Bloqueado para Produção') }}
                    </span>
                @endif
            @endcan
        </div>
    </div>

    <!-- Cards de Metadados -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
            <span class="stat-label block">{{ __('Criado por') }}</span>
            <p class="text-base font-semibold text-white mt-1">
                {{ $roteiro->creator?->name ?? 'Admin NUME' }}
            </p>
        </div>
        <div class="card p-4">
            <span class="stat-label block">{{ __('Produção Vinculada') }}</span>
            <p class="text-base font-semibold text-orange-400 mt-1 truncate" title="{{ $production->title }}">
                {{ $production->title }}
            </p>
        </div>
        <div class="card p-4">
            <span class="stat-label block">{{ __('Data de Criação') }}</span>
            <p class="text-base font-semibold text-white mt-1">
                {{ $roteiro->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
    </div>

    <!-- Conteúdo do Roteiro -->
    <div class="card space-y-4">
        <div class="card-header pb-3 border-b border-zinc-800">
            <h2 class="card-title">{{ __('Estrutura do Roteiro') }}</h2>
            <button type="button"
                    onclick="navigator.clipboard.writeText(`{{ addslashes($roteiro->content) }}`); alert('{{ __('Roteiro copiado com sucesso!') }}');"
                    class="btn text-xs py-1.5 px-3">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span>{{ __('Copiar Roteiro') }}</span>
            </button>
        </div>

        @include('roteiros._structure')

        @if($roteiro->plan_override_reason)
            <p class="text-xs text-orange-400 bg-zinc-950 border border-zinc-800 rounded-xl px-3.5 py-2.5">
                <strong class="font-semibold">{{ __('Criado sem plano aprovado.') }}</strong> {{ $roteiro->plan_override_reason }}
            </p>
        @endif

        <div class="p-5 sm:p-6 bg-zinc-950 border border-zinc-800 rounded-xl font-sans text-sm sm:text-base text-zinc-100 whitespace-pre-wrap leading-relaxed">
{{ $roteiro->content }}
        </div>
    </div>

    <!-- Status de Aprovação e Feedback -->
    @if ($roteiro->status === 'approved' || $roteiro->status === 'in_production')
        <div class="card space-y-1">
            <p class="font-semibold text-emerald-400 text-sm">
                {{ __('Roteiro Aprovado para Gravação') }}
            </p>
            <p class="text-xs text-zinc-400">
                {{ __('Aprovado por:') }} <strong class="text-white">{{ $roteiro->approved_by_cliente_id ? $roteiro->approverCliente?->name . ' (cliente)' : $roteiro->approver?->name }}</strong>
                @if($roteiro->approved_at)
                    · {{ __('em') }} <strong class="text-white">{{ $roteiro->approved_at->format('d/m/Y H:i') }}</strong>
                @endif
            </p>
        </div>
    @elseif ($roteiro->rejection_reason)
        <div class="card space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <p class="font-semibold text-rose-400 text-sm">
                        {{ __('O Cliente Sugeriu Mudanças neste Roteiro') }}
                    </p>
                    <p class="text-zinc-400 text-xs mt-0.5">
                        {{ __('Solicitado por:') }} <strong class="text-white">{{ $roteiro->approved_by_cliente_id ? $roteiro->approverCliente?->name . ' (cliente)' : $roteiro->approver?->name }}</strong>
                    </p>
                </div>
                @can('update', $roteiro)
                    <a href="{{ route('roteiros.edit', ['production' => $production, 'roteiro' => $roteiro]) }}"
                       class="btn primary text-xs py-2 px-4">
                        {{ __('Reescrever com Sofi ou Ajustar Manualmente →') }}
                    </a>
                @endcan
            </div>
            <div class="p-3.5 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-rose-300">
                {{ $roteiro->rejection_reason }}
            </div>
        </div>
    @elseif ($roteiro->submitted_at)
        <div class="card flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1">
                <p class="font-semibold text-orange-400 text-sm">
                    {{ __('Aguardando Revisão do Cliente no Portal') }}
                </p>
                <p class="text-zinc-400 text-xs">
                    {{ __('Submetido em:') }} <strong class="text-white">{{ $roteiro->submitted_at->format('d/m/Y H:i') }}</strong> · {{ __('Enquanto está em revisão com o cliente, a edição manual fica bloqueada até o cliente aprovar ou sugerir mudanças.') }}
                </p>
            </div>
            <a href="{{ $roteiro->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
               class="btn text-xs py-2 px-4 shrink-0">
                {{ __('Enviar Link no WhatsApp') }}
            </a>
        </div>
    @endif

    <!-- Ações do Produtor / Staff -->
    <div class="flex items-center gap-3 pt-2 flex-wrap">
        @can('update', $roteiro)
            @if ($roteiro->status === 'draft')
                <form action="{{ route('roteiros.submit', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn primary text-xs py-2.5 px-5">
                        {{ __('Submeter para Aprovação do Cliente') }}
                    </button>
                </form>
            @elseif ($roteiro->status === 'approved' && $roteiro->status !== 'in_production')
                <form action="{{ route('roteiros.mark-in-production', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn primary text-xs py-2.5 px-5">
                        {{ __('Marcar como Em Produção') }}
                    </button>
                </form>
            @endif
        @endcan

        @can('approve', $roteiro)
            @if ($roteiro->status === 'submitted')
                <form action="{{ route('roteiros.approve', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn primary text-xs py-2.5 px-5">
                        {{ __('Aprovar Roteiro') }}
                    </button>
                </form>
            @endif
        @endcan
    </div>

</div>
@endsection
