@extends('layouts.app')

@section('title', __('Contrato de Assinatura | NUME Admin'))
@section('breadcrumb', __('Contrato de Assinatura'))

@section('content')
<div class="max-w-6xl mx-auto space-y-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-zinc-800">
        <div>
            <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-400 hover:text-orange-400 transition mb-2">
                <span>←</span>
                <span>{{ __('Voltar para Assinaturas') }}</span>
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    {{ __('Contrato #SUB-') }}{{ str_pad($subscription->id, 5, '0', STR_PAD_LEFT) }}
                </h1>
                @php
                    $st = $subscription->subscription_status ?? ($subscription->status === 'paid' ? 'active' : 'pending');
                @endphp
                @if($st === 'active')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('Ativa') }}
                    </span>
                @elseif($st === 'canceled')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                        {{ __('Cancelada') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-zinc-800 text-zinc-300 border border-zinc-700">
                        {{ ucfirst($st) }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Assinado por') }} <strong class="text-white">{{ $subscription->cliente?->name }}</strong> ({{ $subscription->cliente?->company_name ?? 'Pessoa Física' }}) em {{ $subscription->created_at->format('d/m/Y H:i') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($st === 'active')
                <form action="{{ route('admin.subscriptions.renew', $subscription) }}" method="POST" onsubmit="return confirm('Confirmar avanço de ciclo e liberação de nova cota de vídeos?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-zinc-800 hover:bg-emerald-600 text-zinc-200 hover:text-white border border-zinc-700 hover:border-emerald-500 transition cursor-pointer">
                        <span>{{ __('Avançar Ciclo / Renovar Cota') }}</span>
                    </button>
                </form>

                <form action="{{ route('admin.subscriptions.cancel', $subscription) }}" method="POST" onsubmit="return confirm('Deseja realmente rescindir/cancelar esta assinatura de cliente?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-zinc-800 hover:bg-rose-600 text-zinc-200 hover:text-white border border-zinc-700 hover:border-rose-500 transition cursor-pointer">
                        <span>✕</span>
                        <span>{{ __('Cancelar Assinatura') }}</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Grid de Detalhes do Contrato --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Coluna 1: Dados do Cliente e Faturamento --}}
        <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-6 shadow-xl">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-orange-400 flex items-center gap-2">
                <span>{{ __('Dados do Cliente') }}</span>
            </h2>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-zinc-500 block uppercase font-bold text-[10px]">{{ __('Nome Completo') }}</span>
                    <span class="text-white font-semibold text-sm">{{ $subscription->cliente?->name }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block uppercase font-bold text-[10px]">{{ __('E-mail de Contato') }}</span>
                    <span class="text-zinc-300 font-mono">{{ $subscription->cliente?->email }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block uppercase font-bold text-[10px]">{{ __('Empresa / Razão Social') }}</span>
                    <span class="text-zinc-200">{{ $subscription->cliente?->company_name ?? 'Não informada' }}</span>
                </div>
                <div>
                    <span class="text-zinc-500 block uppercase font-bold text-[10px]">{{ __('NIF / CIF Fiscal') }}</span>
                    <span class="text-zinc-200 font-mono">{{ $subscription->billing_tax_id ?? $subscription->cliente?->nif ?? 'Não informado' }}</span>
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-800 space-y-3 text-xs">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider">{{ __('Faturamento & Pagamento') }}</h3>
                <div class="flex justify-between items-center py-1">
                    <span class="text-zinc-400">{{ __('Valor por Ciclo:') }}</span>
                    <span class="font-mono font-bold text-emerald-400 text-sm">€ {{ number_format($subscription->total_amount ?: $subscription->amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center py-1">
                    <span class="text-zinc-400">{{ __('Intervalo:') }}</span>
                    <span class="text-zinc-200 font-bold capitalize">{{ $subscription->subscription_interval === 'quarter' ? 'Trimestral' : 'Mensal' }}</span>
                </div>
                <div class="flex justify-between items-center py-1">
                    <span class="text-zinc-400">{{ __('Fidelidade Contratada:') }}</span>
                    <span class="text-zinc-200 font-bold">{{ $subscription->commitment_months ?? 1 }} {{ __('meses') }}</span>
                </div>
                <div class="flex justify-between items-center py-1">
                    <span class="text-zinc-400">{{ __('Próxima Renovação:') }}</span>
                    <span class="text-zinc-200 font-mono">{{ $subscription->subscription_renews_at ? $subscription->subscription_renews_at->format('d/m/Y') : 'N/A' }}</span>
                </div>
                @if($subscription->isPaid())
                    <div class="pt-2">
                        <a href="{{ route('admin.invoices.download', $subscription) }}"
                           class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-zinc-800 hover:bg-orange-600 text-zinc-300 hover:text-white text-xs font-bold transition">
                            <span>{{ __('Baixar Factura Oficial AEAT') }}</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Coluna 2 e 3: Cota de Vídeos e Pipeline de Produções --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Card de Cota de Vídeos --}}
            <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                    <div>
                        <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
                            <span>{{ __('Consumo de Cota de Vídeos — Ciclo ') }}{{ $subscription->subscription_cycle_current ?? 1 }} / {{ $subscription->subscription_cycles_total ?? $subscription->commitment_months ?? 1 }}</span>
                        </h2>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ __('Vídeos que o cliente tem direito de produzir e publicar durante o ciclo vigente.') }}</p>
                    </div>
                    <span class="text-xs font-mono font-bold text-orange-400 bg-orange-500/10 border border-orange-500/20 px-2.5 py-1 rounded-lg">
                        {{ $subscription->videos_used }} / {{ $subscription->video_quantity }} {{ __('usados') }}
                    </span>
                </div>

                {{-- Barra Grande de Progresso --}}
                <div class="space-y-1.5">
                    @php
                        $pct = $subscription->video_quantity > 0 ? min(100, round(($subscription->videos_used / $subscription->video_quantity) * 100)) : 0;
                    @endphp
                    <div class="w-full bg-zinc-950 rounded-full h-3 overflow-hidden p-0.5 border border-zinc-800">
                        <div class="bg-gradient-to-r from-orange-500 to-emerald-500 h-2 rounded-full transition-all duration-500 w-[{{ $pct }}%]"></div>
                    </div>
                    <div class="flex justify-between text-[11px] text-zinc-500">
                        <span>{{ $subscription->videos_used }} {{ __('produções abertas') }}</span>
                        <span class="font-bold text-emerald-400">{{ $subscription->videosRemaining() }} {{ __('vídeos disponíveis para gravação') }}</span>
                    </div>
                </div>
            </div>

            {{-- Lista de Produções Vinculadas --}}
            <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <span>{{ __('Produções Vinculadas a Este Contrato') }}</span>
                    </h3>
                    <a href="{{ route('productions.create', ['cliente_id' => $subscription->cliente_id, 'pack_purchase_id' => $subscription->id]) }}"
                       class="text-xs font-bold text-orange-400 hover:text-orange-300 transition">
                        + {{ __('Nova Produção da Cota') }}
                    </a>
                </div>

                @if($productions->isNotEmpty())
                    <div class="divide-y divide-zinc-800/60">
                        @foreach($productions as $prod)
                            <div class="py-3 flex items-center justify-between hover:bg-zinc-800/20 px-2 rounded-xl transition">
                                <div>
                                    <a href="{{ route('productions.show', $prod) }}" class="text-xs font-bold text-white hover:text-orange-400 transition">
                                        {{ $prod->title }}
                                    </a>
                                    <div class="text-[10px] text-zinc-500 mt-0.5">
                                        {{ $prod->videos->count() }} {{ __('vídeos') }} · {{ $prod->roteiros->count() }} {{ __('roteiros') }} · {{ $prod->created_at->format('d/m/Y') }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-zinc-800 text-zinc-300 border border-zinc-700">
                                        {{ ucfirst($prod->status) }}
                                    </span>
                                    <a href="{{ route('productions.show', $prod) }}" class="text-zinc-400 hover:text-white p-1 text-xs">
                                        →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center text-zinc-500 space-y-2">
                        <p class="text-xs">{{ __('Nenhuma produção foi aberta ainda dentro deste contrato.') }}</p>
                        <a href="{{ route('productions.create', ['cliente_id' => $subscription->cliente_id, 'pack_purchase_id' => $subscription->id]) }}"
                           class="inline-block text-xs font-bold text-orange-400 hover:underline">
                            + {{ __('Iniciar Primeira Produção para o Cliente') }}
                        </a>
                    </div>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection
