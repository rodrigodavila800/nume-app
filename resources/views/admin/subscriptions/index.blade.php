@extends('layouts.app')

@section('title', __('Assinaturas & Contratos de Clientes | NUME Admin'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-800 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    {{ __('Gestão de Assinaturas') }}
                </span>
                <span class="text-xs text-zinc-500 font-mono">Receita Recorrente & Cotas</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mt-1">
                {{ __('Assinaturas & Contratos dos Clientes') }}
            </h1>
            <p class="text-sm text-zinc-400 mt-1 max-w-2xl">
                {{ __('Acompanhe em tempo real os clientes sob contrato contínuo, MRR ativo, consumo de cotas de vídeos e ciclos de renovação.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.plans.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-800 transition active:scale-95">
                <span>{{ __('Gerenciar Catálogo de Planos') }}</span>
            </a>
        </div>
    </div>

    {{-- Cards de KPIs da Base de Assinantes --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl relative overflow-hidden">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('MRR Ativo (Mensal)') }}</span>
                <span class="text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">Recorrente</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-emerald-400 font-mono">€ {{ number_format($kpis['mrr'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ $kpis['active_count'] }} {{ __('contratos gerando receita contínua') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Assinaturas Ativas') }}</span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-white">{{ $kpis['active_count'] }}</span>
                <span class="text-xs text-zinc-500">/ {{ $kpis['total_contracts'] }} {{ __('total') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ __('Clientes com retenção ativa na produtora') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Cota de Vídeos / Ciclo') }}</span>
                <span class="text-orange-400 font-mono text-xs"></span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-white">{{ $kpis['total_quota'] }}</span>
                <span class="text-xs text-zinc-500">{{ __('vídeos contratados') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ __('Carga de trabalho programada no pipeline') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Consumo de Cotas') }}</span>
                <span class="text-purple-400 font-mono text-xs">{{ $kpis['total_quota'] > 0 ? round(($kpis['videos_used'] / $kpis['total_quota']) * 100) : 0 }}%</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-purple-400">{{ $kpis['videos_used'] }}</span>
                <span class="text-xs text-zinc-500">utilizados ({{ $kpis['videos_remaining'] }} restantes)</span>
            </div>
            {{-- Barra de Progresso --}}
            <div class="w-full bg-zinc-800 rounded-full h-1.5 mt-2 overflow-hidden">
                @php
                    $usagePct = $kpis['total_quota'] > 0 ? min(100, round(($kpis['videos_used'] / $kpis['total_quota']) * 100)) : 0;
                @endphp
                <div class="bg-gradient-to-r from-orange-500 to-purple-500 h-1.5 rounded-full transition-all duration-500 w-[{{ $usagePct }}%]"></div>
            </div>
        </div>
    </div>

    {{-- Filtros e Busca --}}
    <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
        <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            <div class="sm:col-span-6">
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Buscar Assinante ou Plano') }}
                </label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Buscar por nome do cliente, email, empresa ou plano...') }}"
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-zinc-500 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
            </div>

            <div class="sm:col-span-4">
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Status do Contrato') }}
                </label>
                <select name="status" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3.5 py-2.5 text-xs text-zinc-200 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                    <option value="all">{{ __('Todos os Contratos') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('Ativas (Em vigor)') }}</option>
                    <option value="past_due" @selected(request('status') === 'past_due')>{{ __('Pagamento Pendente') }}</option>
                    <option value="canceled" @selected(request('status') === 'canceled')>{{ __('Canceladas / Rescindidas') }}</option>
                    <option value="completed" @selected(request('status') === 'completed')>{{ __('Finalizadas (Ciclos Concluídos)') }}</option>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit"
                        class="w-full bg-orange-600 hover:bg-orange-500 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition active:scale-95 cursor-pointer shadow-md shadow-orange-900/20">
                    {{ __('Filtrar') }}
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.subscriptions.index') }}"
                       class="p-2.5 text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-xl transition"
                       title="{{ __('Limpar filtros') }}">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabela de Assinaturas e Contratos --}}
    <div class="bg-zinc-900 rounded-2xl border border-zinc-800 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/60 text-zinc-400 uppercase font-bold tracking-wider border-b border-zinc-800 text-[10px]">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Cliente / Assinante') }}</th>
                        <th class="px-5 py-3.5">{{ __('Plano & Intervalo') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Valor / Ciclo') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Ciclo Atual') }}</th>
                        <th class="px-5 py-3.5">{{ __('Cota de Vídeos') }}</th>
                        <th class="px-5 py-3.5">{{ __('Próxima Renovação') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Status') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 font-medium text-zinc-300">
                    @forelse ($subscriptions as $sub)
                        <tr class="hover:bg-zinc-800/30 transition">
                            {{-- Cliente / Assinante --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-orange-600/20 border border-orange-500/30 text-orange-400 font-extrabold text-xs flex items-center justify-center shrink-0">
                                        {{ substr($sub->cliente?->name ?? 'C', 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white">{{ $sub->cliente?->name ?? 'Cliente Removido' }}</div>
                                        <div class="text-[11px] text-zinc-400">{{ $sub->cliente?->company_name ?? $sub->cliente?->email }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Plano & Intervalo --}}
                            <td class="px-5 py-4">
                                <span class="font-bold text-white block">{{ $sub->pack?->name ?? 'Plano Customizado' }}</span>
                                <span class="inline-flex items-center gap-1 text-[10px] text-zinc-400 font-mono mt-0.5">
                                    {{ $sub->subscription_interval === 'quarter' ? __('Trimestral') : __('Mensal') }}
                                    · {{ $sub->commitment_months ?? 1 }}m {{ __('fidelidade') }}
                                </span>
                            </td>

                            {{-- Valor / Ciclo --}}
                            <td class="px-5 py-4 text-right font-mono font-extrabold text-emerald-400 text-sm">
                                € {{ number_format($sub->total_amount ?: $sub->amount, 2, ',', '.') }}
                            </td>

                            {{-- Ciclo Atual --}}
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-zinc-800 border border-zinc-700 text-zinc-300">
                                    {{ $sub->subscription_cycle_current ?? 1 }} / {{ $sub->subscription_cycles_total ?? $sub->commitment_months ?? 1 }}
                                </span>
                            </td>

                            {{-- Cota de Vídeos do Ciclo --}}
                            <td class="px-5 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-bold text-white">{{ $sub->videos_used }} / {{ $sub->video_quantity }}</span>
                                        <span class="text-zinc-500 font-mono">{{ $sub->videosRemaining() }} {{ __('restantes') }}</span>
                                    </div>
                                    <div class="w-28 bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                        @php
                                            $prog = $sub->video_quantity > 0 ? min(100, round(($sub->videos_used / $sub->video_quantity) * 100)) : 0;
                                            $progWidthClass = match(true) {
                                                $prog >= 100 => 'w-full',
                                                $prog >= 75 => 'w-3/4',
                                                $prog >= 50 => 'w-1/2',
                                                $prog >= 25 => 'w-1/4',
                                                $prog > 0 => 'w-1/12',
                                                default => 'w-0',
                                            };
                                        @endphp
                                        <div class="bg-orange-500 h-1.5 rounded-full transition-all duration-300 {{ $progWidthClass }}"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Próxima Renovação --}}
                            <td class="px-5 py-4 font-mono text-[11px]">
                                @if($sub->subscription_renews_at)
                                    <span class="text-zinc-300 flex items-center gap-1">
                                        <span>{{ $sub->subscription_renews_at->format('d/m/Y') }}</span>
                                    </span>
                                @else
                                    <span class="text-zinc-500 italic">{{ __('Sem renovação') }}</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-4 text-center">
                                @php
                                    $st = $sub->subscription_status ?? ($sub->status === 'paid' ? 'active' : 'pending');
                                @endphp
                                @if($st === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        {{ __('Ativa') }}
                                    </span>
                                @elseif($st === 'past_due')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        {{ __('Pendente') }}
                                    </span>
                                @elseif($st === 'canceled')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        {{ __('Cancelada') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-zinc-800 text-zinc-400 border border-zinc-700">
                                        {{ ucfirst($st) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Ações --}}
                            <td class="px-5 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.subscriptions.show', $sub) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-zinc-300 bg-zinc-800 hover:bg-orange-600 hover:text-white border border-zinc-700 transition"
                                       title="{{ __('Visualizar Contrato') }}">
                                        <span>{{ __('Contrato') }}</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-zinc-500">
                                <div class="text-3xl mb-2"></div>
                                <h3 class="text-sm font-bold text-white">{{ __('Nenhuma assinatura ativa encontrada') }}</h3>
                                <p class="text-xs text-zinc-400 max-w-sm mx-auto mt-1">
                                    {{ __('Quando seus clientes contratarem Planos de Vídeo Recorrentes na vitrine ou portal, os contratos aparecerão detalhados aqui.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="px-5 py-4 border-t border-zinc-800">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
