<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Redirecionando para Pagamento Seguro | NUME') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.favicons')
</head>
<body class="h-full flex items-center justify-center p-4 bg-zinc-950 text-white font-sans antialiased">
    <div class="max-w-md w-full text-center space-y-6 p-8 rounded-3xl bg-zinc-900/90 border border-zinc-800 shadow-2xl backdrop-blur-xl">
        <!-- Logo ou Spinner -->
        <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
            <div class="absolute inset-0 rounded-full border-4 border-blue-500/20 animate-ping"></div>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-2xl shadow-lg shadow-blue-500/30">
                🔒
            </div>
        </div>

        <div class="space-y-2">
            <h1 class="text-xl font-extrabold tracking-tight text-white">
                {{ __('Conectando com o BBVA / Redsys') }}
            </h1>
            <p class="text-xs text-zinc-400">
                {{ __('Você está sendo transferido com segurança para o ambiente bancário protegido.') }}
            </p>
        </div>

        <!-- Resumo da Compra com IVA -->
        <div class="p-4 rounded-2xl bg-zinc-950/60 border border-zinc-800/80 text-left space-y-2 text-xs">
            <div class="flex justify-between text-zinc-400">
                <span>{{ __('Serviço:') }}</span>
                <span class="font-semibold text-white">{{ $pack?->name ?? ($purchase->quote?->project_title ?: __('Producción Audiovisual')) }}</span>
            </div>
            <div class="flex justify-between text-zinc-400">
                <span>{{ __('Base Imponible:') }}</span>
                <span class="font-mono text-zinc-300">€ {{ number_format($purchase->subtotal ?? $purchase->amount, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-400">
                <span>{{ __('IVA (21%):') }}</span>
                <span class="font-mono text-zinc-300">€ {{ number_format($purchase->tax_amount ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="pt-2 border-t border-zinc-800 flex justify-between font-bold text-white text-sm">
                <span>{{ __('Total:') }}</span>
                <span class="font-mono text-emerald-400">€ {{ number_format($purchase->total_amount ?? $purchase->amount, 2, ',', '.') }}</span>
            </div>
        </div>

        <!-- Formulário Auto-Submit Redsys -->
        <form id="redsys_form" action="{{ $payload['action_url'] }}" method="POST" class="space-y-4">
            <input type="hidden" name="Ds_SignatureVersion" value="{{ $payload['signature_version'] }}">
            <input type="hidden" name="Ds_MerchantParameters" value="{{ $payload['merchant_parameters'] }}">
            <input type="hidden" name="Ds_Signature" value="{{ $payload['signature'] }}">

            <p class="text-[11px] text-zinc-500">
                {{ __('Se você não for redirecionado em alguns segundos, clique no botão abaixo:') }}
            </p>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-500 active:scale-[0.98] text-white font-bold text-xs py-3 px-4 rounded-xl shadow-lg shadow-blue-900/30 transition-all flex items-center justify-center gap-2">
                <span>{{ __('Prosseguir para Pagamento Seguro') }}</span>
                <span>→</span>
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Auto submete o formulário em 300ms
            setTimeout(function () {
                var form = document.getElementById('redsys_form');
                if (form) {
                    form.submit();
                }
            }, 300);
        });
    </script>
</body>
</html>
