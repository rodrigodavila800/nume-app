@extends('layouts.portal')

@section('title', __('Pacote Ativado com Sucesso | NUME Portal'))

@section('content')
<div class="max-w-lg mx-auto px-4 py-8 text-center space-y-6">
    <div class="bg-white rounded-2xl p-8 sm:p-10 border border-zinc-200/80 shadow-xl space-y-5">
        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mx-auto shadow-sm">
            ✓
        </div>
        
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-orange-600">{{ __('Contratação Confirmada') }}</span>
            <h1 class="text-2xl font-extrabold text-zinc-900">{{ __('Seu Pacote de Vídeos Está Ativo!') }}</h1>
            <p class="text-sm text-zinc-600 leading-relaxed">
                {{ __('O pagamento foi processado com sucesso pelo Stripe. A equipe da NUME Creative já foi notificada para início da elaboração dos roteiros.') }}
            </p>
        </div>

        <div class="pt-4 border-t border-zinc-100 flex flex-col sm:flex-row items-center justify-center gap-3">
            @if(isset($purchase) && $purchase->isPaid())
                <a href="{{ route('portal.purchases.invoice', $purchase) }}"
                   target="_blank"
                   class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider bg-zinc-900 hover:bg-zinc-800 text-white shadow-md transition-all transform active:scale-95 gap-2">
                    <span>📄</span>
                    <span>{{ __('Descargar Factura Oficial (PDF)') }}</span>
                </a>
            @endif

            <a href="{{ route('portal.dashboard') }}" 
               class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-900/20 transition-all transform active:scale-95">
                {{ __('Acompanhar Esteira de Produção') }} →
            </a>
        </div>
    </div>
</div>
@endsection
