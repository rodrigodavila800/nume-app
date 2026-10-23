@extends('layouts.app')

@section('title', __('NUME | Faturamento'))
@section('breadcrumb', __('Faturamento'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Faturamento & Assinatura') }}</h1>
        <p class="text-xs text-zinc-400 mt-1">{{ __('Gerencie o plano da sua produtora e status de subscrição na plataforma NUME.') }}</p>
    </div>

    @if (session('warning'))
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <div class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-orange-500">{{ __('Plano Atual') }}</span>
                <h2 class="text-xl font-extrabold text-white mt-0.5">{{ ucfirst($tenant->subscription_plan) }} Plan</h2>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ $tenant->isSubscriptionValid() ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                {{ $tenant->isSubscriptionValid() ? __('Ativo') : __('Expirado') }}
            </span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/80">
                <dt class="text-zinc-500 font-bold uppercase text-[10px]">{{ __('Produtora Vinculada') }}</dt>
                <dd class="text-sm font-bold text-white mt-1">{{ $tenant->name }}</dd>
            </div>
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800/80">
                <dt class="text-zinc-500 font-bold uppercase text-[10px]">{{ __('Validade do Plano') }}</dt>
                <dd class="text-sm font-bold text-white mt-1">
                    @if ($tenant->isSubscriptionValid())
                        <span class="text-emerald-400">{{ __('Válido até') }} {{ $tenant->subscription_expires_at->format('d/m/Y') }}</span>
                    @else
                        <span class="text-rose-400">{{ __('Expirada ou não iniciada') }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        <div class="pt-4 border-t border-zinc-800">
            <form action="{{ route('billing.checkout') }}" method="POST">
                @csrf
                <button type="submit" class="bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                    {{ $stripeCustomer ? __('Atualizar Assinatura') : __('Gerenciar Pagamento') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
