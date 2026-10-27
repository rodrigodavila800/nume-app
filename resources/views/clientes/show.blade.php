@extends('layouts.app')

@section('title', 'NUME | ' . ($cliente->company_name ?: $cliente->name))
@section('breadcrumb', __('Ficha 360º do Cliente'))

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-orange-600/20 border border-orange-500/30 text-orange-400 font-extrabold text-base flex items-center justify-center shrink-0">
                {{ substr($cliente->name, 0, 2) }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ $cliente->company_name ?: $cliente->name }}</h1>
                    @if ($cliente->brand_niche)
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20">
                            {{ $cliente->brand_niche }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-zinc-400 mt-1 flex flex-wrap items-center gap-3">
                    <span>👤 {{ $cliente->name }}</span>
                    <span class="font-mono">✉️ {{ $cliente->email }}</span>
                    @if ($cliente->phone)
                        <span class="font-mono">📱 {{ $cliente->phone }}</span>
                    @endif
                    @if ($cliente->website)
                        <a href="{{ $cliente->website }}" target="_blank" rel="noopener" class="text-orange-400 hover:underline font-mono">🌐 {{ $cliente->website }}</a>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('productions.create', ['cliente_id' => $cliente->id]) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-md shadow-orange-900/20">
                + {{ __('Nova Produção') }}
            </a>
            <a href="{{ route('quotes.create') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 text-xs font-bold transition">
                🧮 {{ __('Novo Orçamento') }}
            </a>
            @can('update', $cliente)
                <a href="{{ route('clientes.edit', $cliente) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 text-xs font-bold transition">
                    ✏️ {{ __('Editar Cadastro & Cotas') }}
                </a>
            @endcan
            <a href="{{ route('clientes.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white border border-zinc-800 text-xs font-bold transition">
                ← {{ __('Clientes') }}
            </a>
        </div>
    </div>

    <!-- Barra de Navegação Comercial -->
    <x-commercial-tabs active="clientes" />

    <!-- Esteira de Jornada do Cliente (5 Etapas) -->
    @php
        $hasDna = $cliente->hasCompletedBrandProfile();
        $hasAnamnesis = $cliente->hasCompletedAnamnesis();
        $planApproved = $latestPlan && $latestPlan->status === \App\Models\MarketingPlan::STATUS_APPROVED;
        $planNeedsAdjustment = $latestPlan && $latestPlan->status === \App\Models\MarketingPlan::STATUS_ADJUSTMENT;
        $hasActiveContract = $cliente->packPurchases->whereIn('status', ['paid', 'active'])->isNotEmpty() || $quotes->where('status', 'accepted')->isNotEmpty();
        $hasProductions = $cliente->productions->isNotEmpty();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 p-4 rounded-2xl bg-zinc-900 border border-zinc-800">
        <div class="p-3 rounded-xl border {{ $hasDna ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-zinc-950 border-zinc-800' }}">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider {{ $hasDna ? 'text-emerald-400' : 'text-zinc-500' }}">
                <span>1. DNA da Marca</span>
                <span>{{ $hasDna ? '✓' : '○' }}</span>
            </div>
            <p class="text-xs font-bold text-white mt-1">{{ $hasDna ? __('Preenchido') : __('Pendente no portal') }}</p>
        </div>

        <div class="p-3 rounded-xl border {{ $hasAnamnesis ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-zinc-950 border-zinc-800' }}">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider {{ $hasAnamnesis ? 'text-emerald-400' : 'text-zinc-500' }}">
                <span>2. Anamnese</span>
                <span>{{ $hasAnamnesis ? '✓' : '○' }}</span>
            </div>
            <p class="text-xs font-bold text-white mt-1">{{ $hasAnamnesis ? __('Respondida') : __('Aguardando cliente') }}</p>
        </div>

        <div class="p-3 rounded-xl border {{ $planApproved ? 'bg-emerald-950/20 border-emerald-500/30' : ($planNeedsAdjustment ? 'bg-amber-950/30 border-amber-500/40' : 'bg-zinc-950 border-zinc-800') }}">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider {{ $planApproved ? 'text-emerald-400' : ($planNeedsAdjustment ? 'text-amber-300' : 'text-zinc-500') }}">
                <span>3. Plano Marketing</span>
                <span>{{ $planApproved ? '✓' : ($planNeedsAdjustment ? '⚠️' : '⏳') }}</span>
            </div>
            <p class="text-xs font-bold text-white mt-1">
                @if ($planApproved)
                    {{ __('Aprovado pelo cliente') }}
                @elseif ($planNeedsAdjustment)
                    {{ __('Cliente pediu ajuste') }}
                @elseif ($latestPlan)
                    {{ __('Em revisão no portal') }}
                @else
                    {{ __('Ainda não gerado') }}
                @endif
            </p>
        </div>

        <div class="p-3 rounded-xl border {{ $hasActiveContract ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-zinc-950 border-zinc-800' }}">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider {{ $hasActiveContract ? 'text-emerald-400' : 'text-zinc-500' }}">
                <span>4. Pacote / Orçamento</span>
                <span>{{ $hasActiveContract ? '✓' : '○' }}</span>
            </div>
            <p class="text-xs font-bold text-white mt-1">{{ $hasActiveContract ? __('Contratado') : __('Em negociação') }}</p>
        </div>

        <div class="p-3 rounded-xl border {{ $hasProductions ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-zinc-950 border-zinc-800' }}">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider {{ $hasProductions ? 'text-emerald-400' : 'text-zinc-500' }}">
                <span>5. Produções</span>
                <span>{{ $hasProductions ? '✓' : '○' }}</span>
            </div>
            <p class="text-xs font-bold text-white mt-1">{{ __(':count na esteira', ['count' => $cliente->productions->count()]) }}</p>
        </div>
    </div>

    <!-- Alerta de Pedido de Ajuste no Plano de Marketing -->
    @if ($latestPlan && $latestPlan->status === \App\Models\MarketingPlan::STATUS_ADJUSTMENT)
        <div class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-lg">⚠️</span>
                    <h2 class="text-sm font-extrabold text-amber-300 uppercase tracking-wider">
                        {{ __('O cliente solicitou ajuste no Plano de Marketing (:mes)', ['mes' => $latestPlan->month_year]) }}
                    </h2>
                </div>
                <span class="text-[11px] font-semibold text-amber-400/90">
                    {{ __('A criação de novos roteiros aguarda a aprovação do plano.') }}
                </span>
            </div>
            <div class="p-3.5 rounded-xl bg-zinc-950/80 border border-amber-500/20 text-xs text-zinc-200 leading-relaxed">
                <strong class="text-amber-400 block mb-1">{{ __('Pedido do cliente:') }}</strong>
                {{ $latestPlan->adjustment_request }}
            </div>
            <form action="{{ route('clientes.marketing-plan.regenerate', $cliente) }}" method="POST" class="flex flex-col sm:flex-row gap-3 pt-1">
                @csrf
                <input type="text" name="adjustment_notes"
                       value="{{ $latestPlan->adjustment_request }}"
                       placeholder="{{ __('Orientação para a Sofi recalcular a grade...') }}"
                       class="flex-1 bg-zinc-950 border border-zinc-800 rounded-xl px-3.5 py-2 text-xs text-white focus:border-orange-500 focus:outline-none">
                <button type="submit"
                        class="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold uppercase tracking-wider transition shrink-0">
                    ✨ {{ __('Recalcular Grade com Sofi e Reenviar ao Cliente') }}
                </button>
            </form>
        </div>
    @endif

    <!-- Grid Principal: DNA + Anamnese + Redes/Cotas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Coluna 1: DNA da Marca & Estudo da Sofi -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>🧬</span>
                    <span>{{ __('DNA da Marca') }}</span>
                </h2>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $hasDna ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-800 text-zinc-500' }}">
                    {{ $hasDna ? __('Completo') : __('Incompleto') }}
                </span>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">{{ __('Nicho de Mercado') }}</span>
                    <p class="text-zinc-200 font-semibold mt-0.5">{{ $cliente->brand_niche ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">{{ __('Público-Alvo') }}</span>
                    <p class="text-zinc-300 mt-0.5 leading-relaxed">{{ $cliente->target_audience ?: '—' }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">{{ __('Diferenciais Competitivos') }}</span>
                    <p class="text-zinc-300 mt-0.5 leading-relaxed">{{ $cliente->key_differentials ?: '—' }}</p>
                </div>
                @if (! empty($cliente->brand_study['positioning_summary']))
                    <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800/80 space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-orange-400 block">✨ {{ __('Leitura Estratégica Sofi') }}</span>
                        <p class="text-[11px] text-zinc-300 leading-relaxed">{{ $cliente->brand_study['positioning_summary'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Coluna 2: Respostas da Anamnese -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>📋</span>
                    <span>{{ __('Anamnese Estratégica') }}</span>
                </h2>
                @if ($cliente->anamnesis_completed_at)
                    <span class="text-[10px] font-mono text-emerald-400">
                        {{ $cliente->anamnesis_completed_at->format('d/m/Y') }}
                    </span>
                @else
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-zinc-800 text-zinc-500">{{ __('Pendente') }}</span>
                @endif
            </div>

            @php $ans = $cliente->anamnesis ?? []; @endphp
            @if (! empty($ans))
                <div class="space-y-2.5 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800/80">
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Objetivo') }}</span>
                            <span class="text-white font-bold">{{ $anamnesisLabels['objectives'][$ans['objective'] ?? ''] ?? ($ans['objective'] ?? '—') }}</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800/80">
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Tom de Voz') }}</span>
                            <span class="text-white font-bold">{{ $anamnesisLabels['tones'][$ans['tone'] ?? ''] ?? ($ans['tone'] ?? '—') }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Oferta Principal & Ticket') }}</span>
                        <p class="text-zinc-200 mt-0.5">{{ $ans['main_offer'] ?? '—' }} @if(!empty($ans['average_ticket'])) <span class="text-orange-400 font-mono">({{ $ans['average_ticket'] }})</span> @endif</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Cliente Ideal') }}</span>
                        <p class="text-zinc-300 mt-0.5">{{ $ans['ideal_customer'] ?? '—' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-zinc-800/60">
                        <span class="text-[11px] text-zinc-400">
                            <strong>{{ __('Frequência:') }}</strong> {{ $anamnesisLabels['frequencies'][$ans['posting_frequency'] ?? ''] ?? '—' }}
                        </span>
                        @if (! empty($ans['wants_ads']))
                            <span class="px-2 py-0.5 rounded bg-orange-500/15 text-orange-300 border border-orange-500/30 text-[10px] font-bold">
                                📣 Ads {{ $ans['ads_monthly_budget'] ? '· ' . $ans['ads_monthly_budget'] : '' }}
                            </span>
                        @endif
                    </div>
                </div>
            @else
                <div class="p-6 rounded-xl bg-zinc-950 border border-zinc-800/80 text-center text-xs text-zinc-500">
                    {{ __('O cliente ainda não preencheu a anamnese no portal.') }}
                </div>
            @endif
        </div>

        <!-- Coluna 3: Redes Conectadas & Cota de Publicação -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <span>📡</span>
                    <span>{{ __('Redes & Cota de Publicação') }}</span>
                </h2>
                @can('update', $cliente)
                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-[11px] font-bold text-orange-400 hover:underline">
                        {{ __('Ajustar limites') }}
                    </a>
                @endcan
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                    <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Redes Conectadas') }}</span>
                    <p class="text-lg font-extrabold text-white font-mono mt-0.5">
                        {{ $limits['networks_connected'] }} / {{ $limits['networks_limit'] ?? '∞' }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                    <span class="text-[10px] font-bold uppercase text-zinc-500 block">{{ __('Envios no Mês') }}</span>
                    <p class="text-lg font-extrabold text-white font-mono mt-0.5">
                        {{ $limits['sends_this_month'] }} / {{ $limits['monthly_limit'] ?? '∞' }}
                    </p>
                </div>
            </div>

            <div class="space-y-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">{{ __('Canais Ativos da Marca') }}</span>
                @php $activeChannels = $cliente->activeSocialChannels(); @endphp
                @if (! empty($activeChannels))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($activeChannels as $net => $handle)
                            <span class="px-2.5 py-1 rounded-lg bg-zinc-950 border border-zinc-800 text-[11px] text-zinc-200 font-medium">
                                <strong class="uppercase text-orange-400">{{ $net }}</strong>{{ $handle ? ': ' . $handle : '' }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-zinc-500">{{ __('Nenhuma rede conectada ou informada ainda.') }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Plano de Marketing & Grade Editorial Atual -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-zinc-800 pb-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-base font-extrabold text-white">{{ __('Plano de Marketing & Grade Editorial') }}</h2>
                    @if ($latestPlan)
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $latestPlan->status === 'approved' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : ($latestPlan->status === 'adjustment_requested' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-sky-500/15 text-sky-400 border border-sky-500/30') }}">
                            {{ $latestPlan->status === 'approved' ? __('Aprovado') : ($latestPlan->status === 'adjustment_requested' ? __('Ajuste Solicitado') : __('Em Rascunho / Revisão')) }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-zinc-400 mt-0.5">
                    {{ $latestPlan ? $latestPlan->title . ' (' . $latestPlan->month_year . ')' : __('Gere ou recalcule a grade editorial do cliente com a Sofi IA.') }}
                </p>
            </div>

            <form action="{{ route('clientes.marketing-plan.regenerate', $cliente) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="px-3.5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 text-xs font-bold transition">
                    ✨ {{ $latestPlan ? __('Recalcular Grade com Sofi') : __('Gerar Plano com Sofi') }}
                </button>
            </form>
        </div>

        @if ($latestPlan && $latestPlan->items->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($latestPlan->items as $item)
                    <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/90 flex flex-col justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                                <span>{{ __('Semana :w · :p', ['w' => $item->week_number, 'p' => strtoupper($item->platform ?? 'REELS')]) }}</span>
                                @if ($item->videoType)
                                    <span class="text-orange-400">{{ $item->videoType->name }}</span>
                                @endif
                            </div>
                            <h3 class="text-xs font-bold text-white">{{ $item->topic }}</h3>
                            @if ($item->hook_idea)
                                <p class="text-[11px] text-zinc-400 line-clamp-2">🎯 {{ $item->hook_idea }}</p>
                            @endif
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-zinc-900 text-[11px]">
                            <span class="text-zinc-500">{{ $item->funnel_stage ? strtoupper($item->funnel_stage) : 'CONTEÚDO' }}</span>
                            @if ($item->production)
                                <a href="{{ route('productions.show', $item->production) }}" class="text-emerald-400 hover:underline font-bold">
                                    🎬 {{ __('Ver Produção →') }}
                                </a>
                            @elseif ($latestPlan->isApproved())
                                <form action="{{ route('clientes.marketing-plan.convert-item', [$cliente, $item]) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white border border-orange-500/30 font-bold text-[10px] transition cursor-pointer">
                                        🎬 {{ __('Gerar Produção & Roteiro →') }}
                                    </button>
                                </form>
                            @else
                                <span class="text-zinc-600">{{ __('Aguardando aprovação do plano') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 rounded-xl bg-zinc-950 border border-zinc-800/80 text-center text-xs text-zinc-500">
                {{ __('Nenhum plano de marketing gerado para este cliente ainda.') }}
            </div>
        @endif
    </div>

    <!-- Produções & Orçamentos do Cliente -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Produções do Cliente -->
        <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <h2 class="text-sm font-extrabold text-white">🎬 {{ __('Produções do Cliente (:count)', ['count' => $cliente->productions->count()]) }}</h2>
                <a href="{{ route('productions.index', ['cliente_id' => $cliente->id]) }}" class="text-xs font-bold text-orange-400 hover:underline">
                    {{ __('Ver na esteira →') }}
                </a>
            </div>

            @if ($cliente->productions->isEmpty())
                <p class="text-xs text-zinc-500 py-4 text-center">{{ __('Nenhuma produção cadastrada para este cliente.') }}</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($cliente->productions->take(6) as $prod)
                        <a href="{{ route('productions.show', $prod) }}"
                           class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 flex items-center justify-between gap-3 transition block">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $prod->title }}</p>
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    ✍️ {{ $prod->roteiros_count }} {{ __('roteiros') }} · 🎬 {{ $prod->videos_count }} {{ __('vídeos') }}
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-zinc-800 text-zinc-300">
                                {{ $prod->status }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Orçamentos & Chamados de Suporte -->
        <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <h2 class="text-sm font-extrabold text-white">🧮 {{ __('Orçamentos & Propostas (:count)', ['count' => $quotes->count()]) }}</h2>
                <a href="{{ route('quotes.index', ['search' => $cliente->email]) }}" class="text-xs font-bold text-orange-400 hover:underline">
                    {{ __('Ver todos →') }}
                </a>
            </div>

            @if ($quotes->isEmpty())
                <p class="text-xs text-zinc-500 py-4 text-center">{{ __('Nenhum orçamento emitido ou solicitado por este cliente.') }}</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($quotes->take(5) as $quote)
                        <a href="{{ route('quotes.show', $quote) }}"
                           class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 flex items-center justify-between gap-3 transition block">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $quote->number }} — {{ $quote->project_title }}</p>
                                <p class="text-[11px] text-zinc-500 font-mono mt-0.5">
                                    € {{ number_format((float) $quote->total_amount, 2, ',', '.') }} · {{ $quote->created_at->format('d/m/Y') }}
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase {{ $quote->status === 'requested' ? 'bg-orange-500/20 text-orange-300 border border-orange-500/30' : ($quote->status === 'accepted' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-zinc-800 text-zinc-300') }}">
                                {{ $quote->status === 'requested' ? __('Pedido do Cliente') : $quote->status }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($tickets->isNotEmpty())
                <div class="pt-3 border-t border-zinc-800 space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">🛟 {{ __('Chamados de Suporte Recentes') }}</span>
                    @foreach ($tickets as $ticket)
                        <a href="{{ route('support.show', $ticket) }}"
                           class="p-3 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 flex items-center justify-between text-xs transition block">
                            <span class="text-zinc-200 font-medium truncate">{{ $ticket->subject }}</span>
                            <span class="text-[10px] font-bold uppercase text-orange-400 shrink-0 ml-2">{{ $ticket->status }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

