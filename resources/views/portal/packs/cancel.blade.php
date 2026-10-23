@extends('layouts.portal')

@section('title', 'Checkout Não Concluído | NUME Portal')

@section('content')
<div class="max-w-lg mx-auto px-4 py-8 text-center space-y-6">
    <div class="bg-white rounded-2xl p-8 sm:p-10 border border-zinc-200/80 shadow-xl space-y-5">
        <div class="w-16 h-16 rounded-full bg-zinc-100 text-zinc-500 flex items-center justify-center text-2xl mx-auto shadow-sm">
            ✕
        </div>
        
        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">Transação Cancelada</span>
            <h1 class="text-2xl font-extrabold text-zinc-900">Checkout Não Finalizado</h1>
            <p class="text-sm text-zinc-600 leading-relaxed">
                A operação foi interrompida antes do pagamento. Nenhuma cobrança foi realizada no seu cartão.
            </p>
        </div>

        <div class="pt-4 border-t border-zinc-100">
            <a href="{{ route('portal.packs.index') }}" 
               class="w-full inline-flex items-center justify-center px-6 py-3 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-900/20 transition-all transform active:scale-95">
                Voltar e Explorar Pacotes
            </a>
        </div>
    </div>
</div>
@endsection
