<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($state = $paymentState ?? 'unknown')
    <title>{{ $state === 'paid' ? __('Contratação Confirmada') : __('Processando pagamento') }} | {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.favicons')
</head>
<body class="min-h-screen flex items-center justify-center bg-zinc-950 px-6 text-zinc-100 font-sans antialiased">
    <main class="max-w-md w-full rounded-3xl border border-zinc-800 bg-zinc-900 p-8 sm:p-10 text-center shadow-2xl space-y-6">
        @if($state === 'paid')
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl font-black mx-auto">
                ✓
            </div>

            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-widest text-orange-400">{{ __('Pagamento Confirmado') }}</span>
                <h1 class="text-2xl sm:text-3xl font-black text-white">{{ __('Seu pacote foi ativado!') }}</h1>
                <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                    {{ __('Sua conta de cliente está pronta. Acesse o portal para conferir seu saldo de vídeos, aprovar roteiros e acompanhar as gravações.') }}
                </p>
            </div>
        @else
            <div class="w-16 h-16 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center text-3xl font-black mx-auto" aria-hidden="true">
                …
            </div>

            <div class="space-y-2" role="status">
                <span class="text-xs font-bold uppercase tracking-widest text-orange-400">{{ __('Processando pagamento') }}</span>
                <h1 class="text-2xl sm:text-3xl font-black text-white">{{ __('Estamos confirmando seu pagamento') }}</h1>
                <p class="text-xs sm:text-sm text-zinc-400 leading-relaxed">
                    {{ __('Assim que o banco confirmar, seu pacote será ativado e você receberá a fatura por e-mail. Isso costuma levar poucos minutos; você pode acompanhar pelo portal.') }}
                </p>
            </div>
        @endif

        @if($state === 'paid' && isset($purchase) && $purchase)
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 text-left text-xs space-y-1">
                <p class="text-zinc-500 font-bold uppercase text-[10px]">{{ __('Pacote Contratado') }}</p>
                <p class="text-sm font-bold text-white">{{ $purchase->pack?->name ?? ($purchase->quote?->project_title ?? __('Pacote Audiovisual')) }}</p>
                <p class="text-orange-400 font-semibold mt-1">📦 {{ $purchase->video_quantity }} {{ __('vídeos inclusos para produção') }}</p>
            </div>
        @endif

        <div class="pt-2 space-y-3">
            <a href="{{ route('portal.dashboard') }}"
               class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-orange-600 px-6 py-3.5 text-xs font-extrabold uppercase tracking-wide text-white shadow-lg shadow-orange-900/30 hover:bg-orange-500 active:scale-95 transition">
                <span>{{ __('Acessar Meu Portal de Produção') }}</span>
                <span>→</span>
            </a>
            <a href="{{ route('storefront.show', $tenant) }}" class="block text-xs font-bold text-zinc-500 hover:text-zinc-300 transition">
                ← {{ __('Voltar para a vitrine') }}
            </a>
        </div>
    </main>
</body>
</html>
