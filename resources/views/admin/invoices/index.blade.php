@extends('layouts.app')

@section('title', __('Facturación & Faturas Oficiales AEAT | NUME Admin'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 py-6">

    {{-- Header com Ação de Exportação --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-800 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    {{ __('Agencia Tributaria (AEAT)') }}
                </span>
                <span class="text-xs text-zinc-500 font-mono">Real Decreto 1619/2012</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mt-1">
                {{ __('Libro de Facturas & Conciliación Fiscal') }}
            </h1>
            <p class="text-sm text-zinc-400 mt-1 max-w-2xl">
                {{ __('Control de ingresos, base imponible e IVA repercutido (Modelo 303) procedentes de Bizum, Tarjeta BBVA Redsys y Stripe.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.invoices.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 transition active:scale-95">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>{{ __('Exportar CSV (Gestoría / Asesoría)') }}</span>
            </a>
        </div>
    </div>

    {{-- Cards de Métricas Fiscais --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6">
        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl relative overflow-hidden">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Total Facturado') }}</span>
                <span class="text-orange-400 bg-orange-500/10 border border-orange-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">Bruto</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl sm:text-3xl font-extrabold text-white">€ {{ number_format($metrics['total_billed'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ $metrics['count_paid'] }} {{ __('facturas liquidadas') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Base Imponible') }}</span>
                <span class="text-sky-400 bg-sky-500/10 border border-sky-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">Neto</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl sm:text-3xl font-extrabold text-white">€ {{ number_format($metrics['total_base'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ __('Ingresos reales antes de impuestos') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('IVA Repercutido') }}</span>
                <span class="text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">Mod. 303</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl sm:text-3xl font-extrabold text-emerald-400">€ {{ number_format($metrics['total_iva'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ __('Cuota de IVA 21% acumulada') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Retención IRPF') }}</span>
                <span class="text-purple-400 bg-purple-500/10 border border-purple-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">Mod. 111/130</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl sm:text-3xl font-extrabold text-purple-400">€ {{ number_format($metrics['total_irpf'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ __('Retenciones soportadas / autónomos') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl relative overflow-hidden">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Custodia Activa') }}</span>
                <span class="text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded-md font-mono text-[10px]">50/50</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl sm:text-3xl font-extrabold text-amber-400">€ {{ number_format($metrics['total_pending_balance'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1 font-medium">
                {{ $metrics['count_escrow_pending'] }} {{ __('saldos pendientes de entrega') }}
            </p>
        </div>
    </div>

    {{-- Filtros Avançados --}}
    <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
        <form method="GET" action="{{ route('admin.invoices.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 items-end">
            {{-- Busca --}}
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Buscar Factura, NIF o Cliente') }}
                </label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Ex: NUM-2026-00001, B12345678..."
                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white placeholder-zinc-500 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                </div>
            </div>

            {{-- Modalidade Comercial --}}
            <div>
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Modalidad') }}
                </label>
                <select name="billing_mode" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-zinc-200 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                    <option value="">{{ __('Todas las modalidades') }}</option>
                    <option value="pending_escrow" @selected(($filters['billing_mode'] ?? '') === 'pending_escrow')>Saldo Pendiente (Custodia)</option>
                    <option value="paid_escrow" @selected(($filters['billing_mode'] ?? '') === 'paid_escrow')>✓ 50/50 Quitado</option>
                    <option value="split_50_50" @selected(($filters['billing_mode'] ?? '') === 'split_50_50')>50/50 Split (Todos)</option>
                    <option value="full_upfront" @selected(($filters['billing_mode'] ?? '') === 'full_upfront')>100% Anticipado</option>
                    <option value="subscription" @selected(($filters['billing_mode'] ?? '') === 'subscription')>Planes Recurrentes</option>
                </select>
            </div>

            {{-- Trimestre Fiscal --}}
            <div>
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Trimestre (AEAT)') }}
                </label>
                <select name="quarter" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-zinc-200 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                    <option value="">{{ __('Todos los trimestres') }}</option>
                    <option value="1" @selected($filters['quarter'] === '1')>1T (Ene - Mar)</option>
                    <option value="2" @selected($filters['quarter'] === '2')>2T (Abr - Jun)</option>
                    <option value="3" @selected($filters['quarter'] === '3')>3T (Jul - Sep)</option>
                    <option value="4" @selected($filters['quarter'] === '4')>4T (Oct - Dic)</option>
                </select>
            </div>

            {{-- Pasarela de Pago --}}
            <div>
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Pasarela') }}
                </label>
                <select name="gateway" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-zinc-200 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                    <option value="">{{ __('Todas las pasarelas') }}</option>
                    <option value="bizum" @selected($filters['gateway'] === 'bizum')>Bizum (BBVA)</option>
                    <option value="redsys_card" @selected($filters['gateway'] === 'redsys_card')>Tarjeta (BBVA)</option>
                    <option value="stripe" @selected($filters['gateway'] === 'stripe')>Stripe</option>
                </select>
            </div>

            {{-- Estado --}}
            <div>
                <label class="block text-[11px] font-bold uppercase text-zinc-400 mb-1">
                    {{ __('Estado') }}
                </label>
                <select name="status" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-zinc-200 focus:bg-zinc-900 focus:border-orange-500 focus:outline-none transition">
                    <option value="paid" @selected($filters['status'] === 'paid')>{{ __('Pagadas / Liquidadas') }}</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>{{ __('Pendientes') }}</option>
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('Todas las operaciones') }}</option>
                </select>
            </div>

            {{-- Botões de Ação --}}
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="w-full bg-orange-600 hover:bg-orange-500 text-white font-bold py-2 px-3 rounded-xl text-xs transition active:scale-95 cursor-pointer">
                    {{ __('Filtrar') }}
                </button>
                @if(request()->hasAny(['search', 'quarter', 'gateway', 'month', 'billing_mode']))
                    <a href="{{ route('admin.invoices.index') }}"
                       class="p-2 text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-xl transition"
                       title="{{ __('Limpiar filtros') }}">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabela de Facturas --}}
    <div class="bg-zinc-900 rounded-2xl border border-zinc-800 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/60 text-zinc-400 uppercase font-bold tracking-wider border-b border-zinc-800 text-[10px]">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Nº Factura / Fecha') }}</th>
                        <th class="px-5 py-3.5">{{ __('Cliente / NIF') }}</th>
                        <th class="px-5 py-3.5">{{ __('Concepto') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Base Imp.') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('IVA (21%)') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Total (€)') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Canal') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 font-medium text-zinc-300">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-zinc-800/40 transition">
                            {{-- Nº Factura e Data --}}
                            <td class="px-5 py-4">
                                <div class="font-extrabold text-white font-mono text-xs">
                                    {{ $invoice->invoice_number ?? 'REC-' . str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}
                                </div>
                                <div class="text-[11px] text-zinc-400 mt-0.5">
                                    {{ ($invoice->paid_at ?? $invoice->invoice_generated_at ?? $invoice->created_at)?->format('d/m/Y H:i') }}
                                </div>
                            </td>

                            {{-- Cliente / NIF --}}
                            <td class="px-5 py-4">
                                <div class="font-bold text-white">
                                    {{ $invoice->billing_legal_name ?: ($invoice->cliente?->name ?? 'Cliente Final') }}
                                </div>
                                @if(!empty($invoice->billing_tax_id))
                                    <div class="text-[10px] text-orange-400 font-mono font-bold bg-orange-500/10 border border-orange-500/20 inline-block px-1.5 py-0.5 rounded mt-0.5">
                                        NIF: {{ $invoice->billing_tax_id }}
                                    </div>
                                @else
                                    <div class="text-[10px] text-zinc-400">
                                        {{ $invoice->cliente?->email }}
                                    </div>
                                @endif
                            </td>

                            {{-- Concepto --}}
                            <td class="px-5 py-4 max-w-xs">
                                <span class="font-semibold text-white block truncate">
                                    {{ $invoice->pack?->name ?? ($invoice->quote?->project_title ?? 'Presupuesto Audiovisual') }}
                                </span>
                                @if(!empty($invoice->addons_purchased))
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach((array)$invoice->addons_purchased as $addon)
                                            <span class="text-[9px] bg-zinc-800 text-zinc-300 border border-zinc-700 px-1 rounded">
                                                + {{ $addon }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Badges da Modalidade Comercial & Custódia --}}
                                @if($invoice->isSplit5050())
                                    <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            50/50 Split
                                        </span>
                                        @if($invoice->balance_status === 'pending')
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-orange-500/15 text-orange-300 border border-orange-500/30">
                                                {{ __('Saldo Pendente: € :amount', ['amount' => number_format($invoice->balance_amount, 2, ',', '.')]) }}
                                            </span>
                                        @elseif($invoice->balance_status === 'paid')
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ✓ {{ __('Saldo Quitado') }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($invoice->isPilotEntry())
                                    <div class="mt-1">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                            {{ __('Piloto de Entrada') }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            {{-- Base Imponible --}}
                            <td class="px-5 py-4 text-right font-mono font-semibold text-zinc-300">
                                € {{ number_format($invoice->subtotal ?? $invoice->amount, 2, ',', '.') }}
                            </td>

                            {{-- IVA --}}
                            <td class="px-5 py-4 text-right font-mono text-emerald-400 font-semibold">
                                € {{ number_format($invoice->tax_amount ?? 0, 2, ',', '.') }}
                                <span class="text-[10px] text-zinc-400 block font-sans">({{ number_format($invoice->tax_rate ?? 21, 0) }}%)</span>
                            </td>

                            {{-- Total --}}
                            <td class="px-5 py-4 text-right">
                                <div class="font-mono font-extrabold text-white text-sm">
                                    € {{ number_format($invoice->total_amount ?? $invoice->amount, 2, ',', '.') }}
                                </div>
                                @if($invoice->isSplit5050())
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                        {{ __('Entrada:') }} € {{ number_format($invoice->deposit_amount ?: $invoice->amount, 2, ',', '.') }}
                                    </div>
                                    @if($invoice->balance_amount > 0)
                                        <div class="text-[10px] {{ $invoice->balance_status === 'pending' ? 'text-amber-400 font-bold' : 'text-emerald-400' }} font-mono">
                                            {{ __('Saldo:') }} € {{ number_format($invoice->balance_amount, 2, ',', '.') }}
                                        </div>
                                    @endif
                                @endif
                            </td>

                            {{-- Canal / Pasarela --}}
                            <td class="px-5 py-4 text-center">
                                @php
                                    $gateway = strtolower($invoice->payment_gateway ?? 'stripe');
                                @endphp
                                @if($gateway === 'bizum')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                        Bizum
                                    </span>
                                @elseif($gateway === 'redsys_card')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        BBVA
                                    </span>
                                @elseif($gateway === 'bank_transfer')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        Transferencia
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                        Stripe
                                    </span>
                                @endif

                                @if($invoice->bank_transfer_reference)
                                    <div class="text-[9px] text-amber-400 font-mono mt-0.5">
                                        {{ $invoice->bank_transfer_reference }}
                                    </div>
                                @elseif($invoice->gateway_order_reference)
                                    <div class="text-[9px] text-zinc-400 font-mono mt-0.5">
                                        #{{ $invoice->gateway_order_reference }}
                                    </div>
                                @endif
                            </td>

                            {{-- Ações --}}
                            <td class="px-5 py-4 text-center">
                                @if($invoice->isPaid())
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <a href="{{ route('admin.invoices.download', $invoice) }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-zinc-800 hover:bg-orange-600 text-zinc-300 hover:text-white border border-zinc-700 hover:border-orange-500 transition shadow-sm"
                                           title="{{ __('Descargar Factura Oficial AEAT en PDF') }}">
                                            <svg class="w-3.5 h-3.5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                            </svg>
                                            <span>PDF</span>
                                        </a>
                                        @if($invoice->isBankTransfer() && $invoice->confirmed_at)
                                            <span class="text-[9px] text-zinc-400 block" title="{{ __('Confirmado por :user', ['user' => $invoice->confirmedBy?->name ?? 'Admin']) }}">
                                                ✓ {{ $invoice->confirmed_at->format('d/m/Y') }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($invoice->isBankTransfer() && $invoice->isExpired())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        {{ __('Expirada') }}
                                    </span>
                                @elseif($invoice->isBankTransfer())
                                    <form action="{{ route('admin.purchases.confirm-bank-transfer', $invoice) }}" method="POST" onsubmit="return confirm('{{ __('Confirmar o recebimento de € :amount na conta bancária? Isso emitirá a fatura oficial e iniciará o projeto.', ['amount' => number_format((float) $invoice->total_amount, 2, ',', '.')]) }}');">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                                            ✓ {{ __('Confirmar') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-zinc-500 text-xs italic">{{ __('Pendiente') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-zinc-500">
                                <div class="text-3xl mb-2"></div>
                                <h3 class="text-sm font-bold text-white">{{ __('No se encontraron facturas emitidas') }}</h3>
                                <p class="text-xs text-zinc-400 max-w-sm mx-auto mt-1">
                                    {{ __('A medida que sus clientes contraten paquetes de vídeos a través de Bizum, Tarjeta o Stripe, las facturas se generarán automáticamente aquí.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="px-5 py-4 border-t border-zinc-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
