@extends('layouts.portal')

@section('title', __('Instruções para Transferência Bancária | NUME Portal'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 space-y-6">
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="w-14 h-14 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-2xl mx-auto shadow-sm">
                ⏳
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200">
                {{ __('Aguardando Pagamento') }}
            </span>
            <h1 class="text-2xl font-black text-zinc-900 tracking-tight">{{ __('Instruções para Transferência Bancária') }}</h1>
            <p class="text-xs text-zinc-600 max-w-md mx-auto">
                {{ __('Seu pedido foi registrado. Para liberar a produção dos seus vídeos, realize a transferência para a conta abaixo:') }}
            </p>
        </div>

        {{-- Referência & Valor em destaque --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-amber-50/60 border border-amber-200">
            <div>
                <span class="text-[10px] uppercase font-bold text-zinc-500 block tracking-wider">{{ __('Referência / Conceito') }}</span>
                <div class="mt-1">
                    <span class="text-base sm:text-lg font-mono font-black text-amber-900 select-all">{{ $purchase->bank_transfer_reference ?? ('BT-' . strtoupper(substr($purchase->id, 0, 8))) }}</span>
                </div>
                <span class="text-[10px] text-zinc-500 block mt-0.5">{{ __('Indique este código no comprovante') }}</span>
            </div>
            <div class="sm:text-right border-t sm:border-t-0 sm:border-l border-amber-200 pt-2 sm:pt-0 sm:pl-4">
                <span class="text-[10px] uppercase font-bold text-zinc-500 block tracking-wider">{{ __('Valor Total') }}</span>
                <span class="text-xl sm:text-2xl font-black text-zinc-900 block mt-0.5">
                    € {{ number_format((float) $purchase->total_amount, 2, ',', '.') }}
                </span>
                @if($purchase->expires_at)
                    <span class="text-[10px] text-amber-800 font-medium block mt-0.5">
                        {{ __('Válido até') }}: {{ $purchase->expires_at->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Dados Bancários --}}
        <div class="p-4 sm:p-5 rounded-xl bg-zinc-50 border border-zinc-200 space-y-3.5 text-xs text-zinc-700">
            <h2 class="font-bold text-zinc-900 flex items-center gap-2 text-sm border-b border-zinc-200 pb-2">
                <span>🏛️</span> {{ __('Dados da Conta da Produtora') }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <span class="text-[10px] uppercase font-bold text-zinc-500 block">{{ __('Beneficiário') }}</span>
                    <span class="font-bold text-zinc-900 block mt-0.5">{{ $accountHolder }}</span>
                </div>
                @if(!empty($bankName))
                    <div>
                        <span class="text-[10px] uppercase font-bold text-zinc-500 block">{{ __('Banco') }}</span>
                        <span class="font-bold text-zinc-900 block mt-0.5">{{ $bankName }}</span>
                    </div>
                @endif
            </div>

            <div>
                <span class="text-[10px] uppercase font-bold text-zinc-500 block">{{ __('IBAN') }}</span>
                <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-white border border-zinc-300 mt-1">
                    <span class="font-mono text-xs sm:text-sm font-bold text-zinc-900 select-all tracking-wider break-all">{{ $iban }}</span>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $iban }}'); alert('IBAN copiado!');" class="shrink-0 px-2.5 py-1 text-[11px] font-bold rounded-md bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition">
                        {{ __('Copiar') }}
                    </button>
                </div>
            </div>

            @if(!empty($bic))
                <div>
                    <span class="text-[10px] uppercase font-bold text-zinc-500 block">{{ __('Código SWIFT / BIC') }}</span>
                    <span class="font-mono font-bold text-zinc-900 block mt-0.5 tracking-wider">{{ $bic }}</span>
                </div>
            @endif

            @if(!empty($instructions))
                <div class="pt-2 border-t border-zinc-200">
                    <span class="text-[10px] uppercase font-bold text-zinc-500 block mb-1">{{ __('Observações Importantes') }}</span>
                    <p class="text-zinc-600 text-xs leading-relaxed whitespace-pre-line">{{ $instructions }}</p>
                </div>
            @endif
        </div>

        {{-- Pacote --}}
        <div class="p-3.5 rounded-xl bg-zinc-50 border border-zinc-200 text-xs flex items-center justify-between">
            <div>
                <span class="text-zinc-500 text-[11px] block">{{ __('Pacote Solicitado') }}</span>
                <span class="font-bold text-zinc-900">{{ $purchase->pack->name ?? __('Pacote Audiovisual') }}</span>
            </div>
            <span class="text-orange-600 font-bold">📦 {{ $purchase->video_quantity }} {{ __('vídeos') }}</span>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ route('portal.dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-900/20 transition-all transform active:scale-95">
                {{ __('Acompanhar no Painel') }} →
            </a>
            <a href="{{ route('portal.packs.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition">
                ← {{ __('Voltar para Pacotes') }}
            </a>
        </div>
    </div>
</div>
@endsection
