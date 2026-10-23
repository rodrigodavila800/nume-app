@extends('layouts.app')

@section('title', 'Pagamento confirmado - Nume App')

@section('content')
<div class="container mx-auto px-4 max-w-lg text-center">
    <div class="bg-white shadow rounded-lg p-8">
        <h1 class="text-2xl font-bold text-green-700 mb-2">Assinatura confirmada!</h1>
        <p class="text-gray-600 mb-6">Seu pagamento foi processado. O acesso será liberado assim que o webhook do Stripe confirmar a assinatura.</p>
        <a href="{{ route('productions.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
            Ir para Produções
        </a>
    </div>
</div>
@endsection
