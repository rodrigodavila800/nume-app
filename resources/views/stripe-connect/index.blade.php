@extends('layouts.app')

@section('title', __('NUME | Stripe Connect'))
@section('breadcrumb', __('Recebimentos'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Recebimento Direto (Stripe Connect)') }}</h1>
        <p class="text-xs text-zinc-400 mt-1">{{ __('Conecte sua conta de pagamento para receber o valor de pacotes vendidos na vitrine diretamente.') }}</p>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <div class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/80">
                <dt class="text-zinc-500 font-bold uppercase text-[10px]">{{ __('Status da Conexão') }}</dt>
                <dd class="text-sm font-bold text-white mt-1">{{ ucfirst($tenant->stripe_account_status ?? 'Pendente') }}</dd>
            </div>
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/80">
                <dt class="text-zinc-500 font-bold uppercase text-[10px]">{{ __('Pronto para Cobranças') }}</dt>
                <dd class="text-sm font-bold mt-1 {{ $tenant->canReceivePayments() ? 'text-emerald-400' : 'text-amber-400' }}">
                    {{ $tenant->canReceivePayments() ? '✅ ' . __('Habilitado') : '⚡ ' . __('Requer Conexão') }}
                </dd>
            </div>
        </div>

        <p class="text-xs text-zinc-400 leading-relaxed">
            {{ __('Ao conectar sua conta Stripe Express, os pagamentos dos clientes são creditados diretamente na sua conta bancária após a liquidação, com dedução automática da taxa de plataforma da NUME.') }}
        </p>

        <div class="pt-4 border-t border-zinc-800">
            <a href="{{ route('stripe-connect.connect') }}"
               class="inline-flex items-center gap-2 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                <span>{{ $tenant->stripe_account_id ? __('Continuar Configuração no Stripe') : __('Conectar Conta Stripe') }}</span>
                <span>→</span>
            </a>
        </div>
    </div>
</div>
@endsection
