@props(['active' => 'packs'])

<div class="flex items-center gap-2 p-1.5 bg-zinc-900/90 border border-zinc-800 rounded-2xl overflow-x-auto shadow-sm">
    <a href="{{ route('admin.plans.index') }}"
       class="flex-1 min-w-[145px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'plans' || request()->routeIs('admin.plans*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🔁</span>
        <span>{{ __('Planos de Assinatura') }}</span>
    </a>

    <a href="{{ route('packs.index') }}"
       class="flex-1 min-w-[145px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'packs' && !request()->routeIs('admin.plans*') ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>📦</span>
        <span>{{ __('Pacotes Avulsos') }}</span>
    </a>

    <a href="{{ route('addons.index') }}"
       class="flex-1 min-w-[145px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'addons' ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>⚡</span>
        <span>{{ __('Serviços & Add-ons') }}</span>
    </a>

    <a href="{{ route('categories.index') }}"
       class="flex-1 min-w-[145px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'categories' ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🏷️</span>
        <span>{{ __('Categorias & Tags') }}</span>
    </a>

    <a href="{{ route('showcase-videos.index') }}"
       class="flex-1 min-w-[145px] px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 select-none {{ $active === 'showcase' ? 'bg-orange-600 text-white shadow-md shadow-orange-900/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60' }}">
        <span>🎬</span>
        <span>{{ __('Portfólio / Vitrine') }}</span>
    </a>
</div>
