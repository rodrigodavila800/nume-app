<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-950 text-zinc-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quote->project_title ?: 'Presupuesto' }} · {{ $tenant?->name ?? 'NUME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col justify-between py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto w-full">
        <!-- Header da Produtora -->
        <header class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-8 border-b border-zinc-800 gap-4">
            <div>
                <span class="inline-block text-xs font-bold uppercase tracking-wider text-orange-500 mb-1">
                    {{ __('Propuesta Audiovisual') }}
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    {{ $tenant?->name ?? 'NUME Creative Studio' }}
                </h1>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ __('Presupuesto Oficial') }} <span class="font-mono font-bold text-zinc-200">#{{ $quote->number }}</span>
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    {{ __('Válido hasta') }}: {{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : now()->addDays(15)->format('d/m/Y') }}
                </span>
            </div>
        </header>

        <!-- Mensagens de Flash / Feedback -->
        @if(session('error'))
            <div class="mt-6 p-4 rounded-xl bg-red-950/60 border border-red-800 text-red-200 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(session('info'))
            <div class="mt-6 p-4 rounded-xl bg-blue-950/60 border border-blue-800 text-blue-200 text-sm">
                {{ session('info') }}
            </div>
        @endif

        @if(request('canceled'))
            <div class="mt-6 p-4 rounded-xl bg-amber-950/60 border border-amber-800 text-amber-200 text-sm">
                {{ __('Operación de pago cancelada. Puedes intentarlo de nuevo o seleccionar otro método de pago abajo.') }}
            </div>
        @endif

        <!-- Card Principal da Proposta -->
        <main class="mt-8 space-y-8">
            <section class="bg-zinc-900/70 border border-zinc-800 rounded-2xl p-6 sm:p-8 backdrop-blur-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-zinc-800">
                    <div>
                        <h2 class="text-lg font-bold text-white">
                            {{ $quote->project_title ?: __('Producción Audiovisual Estratégica') }}
                        </h2>
                        <p class="text-sm text-zinc-400 mt-1">
                            {{ __('Preparado para') }}: <strong class="text-zinc-200">{{ $quote->client_name ?: __('Cliente') }}</strong>
                        </p>
                    </div>
                    @if($quote->client_email)
                        <div class="text-xs text-zinc-400">
                            {{ $quote->client_email }}
                        </div>
                    @endif
                </div>

                <!-- Resumo dos Valores -->
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-zinc-950/50 rounded-xl p-4 border border-zinc-800/80">
                        <span class="text-xs text-zinc-400 block">{{ __('Base Imponible (Subtotal)') }}</span>
                        <span class="text-xl font-bold font-mono text-zinc-100">
                            € {{ number_format($quote->subtotal, 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="bg-zinc-950/50 rounded-xl p-4 border border-zinc-800/80">
                        <span class="text-xs text-zinc-400 block">{{ __('Impuestos (IVA :rate%)', ['rate' => number_format($quote->tax_rate, 0)]) }}</span>
                        <span class="text-xl font-bold font-mono text-zinc-100">
                            € {{ number_format($quote->tax_amount, 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 bg-orange-950/20 border border-orange-500/30 rounded-xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="text-xs uppercase font-bold tracking-wider text-orange-400 block">{{ __('Importe Total con IVA') }}</span>
                        <span class="text-3xl font-extrabold font-mono text-white">
                            € {{ number_format($quote->total_amount, 2, ',', '.') }}
                        </span>
                    </div>

                    @if($quote->deposit_amount > 0 && $quote->balance_amount > 0 && ! $quote->isAds())
                        <div class="text-left sm:text-right">
                            <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded bg-orange-500/20 text-orange-300 border border-orange-500/30">
                                {{ __('Sinal del 50% para inicio') }}: <strong class="font-mono">€ {{ number_format($quote->deposit_amount, 2, ',', '.') }}</strong>
                            </span>
                            <span class="block text-xs text-zinc-400 mt-1">
                                {{ __('Restante a la entrega') }}: € {{ number_format($quote->balance_amount, 2, ',', '.') }}
                            </span>
                        </div>
                    @else
                        <div class="text-left sm:text-right">
                            <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded bg-zinc-800 text-zinc-300">
                                {{ __('Pago íntegro en la aprobación') }}
                            </span>
                        </div>
                    @endif
                </div>

                @if($quote->isAds() && ($quote->calculation['verba_total_info'] ?? 0) > 0)
                    <p class="mt-4 text-xs text-zinc-300 bg-zinc-950/60 border border-zinc-800 rounded-xl p-4">
                        {{ __('Inversión publicitaria prevista: € :amount. La pagas directamente a la plataforma de anuncios; no está incluida en este importe.', ['amount' => number_format($quote->calculation['verba_total_info'], 2, ',', '.')]) }}
                    </p>
                @endif
            </section>

            <!-- Checkout / Aprovação -->
            <section class="bg-zinc-900/70 border border-zinc-800 rounded-2xl p-6 sm:p-8 backdrop-blur-sm">
                <h3 class="text-base font-bold text-white mb-2">
                    {{ __('Aprobar Propuesta e Iniciar Grabación') }}
                </h3>
                <p class="text-xs text-zinc-400 mb-6">
                    {{ __('Selecciona la forma de pago para abonar el depósito/señal y confirmar la reserva de agenda del equipo:') }}
                </p>

                <form method="POST" action="{{ route('quotes.public.approve', $quote->approval_token) }}" class="space-y-6">
                    @csrf

                    @if($errors->any())
                        <div class="p-4 rounded-xl bg-red-950/60 border border-red-800 text-red-200 text-xs space-y-1">
                            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                        </div>
                    @endif

                    {{-- Datos de facturación: la factura sale con NIF y dirección. Nunca se muestran completos en esta página. --}}
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-white mb-2">{{ __('Datos de facturación') }}</legend>

                        @if($savedBilling)
                            <label class="flex items-center gap-2 text-xs text-zinc-300 cursor-pointer">
                                <input type="checkbox" name="use_saved_billing" value="1" checked class="rounded border-zinc-700 bg-zinc-950 text-orange-600">
                                <span>{{ __('Usar los datos de facturación ya registrados (NIF terminado en :tail)', ['tail' => $savedBilling['tax_tail']]) }}</span>
                            </label>
                            <p class="text-[11px] text-zinc-500">{{ __('Para cambiarlos, desmarca la casilla y rellena los datos nuevos.') }}</p>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="billing_type" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('Tipo') }}</label>
                                <select id="billing_type" name="billing_type" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                                    <option value="empresa" @selected(old('billing_type', 'empresa') === 'empresa')>{{ __('Empresa') }}</option>
                                    <option value="autonomo" @selected(old('billing_type') === 'autonomo')>{{ __('Autónomo') }}</option>
                                    <option value="particular" @selected(old('billing_type') === 'particular')>{{ __('Particular') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="billing_tax_id" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('NIF / CIF / NIE') }}</label>
                                <input id="billing_tax_id" name="billing_tax_id" maxlength="30" value="{{ old('billing_tax_id') }}" autocomplete="off" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="billing_legal_name" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('Razón social o nombre completo') }}</label>
                                <input id="billing_legal_name" name="billing_legal_name" maxlength="255" value="{{ old('billing_legal_name', $quote->client_name) }}" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="billing_address" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('Dirección') }}</label>
                                <input id="billing_address" name="billing_address" maxlength="255" value="{{ old('billing_address') }}" autocomplete="street-address" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label for="billing_postal_code" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('Código postal') }}</label>
                                <input id="billing_postal_code" name="billing_postal_code" maxlength="20" value="{{ old('billing_postal_code') }}" autocomplete="postal-code" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label for="billing_city" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1">{{ __('Ciudad') }}</label>
                                <input id="billing_city" name="billing_city" maxlength="100" value="{{ old('billing_city') }}" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <input type="hidden" name="billing_country" value="ES">
                        </div>
                        <p class="text-[11px] text-zinc-500">{{ __('Guardamos estos datos cifrados y solo los usamos para emitir tus facturas (RGPD).') }}</p>
                    </fieldset>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @if(!empty($activeMethods['stripe']))
                            <label class="relative flex flex-col p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-orange-500/60 transition group">
                                <input type="radio" name="payment_method" value="stripe" class="sr-only peer" checked>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-zinc-200 peer-checked:text-orange-400">
                                        {{ __('Tarjeta Débito / Crédito') }}
                                    </span>
                                    <span class="text-xs text-zinc-400">Stripe</span>
                                </div>
                                <span class="text-xs text-zinc-500 mt-2">
                                    {{ __('Pago seguro instantáneo con confirmación inmediata') }}
                                </span>
                                <div class="mt-3 w-4 h-4 rounded-full border border-zinc-700 peer-checked:border-orange-500 peer-checked:bg-orange-500 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white hidden peer-checked:block"></div>
                                </div>
                            </label>
                        @endif

                        @if(!empty($activeMethods['redsys_card']))
                            <label class="relative flex flex-col p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-orange-500/60 transition group">
                                <input type="radio" name="payment_method" value="redsys_card" class="sr-only peer" {{ empty($activeMethods['stripe']) ? 'checked' : '' }}>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-zinc-200 peer-checked:text-orange-400">
                                        {{ __('Tarjeta Bancaria') }}
                                    </span>
                                    <span class="text-xs text-zinc-400">Redsys TPV</span>
                                </div>
                                <span class="text-xs text-zinc-500 mt-2">
                                    {{ __('Pasarela bancaria con autenticación 3DSecure') }}
                                </span>
                                <div class="mt-3 w-4 h-4 rounded-full border border-zinc-700 peer-checked:border-orange-500 peer-checked:bg-orange-500 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white hidden peer-checked:block"></div>
                                </div>
                            </label>
                        @endif

                        @if(!empty($activeMethods['bizum']))
                            <label class="relative flex flex-col p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-orange-500/60 transition group">
                                <input type="radio" name="payment_method" value="bizum" class="sr-only peer">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-zinc-200 peer-checked:text-orange-400">
                                        Bizum
                                    </span>
                                    <span class="text-xs text-zinc-400">{{ __('Móvil') }}</span>
                                </div>
                                <span class="text-xs text-zinc-500 mt-2">
                                    {{ __('Abono rápido mediante tu número de teléfono') }}
                                </span>
                                <div class="mt-3 w-4 h-4 rounded-full border border-zinc-700 peer-checked:border-orange-500 peer-checked:bg-orange-500 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white hidden peer-checked:block"></div>
                                </div>
                            </label>
                        @endif

                        @if(!empty($activeMethods['bank_transfer']))
                            <label class="relative flex flex-col p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-orange-500/60 transition group">
                                <input type="radio" name="payment_method" value="bank_transfer" class="sr-only peer">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-zinc-200 peer-checked:text-orange-400">
                                        {{ __('Transferencia Bancaria') }}
                                    </span>
                                    <span class="text-xs text-zinc-400">IBAN</span>
                                </div>
                                <span class="text-xs text-zinc-500 mt-2">
                                    {{ __('Te facilitamos el IBAN y referencia única de pedido') }}
                                </span>
                                <div class="mt-3 w-4 h-4 rounded-full border border-zinc-700 peer-checked:border-orange-500 peer-checked:bg-orange-500 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white hidden peer-checked:block"></div>
                                </div>
                            </label>
                        @endif
                    </div>

                    @php
                        $toPayNow = $quote->deposit_amount > 0 ? $quote->deposit_amount : $quote->total_amount;
                    @endphp

                    <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-3.5 rounded-xl font-bold text-sm bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-600/30 transition">
                            {{ __('Aprobar y Abonar Señal') }} (€ {{ number_format($toPayNow, 2, ',', '.') }}) &rarr;
                        </button>
                    </div>
                </form>

                <div class="mt-8 pt-6 border-t border-zinc-800/80 flex items-center justify-between">
                    <span class="text-xs text-zinc-400">
                        {{ __('¿Deseas descartar o renegociar las condiciones?') }}
                    </span>
                    <form method="POST" action="{{ route('quotes.public.reject', $quote->approval_token) }}" onsubmit="return confirm('¿Confirmas que deseas rechazar este presupuesto?')">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-zinc-400 hover:text-red-400 underline transition">
                            {{ __('Rechazar propuesta') }}
                        </button>
                    </form>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="mt-12 text-center text-xs text-zinc-400">
            <p>{{ $tenant?->name ?? 'NUME Creative Studio' }} · {{ __('Producción Audiovisual Estratégica') }}</p>
        </footer>
    </div>
</body>
</html>
