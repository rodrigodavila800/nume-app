@extends('layouts.app')

@section('title', __('NUME | Orçamentos & Propostas'))
@section('breadcrumb', __('Orçamentos'))

@section('content')
<div class="space-y-6">

    <!-- Header & Ações -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Orçamentos & Propostas Estratégicas') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Simulador de custos, horas e filmmakers com geração de proposta oficial em PDF e envio SMTP.') }}</p>
        </div>

        <div class="flex items-center gap-3">
            @can('create', App\Models\Quote::class)
                <a href="{{ route('quotes.create') }}"
                   class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Novo Orçamento') }}</span>
                </a>
                <a href="{{ route('quotes.create', ['kind' => 'ads']) }}"
                   class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-100 border border-zinc-700 transition-all">
                    <svg class="w-4 h-4 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    <span>{{ __('Orçamento de anúncios') }}</span>
                </a>
            @endcan
        </div>
    </div>

    <x-commercial-tabs active="quotes" />

    <!-- Cards de Métricas e Pipeline -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Total Emitidos') }}</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-white font-mono">{{ $metrics['total_count'] }}</span>
                    <span class="text-xs text-zinc-500">{{ __('orçamentos') }}</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-zinc-800 text-zinc-300 border border-zinc-700/60 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500">{{ __('Em Negociação') }}</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-amber-400 font-mono">{{ $metrics['sent_count'] }}</span>
                    <span class="text-xs text-zinc-500">{{ $metrics['draft_count'] }} rascunhos</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-500">{{ __('Aprovados') }}</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-emerald-400 font-mono">{{ $metrics['accepted_count'] }}</span>
                    <span class="text-xs text-emerald-500/80 font-bold font-mono">€ {{ number_format($metrics['accepted_volume'], 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-orange-500">{{ __('Pipeline Ativo') }}</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-orange-400 font-mono">€ {{ number_format($metrics['pipeline_volume'], 0, ',', '.') }}</span>
                    <span class="text-xs text-zinc-500">{{ __('em aberto') }}</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-orange-500/10 text-orange-400 border border-orange-500/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
    </div>

    <!-- Filtros & Busca -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-zinc-900/60 p-3 rounded-2xl border border-zinc-800/80">
        <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
            @php
                $statusTabs = [
                    'all' => __('Todos'),
                    'requested' => __('Pedidos de clientes').($metrics['requested_count'] ? ' ('.$metrics['requested_count'].')' : ''),
                    'draft' => __('Rascunho'),
                    'sent' => __('Enviados'),
                    'accepted' => __('Aprovados'),
                    'rejected' => __('Recusados'),
                ];
            @endphp
            @foreach($statusTabs as $tabKey => $tabLabel)
                <a href="{{ route('quotes.index', array_merge(request()->query(), ['status' => $tabKey, 'page' => 1])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $status === $tabKey ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}">
                    {{ $tabLabel }}
                </a>
            @endforeach
        </div>

        <form action="{{ route('quotes.index') }}" method="GET" class="w-full sm:w-72 flex items-center gap-2">
            @if($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="relative w-full">
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="{{ __('Buscar por cliente, projeto, nº...') }}"
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 pl-8">
                <svg class="w-3.5 h-3.5 text-zinc-500 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            @if($search)
                <a href="{{ route('quotes.index', ['status' => $status]) }}" class="text-xs text-zinc-500 hover:text-zinc-300">✕</a>
            @endif
        </form>
    </div>

    <!-- Tabela de Orçamentos -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl shadow-black/40">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/80 border-b border-zinc-800 text-[10px] uppercase font-bold text-zinc-500 tracking-wider">
                    <tr>
                        <th class="py-3 px-4">{{ __('Nº / Data') }}</th>
                        <th class="py-3 px-4">{{ __('Cliente & Projeto') }}</th>
                        <th class="py-3 px-4">{{ __('Escopo Estimado') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Subtotal') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Total com IVA') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    @forelse($quotes as $quote)
                        @php
                            $inputs = $quote->inputs ?? [];
                            $calc = $quote->calculation ?? [];
                            $statusClasses = match($quote->status) {
                                'accepted' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                'sent' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                'requested' => 'bg-sky-500/10 text-sky-300 border-sky-500/20',
                                default => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                            };
                            $statusLabel = match($quote->status) {
                                'accepted' => __('Aprovado'),
                                'sent' => __('Enviado'),
                                'rejected' => __('Recusado'),
                                'requested' => __('Pedido do cliente'),
                                default => __('Rascunho'),
                            };
                        @endphp
                        <tr class="hover:bg-zinc-800/40 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-mono font-bold text-white">{{ $quote->number }}</div>
                                <div class="text-[10px] text-zinc-500">{{ $quote->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-white">{{ $quote->client_name }}</div>
                                <div class="text-[11px] text-zinc-400 truncate max-w-xs">{{ $quote->project_title ?: 'Vídeos para redes sociais' }}</div>
                                @if($quote->client_email)
                                    <div class="text-[10px] text-zinc-500 font-mono">{{ $quote->client_email }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                @if($quote->isAds())
                                    <span class="px-2 py-0.5 rounded bg-zinc-950 border border-zinc-800 text-[10px] font-bold text-sky-300">
                                        {{ __('Anúncios') }} · {{ $inputs['criativos'] ?? 0 }} {{ __('criativos') }} · {{ $inputs['meses'] ?? 1 }} {{ __('mês(es)') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-zinc-950 border border-zinc-800 text-[10px] font-bold text-orange-400">
                                        {{ $inputs['videos'] ?? 1 }} {{ ($inputs['videos'] ?? 1) == 1 ? 'vídeo' : 'vídeos' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-zinc-950 border border-zinc-800 text-[10px] text-zinc-400 font-mono">
                                        {{ number_format($calc['horas'] ?? 0, 1) }}h
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-zinc-950 border border-zinc-800 text-[10px] text-zinc-400">
                                        {{ $inputs['filmmakers'] ?? 1 }} FM
                                    </span>
                                    @if(!empty($inputs['drone']))
                                        <span class="px-1.5 py-0.5 rounded bg-sky-950/40 text-sky-400 border border-sky-800/30 text-[9px] font-bold">
                                            Dron
                                        </span>
                                    @endif
                                @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-semibold text-zinc-300">
                                € {{ number_format($quote->subtotal, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-black text-orange-400 text-sm">
                                € {{ number_format($quote->total_amount, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-extrabold uppercase border {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('quotes.pdf', $quote) }}"
                                       class="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition"
                                       title="{{ __('Baixar Proposta PDF') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                    @can('update', $quote)
                                        <a href="{{ route('quotes.edit', $quote) }}"
                                           class="p-1.5 rounded-lg bg-zinc-800 hover:bg-orange-600 text-zinc-300 hover:text-white transition"
                                           title="{{ __('Editar & Simular') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @endcan
                                    @can('delete', $quote)
                                        <form action="{{ route('quotes.destroy', $quote) }}" method="POST" class="inline" onsubmit="return confirm('Remover este orçamento?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-rose-950 hover:text-rose-400 text-zinc-400 transition cursor-pointer" title="{{ __('Excluir') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-zinc-500">
                                <p class="text-sm font-semibold">{{ __('Nenhum orçamento encontrado.') }}</p>
                                <p class="text-xs text-zinc-600 mt-1">{{ __('Crie um novo orçamento simulando as horas e filmmakers do seu cliente.') }}</p>
                                @can('create', App\Models\Quote::class)
                                    <a href="{{ route('quotes.create') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition">
                                        + {{ __('Novo Orçamento') }}
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($quotes->hasPages())
            <div class="p-4 border-t border-zinc-800">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
