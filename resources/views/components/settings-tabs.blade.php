@props(['active' => 'gateways'])

<div class="flex flex-wrap items-center gap-1.5 p-1.5 bg-zinc-900/90 border border-zinc-800 rounded-2xl shadow-sm">
    <a href="{{ route('admin.settings.gateways') }}"
       id="tab-btn-gateways"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'gateways' ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>💳</span>
        <span>{{ __('Stripe & Google Login') }}</span>
    </a>

    <a href="{{ route('admin.settings.fiscal') }}"
       id="tab-btn-fiscal"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'fiscal' || request()->routeIs('admin.settings.fiscal*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>⚖️</span>
        <span>{{ __('Perfil Fiscal') }}</span>
    </a>

    <a href="{{ route('admin.settings.redsys') }}"
       id="tab-btn-redsys"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'redsys' || request()->routeIs('admin.settings.redsys*') ? 'bg-blue-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🏦</span>
        <span>{{ __('TPV Redsys (BBVA)') }}</span>
    </a>

    <a href="{{ route('admin.settings.bank-transfer') }}"
       id="tab-btn-bank-transfer"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'bank_transfer' || request()->routeIs('admin.settings.bank-transfer*') ? 'bg-emerald-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🏛️</span>
        <span>{{ __('Transferência (IBAN)') }}</span>
    </a>

    <a href="{{ route('admin.settings.platform') }}"
       id="tab-btn-platform"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'platform' || request()->routeIs('admin.settings.platform*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🌐</span>
        <span>{{ __('Redes & SEO Sofi') }}</span>
    </a>

    <a href="{{ route('video_types.index') }}"
       id="tab-btn-video-types"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'video_types' || request()->routeIs('video_types.*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🎬</span>
        <span>{{ __('Tipos de Vídeo') }}</span>
    </a>

    <a href="{{ route('ai_usage.index') }}"
       id="tab-btn-ai-usage"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'ai_usage' || request()->routeIs('ai_usage.*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🤖</span>
        <span>{{ __('IA & Consumo') }}</span>
    </a>

    <a href="{{ route('api_keys.index') }}"
       id="tab-btn-api-keys"
       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 select-none cursor-pointer {{ $active === 'api_keys' || request()->routeIs('api_keys.*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🔑</span>
        <span>{{ __('API & MCP') }}</span>
    </a>
</div>
