@props(['active' => 'gateways'])

<div class="flex items-center gap-2 p-1.5 bg-zinc-900/90 border border-zinc-800 rounded-2xl overflow-x-auto shadow-sm">
    <a href="{{ route('admin.settings.gateways') }}#gateways"
       onclick="if(typeof switchSettingTab === 'function') { switchSettingTab('gateways'); return false; }"
       id="tab-btn-gateways"
       class="settings-tab-btn flex-1 min-w-[170px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none cursor-pointer {{ $active === 'gateways' ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>💳</span>
        <span>{{ __('Gateways de Pagamento') }}</span>
    </a>

    <a href="{{ route('admin.settings.fiscal') }}"
       id="tab-btn-fiscal"
       class="settings-tab-btn flex-1 min-w-[170px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none cursor-pointer {{ $active === 'fiscal' || request()->routeIs('admin.settings.fiscal*') ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>⚖️</span>
        <span>{{ __('Perfil Fiscal & Impostos') }}</span>
    </a>

    <a href="{{ route('admin.settings.gateways') }}#oauth"
       onclick="if(typeof switchSettingTab === 'function') { switchSettingTab('oauth'); return false; }"
       id="tab-btn-oauth"
       class="settings-tab-btn flex-1 min-w-[170px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none cursor-pointer {{ $active === 'oauth' ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🔑</span>
        <span>{{ __('Google OAuth & APIs') }}</span>
    </a>

    <a href="{{ route('admin.settings.platform') }}"
       class="settings-tab-btn flex-1 min-w-[170px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none cursor-pointer {{ $active === 'platform' ? 'bg-orange-600 text-white shadow-sm font-extrabold' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🌐</span>
        <span>{{ __('Redes Sociais & SEO Sofi') }}</span>
    </a>
</div>
