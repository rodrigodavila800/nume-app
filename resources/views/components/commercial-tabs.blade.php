@props(['active' => 'clientes'])

@php
    $pendingQuotesCount = \App\Models\Quote::where('status', 'requested')->count();
    $openTicketsCount = \App\Models\SupportTicket::whereIn('status', \App\Models\SupportTicket::OPEN_STATUSES)->count();
    $pendingPlansCount = \App\Models\MarketingPlan::where('status', \App\Models\MarketingPlan::STATUS_ADJUSTMENT)->count();
@endphp

<div class="flex items-center gap-2 p-1.5 bg-zinc-900/90 border border-zinc-800 rounded-2xl overflow-x-auto shadow-sm">
    <a href="{{ route('clientes.index') }}"
       class="flex-1 min-w-[150px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'clientes' || request()->routeIs('clientes*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>👥</span>
        <span>{{ __('Clientes (Ficha 360º)') }}</span>
        @if ($pendingPlansCount > 0)
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $active === 'clientes' || request()->routeIs('clientes*') ? 'bg-white text-orange-600' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                {{ $pendingPlansCount }}
            </span>
        @endif
    </a>

    <a href="{{ route('crm.leads.index') }}"
       class="flex-1 min-w-[150px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'crm' || request()->routeIs('crm*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>📈</span>
        <span>{{ __('CRM · Funil') }}</span>
    </a>

    <a href="{{ route('quotes.index') }}"
       class="flex-1 min-w-[150px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'quotes' || request()->routeIs('quotes*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🧮</span>
        <span>{{ __('Orçamentos & Propostas') }}</span>
        @if ($pendingQuotesCount > 0)
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $active === 'quotes' || request()->routeIs('quotes*') ? 'bg-white text-orange-600' : 'bg-orange-500/20 text-orange-300 border border-orange-500/30' }}">
                {{ $pendingQuotesCount }}
            </span>
        @endif
    </a>

    <a href="{{ route('support.index') }}"
       class="flex-1 min-w-[150px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'support' || request()->routeIs('support*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🛟</span>
        <span>{{ __('Suporte') }}</span>
        @if ($openTicketsCount > 0)
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $active === 'support' || request()->routeIs('support*') ? 'bg-white text-orange-600' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                {{ $openTicketsCount }}
            </span>
        @endif
    </a>
</div>

