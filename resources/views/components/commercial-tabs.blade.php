@props(['active' => 'clientes'])

@php
    $pendingQuotesCount = \App\Models\Quote::where('status', 'requested')->count();
    $openTicketsCount = \App\Models\SupportTicket::whereIn('status', \App\Models\SupportTicket::OPEN_STATUSES)->count();
    $pendingPlansCount = \App\Models\MarketingPlan::where('status', \App\Models\MarketingPlan::STATUS_ADJUSTMENT)->count();
@endphp

<div class="settings-tabs">
    <a href="{{ route('clientes.index') }}"
       class="settings-tab {{ $active === 'clientes' || request()->routeIs('clientes*') ? 'active' : '' }}">
        <span>{{ __('Clientes (Ficha 360º)') }}</span>
        @if ($pendingPlansCount > 0)
            <span class="nav-badge">{{ $pendingPlansCount }}</span>
        @endif
    </a>

    <a href="{{ route('crm.leads.index') }}"
       class="settings-tab {{ $active === 'crm' || request()->routeIs('crm*') ? 'active' : '' }}">
        <span>{{ __('CRM & Funil') }}</span>
    </a>

    <a href="{{ route('quotes.index') }}"
       class="settings-tab {{ $active === 'quotes' || request()->routeIs('quotes*') ? 'active' : '' }}">
        <span>{{ __('Orçamentos & Propostas') }}</span>
        @if ($pendingQuotesCount > 0)
            <span class="nav-badge">{{ $pendingQuotesCount }}</span>
        @endif
    </a>

    <a href="{{ route('support.index') }}"
       class="settings-tab {{ $active === 'support' || request()->routeIs('support*') ? 'active' : '' }}">
        <span>{{ __('Suporte') }}</span>
        @if ($openTicketsCount > 0)
            <span class="nav-badge">{{ $openTicketsCount }}</span>
        @endif
    </a>
</div>
