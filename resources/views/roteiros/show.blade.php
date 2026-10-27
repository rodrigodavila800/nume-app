@extends('layouts.app')

@section('title', __('NUME | Visualizar Roteiro'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Topo / Navegação e Status -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-800">
        <div>
            <a href="{{ route('roteiros.index', $production) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-400 hover:text-orange-400 transition mb-2">
                <span>←</span>
                <span>{{ __('Voltar para Roteiros da Produção') }}</span>
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    {{ __('Roteiro') }} #{{ $roteiro->id }}
                </h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider
                    @if($roteiro->status === 'draft') bg-zinc-800 text-zinc-300 border border-zinc-700
                    @elseif($roteiro->status === 'submitted') bg-amber-500/20 text-amber-300 border border-amber-500/40
                    @elseif($roteiro->status === 'approved') bg-emerald-500/20 text-emerald-300 border border-emerald-500/40
                    @elseif($roteiro->status === 'in_production') bg-orange-500/20 text-orange-300 border border-orange-500/40
                    @elseif($roteiro->status === 'rejected') bg-rose-500/20 text-rose-300 border border-rose-500/40
                    @else bg-blue-500/20 text-blue-300 border border-blue-500/40
                    @endif">
                    <span class="w-1.5 h-1.5 rounded-full 
                        @if($roteiro->status === 'draft') bg-zinc-400
                        @elseif($roteiro->status === 'submitted') bg-amber-400 animate-pulse
                        @elseif($roteiro->status === 'approved') bg-emerald-400
                        @elseif($roteiro->status === 'in_production') bg-orange-400 animate-pulse
                        @elseif($roteiro->status === 'rejected') bg-rose-400
                        @else bg-blue-400
                        @endif"></span>
                    {{ ucfirst(str_replace('_', ' ', $roteiro->status)) }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @can('update', $roteiro)
                @if (in_array($roteiro->status, ['draft', 'rejected']))
                    <a href="{{ route('roteiros.edit', ['production' => $production, 'roteiro' => $roteiro]) }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-900/20 transition cursor-pointer">
                        <span>✏️</span>
                        <span>{{ $roteiro->status === 'rejected' ? __('Aplicar Ajustes (Sofi ou Manual)') : __('Editar Roteiro (Sofi ou Manual)') }}</span>
                    </a>
                @elseif ($roteiro->status === 'submitted')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-amber-300 bg-amber-500/10 border border-amber-500/30" title="{{ __('Em revisão pelo cliente: edição bloqueada até o cliente aprovar ou sugerir mudanças') }}">
                        <span>⏳</span>
                        <span>{{ __('Em Revisão pelo Cliente (Edição Bloqueada)') }}</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-zinc-400 bg-zinc-800/80 border border-zinc-700/60" title="{{ __('Roteiro aprovado não permite edição direta') }}">
                        <span>🔒</span>
                        <span>{{ __('Bloqueado para Produção') }}</span>
                    </span>
                @endif
            @endcan
        </div>
    </div>

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-950/80 border border-rose-500/40 text-rose-200 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
            <span>✗</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-200 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-sm">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Cards de Metadados -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-zinc-900/90 border border-zinc-800 p-4 rounded-2xl shadow-sm">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Criado por') }}</span>
            <p class="text-sm sm:text-base font-extrabold text-white mt-1">
                {{ $roteiro->creator?->name ?? 'Admin NUME' }}
            </p>
        </div>
        <div class="bg-zinc-900/90 border border-zinc-800 p-4 rounded-2xl shadow-sm">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Produção Vinculada') }}</span>
            <p class="text-sm sm:text-base font-extrabold text-orange-400 mt-1 truncate" title="{{ $production->title }}">
                {{ $production->title }}
            </p>
        </div>
        <div class="bg-zinc-900/90 border border-zinc-800 p-4 rounded-2xl shadow-sm">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Data de Criação') }}</span>
            <p class="text-sm sm:text-base font-extrabold text-white mt-1">
                {{ $roteiro->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
    </div>

    <!-- Conteúdo do Roteiro (Leitor de Alto Contraste) -->
    <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
            <div class="flex items-center gap-2">
                <span class="text-base">🎬</span>
                <h2 class="text-lg font-extrabold text-white">{{ __('Estrutura do Roteiro') }}</h2>
            </div>
            <button type="button" 
                    onclick="navigator.clipboard.writeText(`{{ addslashes($roteiro->content) }}`); alert('{{ __('Roteiro copiado com sucesso!') }}');"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 transition cursor-pointer">
                <span>📋</span>
                <span>{{ __('Copiar Roteiro') }}</span>
            </button>
        </div>

        @include('roteiros._structure')

        @if($roteiro->plan_override_reason)
            <p class="text-xs text-amber-300 bg-amber-950/50 border border-amber-500/30 rounded-xl px-3 py-2">
                <span class="font-bold">{{ __('Criado sem plano aprovado.') }}</span> {{ $roteiro->plan_override_reason }}
            </p>
        @endif

        <!-- Bloco de Texto Formatado com Alto Contraste -->
        <div class="p-5 sm:p-6 bg-zinc-950 border border-zinc-800/80 rounded-xl font-sans text-sm sm:text-base text-zinc-100 whitespace-pre-wrap leading-relaxed selection:bg-orange-500 selection:text-white">
{{ $roteiro->content }}
        </div>
    </div>

    <!-- Status de Aprovação e Feedback -->
    @if ($roteiro->status === 'approved' || $roteiro->status === 'in_production')
        <div class="bg-emerald-950/40 border border-emerald-500/30 rounded-2xl p-5 text-emerald-200 text-xs sm:text-sm space-y-1">
            <p class="font-extrabold text-emerald-300 flex items-center gap-2">
                <span>✓</span> 
                <span>{{ __('Roteiro Aprovado para Gravação') }}</span>
            </p>
            <p class="text-zinc-400">
                {{ __('Aprovado por:') }} <strong class="text-white">{{ $roteiro->approved_by_cliente_id ? $roteiro->approverCliente?->name . ' (cliente)' : $roteiro->approver?->name }}</strong>
                @if($roteiro->approved_at)
                    · {{ __('em') }} <strong class="text-white">{{ $roteiro->approved_at->format('d/m/Y H:i') }}</strong>
                @endif
            </p>
        </div>
    @elseif ($roteiro->rejection_reason)
        <div class="bg-rose-950/40 border border-rose-500/30 rounded-2xl p-5 text-rose-200 text-xs sm:text-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <p class="font-extrabold text-rose-300 flex items-center gap-2">
                        <span>💬</span> 
                        <span>{{ __('O Cliente Sugeriu Mudanças neste Roteiro') }}</span>
                    </p>
                    <p class="text-zinc-400 text-xs mt-0.5">
                        {{ __('Solicitado por:') }} <strong class="text-white">{{ $roteiro->approved_by_cliente_id ? $roteiro->approverCliente?->name . ' (cliente)' : $roteiro->approver?->name }}</strong>
                    </p>
                </div>
                @can('update', $roteiro)
                    <a href="{{ route('roteiros.edit', ['production' => $production, 'roteiro' => $roteiro]) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold transition shrink-0">
                        ✨ {{ __('Reescrever com Sofi ou Ajustar Manualmente →') }}
                    </a>
                @endcan
            </div>
            <div class="p-3 bg-zinc-950 border border-rose-900/50 rounded-xl text-xs text-rose-200">
                {{ $roteiro->rejection_reason }}
            </div>
        </div>
    @elseif ($roteiro->submitted_at)
        <div class="bg-amber-950/40 border border-amber-500/30 rounded-2xl p-5 text-amber-200 text-xs sm:text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1">
                <p class="font-extrabold text-amber-300 flex items-center gap-2">
                    <span>⏳</span> 
                    <span>{{ __('Aguardando Revisão do Cliente no Portal') }}</span>
                </p>
                <p class="text-zinc-400 text-xs">
                    {{ __('Submetido em:') }} <strong class="text-white">{{ $roteiro->submitted_at->format('d/m/Y H:i') }}</strong> · {{ __('Enquanto está em revisão com o cliente, a edição manual fica bloqueada até o cliente aprovar ou sugerir mudanças.') }}
                </p>
            </div>
            <a href="{{ $roteiro->getWhatsAppShareUrl() }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shrink-0">
                <span>📲</span>
                <span>{{ __('Enviar Link no WhatsApp') }}</span>
            </a>
        </div>
    @endif

    <!-- Ações do Produtor / Staff -->
    <div class="flex items-center gap-3 pt-2 flex-wrap">
        @can('update', $roteiro)
            @if ($roteiro->status === 'draft')
                <form action="{{ route('roteiros.submit', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-900/20 transition cursor-pointer">
                        <span>🚀</span>
                        <span>{{ __('Submeter para Aprovação do Cliente') }}</span>
                    </button>
                </form>
            @elseif ($roteiro->status === 'approved' && $roteiro->status !== 'in_production')
                <form action="{{ route('roteiros.mark-in-production', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-900/20 transition cursor-pointer">
                        <span>🎬</span>
                        <span>{{ __('Marcar como Em Produção') }}</span>
                    </button>
                </form>
            @endif
        @endcan

        @can('approve', $roteiro)
            @if ($roteiro->status === 'submitted')
                <form action="{{ route('roteiros.approve', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition cursor-pointer">
                        <span>✓</span>
                        <span>{{ __('Aprovar Roteiro') }}</span>
                    </button>
                </form>
            @endif
        @endcan
    </div>

</div>
@endsection
