@props(['active' => 'packs'])

<div class="settings-tabs">
    <a href="{{ route('admin.plans.index') }}"
       class="settings-tab {{ $active === 'plans' || request()->routeIs('admin.plans*') ? 'active' : '' }}">
        <span>{{ __('Planos de Assinatura') }}</span>
    </a>

    <a href="{{ route('packs.index') }}"
       class="settings-tab {{ $active === 'packs' && !request()->routeIs('admin.plans*') ? 'active' : '' }}">
        <span>{{ __('Pacotes Avulsos') }}</span>
    </a>

    <a href="{{ route('addons.index') }}"
       class="settings-tab {{ $active === 'addons' ? 'active' : '' }}">
        <span>{{ __('Serviços & Add-ons') }}</span>
    </a>

    <a href="{{ route('categories.index') }}"
       class="settings-tab {{ $active === 'categories' ? 'active' : '' }}">
        <span>{{ __('Categorias & Tags') }}</span>
    </a>

    <a href="{{ route('showcase-videos.index') }}"
       class="settings-tab {{ $active === 'showcase' ? 'active' : '' }}">
        <span>{{ __('Portfólio / Vitrine') }}</span>
    </a>
</div>
