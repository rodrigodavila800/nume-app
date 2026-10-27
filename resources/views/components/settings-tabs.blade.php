@props(['active' => 'gateways'])

<div class="settings-tabs">
    <a href="{{ route('admin.settings.gateways') }}"
       id="tab-btn-gateways"
       class="settings-tab {{ $active === 'gateways' ? 'active' : '' }}">
        <span>{{ __('Stripe & Google Login') }}</span>
    </a>

    <a href="{{ route('admin.settings.fiscal') }}"
       id="tab-btn-fiscal"
       class="settings-tab {{ $active === 'fiscal' || request()->routeIs('admin.settings.fiscal*') ? 'active' : '' }}">
        <span>{{ __('Perfil Fiscal') }}</span>
    </a>

    <a href="{{ route('admin.settings.redsys') }}"
       id="tab-btn-redsys"
       class="settings-tab {{ $active === 'redsys' || request()->routeIs('admin.settings.redsys*') ? 'active' : '' }}">
        <span>{{ __('TPV Redsys (BBVA)') }}</span>
    </a>

    <a href="{{ route('admin.settings.bank-transfer') }}"
       id="tab-btn-bank-transfer"
       class="settings-tab {{ $active === 'bank_transfer' || request()->routeIs('admin.settings.bank-transfer*') ? 'active' : '' }}">
        <span>{{ __('Transferência (IBAN)') }}</span>
    </a>

    <a href="{{ route('admin.settings.platform') }}"
       id="tab-btn-platform"
       class="settings-tab {{ $active === 'platform' || request()->routeIs('admin.settings.platform*') ? 'active' : '' }}">
        <span>{{ __('Redes & SEO Sofi') }}</span>
    </a>

    <a href="{{ route('video_types.index') }}"
       id="tab-btn-video-types"
       class="settings-tab {{ $active === 'video_types' || request()->routeIs('video_types.*') ? 'active' : '' }}">
        <span>{{ __('Tipos de Vídeo') }}</span>
    </a>

    <a href="{{ route('ai_usage.index') }}"
       id="tab-btn-ai-usage"
       class="settings-tab {{ $active === 'ai_usage' || request()->routeIs('ai_usage.*') ? 'active' : '' }}">
        <span>{{ __('IA & Consumo') }}</span>
    </a>

    <a href="{{ route('api_keys.index') }}"
       id="tab-btn-api-keys"
       class="settings-tab {{ $active === 'api_keys' || request()->routeIs('api_keys.*') ? 'active' : '' }}">
        <span>{{ __('API & MCP') }}</span>
    </a>
</div>
