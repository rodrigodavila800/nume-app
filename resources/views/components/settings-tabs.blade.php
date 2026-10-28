@props(['active' => 'gateways'])

<div class="settings-tabs">
    <a href="{{ route('admin.settings.gateways') }}"
       id="tab-btn-gateways"
       class="settings-tab {{ $active === 'gateways' ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span>{{ __('Stripe & Google Login') }}</span>
    </a>

    <a href="{{ route('admin.settings.fiscal') }}"
       id="tab-btn-fiscal"
       class="settings-tab {{ $active === 'fiscal' || request()->routeIs('admin.settings.fiscal*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
        <span>{{ __('Perfil Fiscal') }}</span>
    </a>

    <a href="{{ route('admin.settings.redsys') }}"
       id="tab-btn-redsys"
       class="settings-tab {{ $active === 'redsys' || request()->routeIs('admin.settings.redsys*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        <span>{{ __('TPV Redsys (BBVA)') }}</span>
    </a>

    <a href="{{ route('admin.settings.bank-transfer') }}"
       id="tab-btn-bank-transfer"
       class="settings-tab {{ $active === 'bank_transfer' || request()->routeIs('admin.settings.bank-transfer*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
        <span>{{ __('Transferência (IBAN)') }}</span>
    </a>

    <a href="{{ route('admin.settings.platform') }}"
       id="tab-btn-platform"
       class="settings-tab {{ $active === 'platform' || request()->routeIs('admin.settings.platform*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
        <span>{{ __('Redes & SEO Sofi') }}</span>
    </a>

    <a href="{{ route('video_types.index') }}"
       id="tab-btn-video-types"
       class="settings-tab {{ $active === 'video_types' || request()->routeIs('video_types.*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
        <span>{{ __('Tipos de Vídeo') }}</span>
    </a>

    <a href="{{ route('ai_usage.index') }}"
       id="tab-btn-ai-usage"
       class="settings-tab {{ $active === 'ai_usage' || request()->routeIs('ai_usage.*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>{{ __('IA & Consumo') }}</span>
    </a>

    <a href="{{ route('api_keys.index') }}"
       id="tab-btn-api-keys"
       class="settings-tab {{ $active === 'api_keys' || request()->routeIs('api_keys.*') ? 'active' : '' }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
        <span>{{ __('API & MCP') }}</span>
    </a>
</div>
