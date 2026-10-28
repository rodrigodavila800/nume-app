@props(['active' => 'clientes'])

@php
    $pendingQuotesCount = \App\Models\Quote::where('status', 'requested')->count();
    $openTicketsCount = \App\Models\SupportTicket::whereIn('status', \App\Models\SupportTicket::OPEN_STATUSES)->count();
    $pendingPlansCount = \App\Models\MarketingPlan::where('status', \App\Models\MarketingPlan::STATUS_ADJUSTMENT)->count();
@endphp

<div class="settings-tabs">
    <a href="{{ route('clientes.index') }}"
       class="settings-tab {{ $active === 'clientes' || request()->routeIs('clientes*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span>{{ __('Clientes (Ficha 360º)') }}</span>
        @if ($pendingPlansCount > 0)
            <span class="nav-badge">{{ $pendingPlansCount }}</span>
        @endif
    </a>

    <a href="{{ route('crm.leads.index') }}"
       class="settings-tab {{ $active === 'crm' || request()->routeIs('crm*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        <span>{{ __('CRM & Funil') }}</span>
    </a>

    <a href="{{ route('quotes.index') }}"
       class="settings-tab {{ $active === 'quotes' || request()->routeIs('quotes*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span>{{ __('Orçamentos & Propostas') }}</span>
        @if ($pendingQuotesCount > 0)
            <span class="nav-badge">{{ $pendingQuotesCount }}</span>
        @endif
    </a>

    <a href="{{ route('support.index') }}"
       class="settings-tab {{ $active === 'support' || request()->routeIs('support*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span>{{ __('Suporte') }}</span>
        @if ($openTicketsCount > 0)
            <span class="nav-badge">{{ $openTicketsCount }}</span>
        @endif
    </a>
</div>
