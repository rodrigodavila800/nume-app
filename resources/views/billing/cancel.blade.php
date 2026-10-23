@extends('layouts.app')

@section('title', 'Pagamento cancelado - Nume App')

@section('content')
<div class="container mx-auto px-4 max-w-lg text-center">
    <div class="bg-white shadow rounded-lg p-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Assinatura não concluída</h1>
        <p class="text-gray-600 mb-6">Você cancelou o checkout. Pode tentar novamente quando quiser.</p>
        <a href="{{ route('billing.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
            Voltar para Faturamento
        </a>
    </div>
</div>
@endsection
