@extends('layouts.portal')

@section('title', __('Orçamentos & Propostas | NUME Portal'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-200/80 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-50 text-orange-700 border border-orange-200">
                    {{ __('Propostas Comerciais') }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-zinc-900 tracking-tight mt-1">
                {{ __('Orçamentos & Propostas') }}
            </h1>
            <p class="text-xs sm:text-sm text-zinc-600 mt-1 max-w-2xl">
                {{ __('Revise orçamentos enviados pela produtora, aprove propostas online e acompanhe a evolução para ordens de gravação.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('portal.billing.edit') }}" class="text-xs font-bold text-zinc-600 hover:text-orange-600 transition">{{ __('Dados de faturação') }}</a>
            @if ($canRequest)
                <a href="{{ route('portal.quotes.request') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition">
                    {{ __('Pedir orçamento') }}
                </a>
            @endif
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-zinc-100 text-zinc-700 border border-zinc-200">
                <span>Total:</span>
                <strong class="text-zinc-900">{{ $quotes->total() }}</strong>
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl text-sm font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Listagem de Quotes --}}
    @if ($quotes->isEmpty())
        <div class="p-12 rounded-3xl bg-white border border-zinc-200 shadow-sm text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-zinc-100 border border-zinc-200 text-zinc-400 flex items-center justify-center mx-auto text-2xl">
                
            </div>
            <div class="space-y-1">
                <h3 class="text-base font-bold text-zinc-900">{{ __('Nenhum orçamento pendente') }}</h3>
                <p class="text-xs text-zinc-500 max-w-md mx-auto">
                    {{ __('Quando a produtora enviar uma proposta personalizada para sua marca, ela aparecerá aqui para aprovação com 1 clique.') }}
                </p>
            </div>
            <div class="pt-2">
                <a href="{{ route('portal.packs.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs bg-orange-600 hover:bg-orange-700 text-white shadow-xs transition">
                    <span>{{ __('Explorar Pacotes de Vídeos') }}</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($quotes as $quote)
                @php
                    $isExpired = $quote->isExpired();
                    $isAccepted = $quote->isAccepted();
                    $isRejected = $quote->isRejected();
                    $isPending = $quote->isSent() && ! $isExpired;
                @endphp

                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-zinc-200/80 shadow-xs hover:border-orange-200 transition space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-zinc-100">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="font-mono text-xs font-bold text-zinc-900 bg-zinc-100 px-2.5 py-1 rounded-lg border border-zinc-200">
                                #{{ $quote->number }}
                            </span>
                            <h2 class="text-base font-bold text-zinc-900">
                                {{ $quote->project_title ?: __('Produção Audiovisual') }}
                            </h2>
                        </div>

                        <div>
                            @if ($quote->isRequested())
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">
                                    {{ __('Em análise pela produtora') }}
                                </span>
                            @elseif ($isAccepted)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('Aprovado') }}
                                </span>
                            @elseif ($isRejected)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-zinc-100 text-zinc-600 border border-zinc-200">
                                    {{ __('Declinado') }}
                                </span>
                            @elseif ($isExpired)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    {{ __('Expirado') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                    {{ __('Aguardando Aprovação') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div class="space-y-1">
                            <span class="text-zinc-500 block">{{ __('Investimento Total:') }}</span>
                            @if ($quote->isRequested())
                                <span class="text-sm font-bold text-zinc-700 block">{{ __('A produtora está calculando') }}</span>
                            @else
                                <span class="text-base font-extrabold text-zinc-900 font-mono block">
                                    € {{ number_format($quote->total_amount, 2, ',', '.') }}
                                </span>
                                <span class="text-[11px] text-zinc-500 block">
                                    (€ {{ number_format($quote->subtotal, 2, ',', '.') }} + {{ number_format($quote->tax_rate, 0) }}% IVA)
                                </span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            <span class="text-zinc-500 block">{{ __('Condição de Pagamento:') }}</span>
                            @if ($quote->deposit_amount > 0 && $quote->balance_amount > 0)
                                <span class="font-bold text-zinc-800 block">
                                    {{ __('Sinal 50%') }}: € {{ number_format($quote->deposit_amount, 2, ',', '.') }}
                                </span>
                                <span class="text-[11px] text-zinc-500 block">
                                    {{ __('Restante') }}: € {{ number_format($quote->balance_amount, 2, ',', '.') }}
                                </span>
                            @else
                                <span class="font-bold text-zinc-800 block">
                                    {{ __('Pagamento integral na aprovação') }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            <span class="text-zinc-500 block">{{ __('Histórico & Prazos:') }}</span>
                            @if ($quote->sent_at)
                                <span class="text-zinc-700 block">
                                    {{ __('Enviado em') }}: {{ $quote->sent_at->format('d/m/Y') }}
                                </span>
                            @endif
                            @if ($quote->valid_until)
                                <span class="text-[11px] text-zinc-500 block">
                                    {{ __('Validade') }}: {{ $quote->valid_until->format('d/m/Y') }}
                                </span>
                            @endif
                            @if ($isAccepted && $quote->accepted_at)
                                <span class="text-[11px] text-emerald-700 font-medium block">
                                    {{ __('Aprovado em') }}: {{ $quote->accepted_at->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Barra de Ações --}}
                    <div class="pt-3 border-t border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs text-zinc-500 flex flex-wrap items-center gap-3">
                            @if ($quote->isAds())
                                <span>{{ __('Anúncios') }}</span>
                            @elseif ($quote->inputs && isset($quote->inputs['videos']))
                                <span>{{ $quote->inputs['videos'] }} {{ __('vídeos previstos') }}</span>
                            @endif
                            @foreach ($quote->packPurchases as $invoicePurchase)
                                @if ($invoicePurchase->isPaid())
                                    <a href="{{ route('portal.purchases.invoice', $invoicePurchase) }}"
                                       class="inline-flex items-center gap-1 font-bold text-orange-700 hover:text-orange-800">
                                        {{ __('Fatura :num (PDF)', ['num' => $invoicePurchase->invoice_number]) }}
                                    </a>
                                @endif
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($isPending && !empty($quote->approval_token))
                                <a href="{{ route('quotes.public.show', $quote->approval_token) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition">
                                    <span>{{ __('Revisar & Aprovar Proposta') }}</span>
                                    <span>&rarr;</span>
                                </a>
                            @elseif ($isAccepted && $quote->production_id)
                                <a href="{{ route('portal.productions.show', $quote->production_id) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition">
                                    <span>{{ __('Acompanhar Produção') }}</span>
                                    <span>&rarr;</span>
                                </a>
                                @if(!empty($quote->approval_token))
                                    <a href="{{ route('quotes.public.show', $quote->approval_token) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition">
                                        <span>{{ __('Ver Proposta') }}</span>
                                    </a>
                                @endif
                            @elseif (!empty($quote->approval_token))
                                <a href="{{ route('quotes.public.show', $quote->approval_token) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition">
                                    <span>{{ __('Ver Detalhes') }}</span>
                                    <span>&rarr;</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="pt-4">
                {{ $quotes->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
