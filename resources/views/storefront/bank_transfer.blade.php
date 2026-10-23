<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Instruções para Transferência Bancária') }} | {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-zinc-950 px-4 py-8 text-zinc-100 font-sans antialiased">
    <main class="max-w-xl w-full rounded-3xl border border-zinc-800 bg-zinc-900 p-6 sm:p-8 text-left shadow-2xl space-y-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/20">
                ⏳ {{ __('Aguardando Pagamento') }}
            </span>
            <h1 class="text-2xl font-black text-white tracking-tight">{{ __('Instruções para Transferência') }}</h1>
            <p class="text-xs text-zinc-400 max-w-md mx-auto">
                {{ __('Para ativar seu pacote, faça a transferência bancária com os dados abaixo e inclua o código de referência no conceito.') }}
            </p>
        </div>

        {{-- Referência & Valor em destaque --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-2xl bg-zinc-950 border border-amber-500/30">
            <div>
                <span class="text-[10px] uppercase font-bold text-zinc-400 block tracking-wider">{{ __('Referência / Conceito') }}</span>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-base sm:text-lg font-mono font-extrabold text-amber-400 select-all">{{ $purchase->bank_transfer_reference ?? ('BT-' . strtoupper(substr($purchase->id, 0, 8))) }}</span>
                </div>
                <span class="text-[10px] text-zinc-400 block mt-0.5">{{ __('Obrigatório informar no comprovante') }}</span>
            </div>
            <div class="sm:text-right border-t sm:border-t-0 sm:border-l border-zinc-800 pt-2 sm:pt-0 sm:pl-3">
                <span class="text-[10px] uppercase font-bold text-zinc-400 block tracking-wider">{{ __('Valor Total a Transferir') }}</span>
                <span class="text-xl sm:text-2xl font-black text-white block mt-0.5">
                    € {{ number_format((float) $purchase->total_amount, 2, ',', '.') }}
                </span>
                @if($purchase->expires_at)
                    <span class="text-[10px] text-amber-400 block mt-0.5">
                        {{ __('Válido até') }}: {{ $purchase->expires_at->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Dados Bancários --}}
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-950/70 border border-zinc-800 space-y-3.5 text-xs">
            <h2 class="font-bold text-white flex items-center gap-2 text-sm border-b border-zinc-800 pb-2">
                <span>🏛️</span> {{ __('Dados da Conta da Produtora') }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <span class="text-[10px] uppercase font-bold text-zinc-400 block">{{ __('Beneficiário') }}</span>
                    <span class="font-semibold text-white block mt-0.5">{{ $accountHolder ?: $tenant->name }}</span>
                </div>
                @if(!empty($bankName))
                    <div>
                        <span class="text-[10px] uppercase font-bold text-zinc-400 block">{{ __('Banco') }}</span>
                        <span class="font-semibold text-white block mt-0.5">{{ $bankName }}</span>
                    </div>
                @endif
            </div>

            <div>
                <span class="text-[10px] uppercase font-bold text-zinc-400 block">{{ __('IBAN') }}</span>
                <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-zinc-900 border border-zinc-800 mt-1">
                    <span class="font-mono text-xs sm:text-sm font-bold text-emerald-400 select-all tracking-wider break-all">{{ $iban }}</span>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $iban }}'); alert('IBAN copiado!');" class="shrink-0 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-200 transition">
                        {{ __('Copiar') }}
                    </button>
                </div>
            </div>

            @if(!empty($bic))
                <div>
                    <span class="text-[10px] uppercase font-bold text-zinc-400 block">{{ __('Código SWIFT / BIC') }}</span>
                    <span class="font-mono font-bold text-zinc-200 block mt-0.5 tracking-wider">{{ $bic }}</span>
                </div>
            @endif

            @if(!empty($instructions))
                <div class="pt-2 border-t border-zinc-800">
                    <span class="text-[10px] uppercase font-bold text-zinc-400 block mb-1">{{ __('Observações da Produtora') }}</span>
                    <p class="text-zinc-300 text-xs leading-relaxed whitespace-pre-line">{{ $instructions }}</p>
                </div>
            @endif
        </div>

        {{-- Resumo do Pacote --}}
        <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 text-xs flex items-center justify-between">
            <div>
                <span class="text-zinc-400 text-[11px] block">{{ __('Pacote Contratado') }}</span>
                <span class="font-bold text-white">{{ $purchase->pack->name ?? __('Pacote Audiovisual') }}</span>
            </div>
            <span class="text-emerald-400 font-bold">📦 {{ $purchase->video_quantity }} {{ __('vídeos') }}</span>
        </div>

        <div class="pt-2 space-y-3">
            <a href="{{ route('portal.dashboard') }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-xs font-extrabold uppercase tracking-wide text-white shadow-lg shadow-emerald-900/30 hover:bg-emerald-500 active:scale-95 transition">
                <span>{{ __('Acessar Meu Portal de Produção') }}</span>
                <span>→</span>
            </a>
            <a href="{{ route('storefront.show', $tenant) }}" class="block text-center text-xs font-bold text-zinc-500 hover:text-zinc-300 transition">
                ← {{ __('Voltar para a vitrine') }}
            </a>
        </div>
    </main>
</body>
</html>
