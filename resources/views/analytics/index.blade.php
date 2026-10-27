@extends('layouts.app')

@section('title', __('NUME | BI & Dashboard de Vendas'))
@section('breadcrumb', __('Vendas & BI'))

@section('content')
<div class="space-y-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 mb-1">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ __('Dashboard de Vendas & Telemetria') }}</h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                    Sofi Copilot Engine
                </span>
            </div>
            <p class="text-xs text-zinc-400">
                {{ __('Acompanhe o faturamento, performance de pacotes de vídeo e diagnósticos preditivos de crescimento.') }}
            </p>
        </div>
    </div>

    <!-- 4 KPI Metrics (AkaDash Standard) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Gross Revenue -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col justify-between hover:border-zinc-700 transition">
            <div class="flex items-center justify-between text-zinc-400 mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Faturamento Bruto') }}</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-sm">
                    €
                </div>
            </div>
            <div>
                <p class="text-2xl font-black text-white tracking-tight">{{ number_format($kpis['grossRevenue'], 2, ',', '.') }} €</p>
                <div class="text-[11px] text-zinc-500 mt-1 flex flex-wrap items-center gap-1.5 font-mono">
                    <span class="text-emerald-400 font-bold font-sans">{{ $kpis['totalSalesCount'] }} {{ __('vendas') }}</span>
                    <span>·</span>
                    <span>Base: € {{ number_format($kpis['baseRevenue'] ?? $kpis['grossRevenue'], 2, ',', '.') }}</span>
                    @if(!empty($kpis['totalIva']) && $kpis['totalIva'] > 0)
                        <span>·</span>
                        <span class="text-zinc-400">IVA 21%: € {{ number_format($kpis['totalIva'], 2, ',', '.') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- KPI 2: Ticket Médio (AOV) -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col justify-between hover:border-zinc-700 transition">
            <div class="flex items-center justify-between text-zinc-400 mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Ticket Médio (AOV)') }}</span>
                <div class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-400 flex items-center justify-center font-bold text-sm">
                    
                </div>
            </div>
            <div>
                <p class="text-2xl font-black text-white tracking-tight">{{ number_format($kpis['averageTicket'], 2, ',', '.') }} €</p>
                <p class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5">
                    <span class="text-orange-400 font-bold">{{ __('Por Contrato') }}</span>
                    <span>·</span>
                    <span>{{ __('Catálogo Ativo') }}</span>
                </p>
            </div>
        </div>

        <!-- KPI 3: Taxa de Aprovação de Roteiros -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col justify-between hover:border-zinc-700 transition">
            <div class="flex items-center justify-between text-zinc-400 mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Eficiência de Roteiros') }}</span>
                <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-sm">
                    
                </div>
            </div>
            <div>
                <p class="text-2xl font-black text-white tracking-tight">{{ $kpis['approvalRate'] }}%</p>
                <p class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5">
                    <span class="text-blue-400 font-bold">{{ $pipeline['approvedRoteiros'] }} / {{ $pipeline['totalRoteiros'] }}</span>
                    <span>{{ __('aprovados') }}</span>
                </p>
            </div>
        </div>

        <!-- KPI 4: Clientes & Base Ativa -->
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col justify-between hover:border-zinc-700 transition">
            <div class="flex items-center justify-between text-zinc-400 mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Base de Clientes') }}</span>
                <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold text-sm">
                    
                </div>
            </div>
            <div>
                <p class="text-2xl font-black text-white tracking-tight">{{ $kpis['totalClients'] }}</p>
                <p class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5">
                    <span class="text-purple-400 font-bold">{{ $kpis['activeClientsCount'] }} {{ __('em produção') }}</span>
                    <span>·</span>
                    <span>{{ __('Retenção') }}</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Mix de Canais de Pagamento (Multi-Gateway BBVA & Stripe) -->
    @if(isset($gatewaysBreakdown))
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-300 flex items-center gap-2">
                        <span>{{ __('Mix de Meios de Pagamento & Liquidação') }}</span>
                    </h3>
                    <p class="text-[11px] text-zinc-500 mt-0.5">
                        {{ __('Distribuição das contratações recebidas via BBVA Redsys (Bizum e Tarjeta) e Stripe.') }}
                    </p>
                </div>
                <a href="{{ route('admin.invoices.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-400 hover:text-orange-300 transition">
                    <span>{{ __('Ver Livro de Facturas AEAT') }}</span>
                    <span>→</span>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Bizum -->
                <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg"></span>
                        <div>
                            <div class="text-xs font-bold text-white">Bizum (BBVA)</div>
                            <div class="text-[10px] text-zinc-500">{{ $gatewaysBreakdown['bizum']['count'] }} {{ __('vendas') }}</div>
                        </div>
                    </div>
                    <div class="text-right font-mono font-bold text-xs text-blue-400">
                        € {{ number_format($gatewaysBreakdown['bizum']['amount'], 2, ',', '.') }}
                    </div>
                </div>

                <!-- Tarjeta Redsys -->
                <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg"></span>
                        <div>
                            <div class="text-xs font-bold text-white">Tarjeta (BBVA)</div>
                            <div class="text-[10px] text-zinc-500">{{ $gatewaysBreakdown['redsys_card']['count'] }} {{ __('vendas') }}</div>
                        </div>
                    </div>
                    <div class="text-right font-mono font-bold text-xs text-emerald-400">
                        € {{ number_format($gatewaysBreakdown['redsys_card']['amount'], 2, ',', '.') }}
                    </div>
                </div>

                <!-- Stripe -->
                <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg"></span>
                        <div>
                            <div class="text-xs font-bold text-white">Stripe / Global</div>
                            <div class="text-[10px] text-zinc-500">{{ $gatewaysBreakdown['stripe']['count'] }} {{ __('vendas') }}</div>
                        </div>
                    </div>
                    <div class="text-right font-mono font-bold text-xs text-purple-400">
                        € {{ number_format($gatewaysBreakdown['stripe']['amount'], 2, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Sofi AI Copilot Intelligence Hub -->
    <div class="p-6 sm:p-8 rounded-2xl bg-gradient-to-b from-zinc-900 to-zinc-950 border border-orange-500/20 shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-zinc-800/80">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-orange-600/20 border border-orange-500/30 text-orange-400 flex items-center justify-center text-xl shrink-0">
                    
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                        <span>{{ __('Sofi AI Copilot — Diagnósticos & Decisões Estratégicas') }}</span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Análise automatizada de gargalos de vendas, upsell de clientes e táticas de prospecção.') }}
                    </p>
                </div>
            </div>
            <span class="text-xs text-zinc-500 font-mono self-start sm:self-auto">
                {{ __('Motor Preditivo v2.4') }}
            </span>
        </div>

        <!-- Insights List -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($sofiInsights as $insight)
                <div class="p-5 rounded-xl bg-zinc-900/80 border border-zinc-800 hover:border-zinc-700 flex flex-col justify-between space-y-4 transition group">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-zinc-800 text-orange-400 border border-zinc-700">
                                {{ $insight['category'] }}
                            </span>
                            @if($insight['urgency'] === 'alta')
                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping" title="Atenção Alta"></span>
                            @endif
                        </div>
                        <h3 class="text-sm font-bold text-white group-hover:text-orange-400 transition">{{ $insight['title'] }}</h3>
                        <p class="text-xs text-zinc-400 leading-relaxed">{{ $insight['description'] }}</p>
                    </div>

                    <a href="{{ $insight['action_url'] }}"
                       class="inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-zinc-800 hover:bg-orange-600 text-zinc-300 hover:text-white text-xs font-bold transition">
                        <span>{{ $insight['action_label'] }}</span>
                        <span>→</span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Performance dos Pacotes de Vídeos -->
    <div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-white">{{ __('Tração do Catálogo de Pacotes') }}</h2>
                <p class="text-xs text-zinc-400 mt-0.5">{{ __('Performance individual de cada oferta configurada para venda na vitrine.') }}</p>
            </div>
            <a href="{{ route('packs.create') }}"
               class="text-xs font-bold text-orange-400 hover:text-orange-300 transition">
                + {{ __('Novo Pacote') }}
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/60 border-b border-zinc-800 text-zinc-400 uppercase font-bold tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">{{ __('Pacote') }}</th>
                        <th class="px-4 py-3">{{ __('Preço Unitário') }}</th>
                        <th class="px-4 py-3">{{ __('Entregáveis') }}</th>
                        <th class="px-4 py-3">{{ __('Vendas Confirmadas') }}</th>
                        <th class="px-4 py-3">{{ __('Receita Gerada') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    @forelse ($packPerformance as $p)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="px-4 py-3.5 font-bold text-white">{{ $p['name'] }}</td>
                            <td class="px-4 py-3.5 font-mono text-zinc-300">{{ number_format($p['price'], 2, ',', '.') }} €</td>
                            <td class="px-4 py-3.5 text-zinc-400">{{ $p['video_quantity'] }} {{ __('vídeos') }}</td>
                            <td class="px-4 py-3.5 font-bold text-orange-400">{{ $p['sales_count'] }}</td>
                            <td class="px-4 py-3.5 font-bold text-emerald-400 font-mono">{{ number_format($p['revenue'], 2, ',', '.') }} €</td>
                            <td class="px-4 py-3.5 text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase {{ $p['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-800 text-zinc-400' }}">
                                    {{ $p['status'] === 'active' ? __('Ativo') : __('Inativo') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                {{ __('Nenhum pacote cadastrado ainda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
