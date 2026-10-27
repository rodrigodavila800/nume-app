@extends('layouts.app')

@section('title', __('NUME | Serviços & Add-ons'))
@section('breadcrumb', __('Serviços & Add-ons'))

@section('content')
<div class="space-y-8">

    <!-- Header & Ações -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                {{ __('Catálogo de Serviços & Add-ons') }}
            </h1>
            <p class="text-xs sm:text-sm text-zinc-400 mt-1">
                {{ __('Gerencie os serviços adicionais e upsells de alta retenção que podem ser vinculados aos pacotes de vídeo da sua produtora.') }}
            </p>
        </div>
        <button type="button" onclick="openCreateAddonModal()"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-orange-900/30 transition">
            <span>+ {{ __('Novo Add-on') }}</span>
        </button>
    </div>

    <!-- Navegação de Abas do Catálogo & Cadastros -->
    <x-catalog-tabs active="addons" />

    <!-- Cards de Métricas -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Total de Add-ons') }}</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-2xl font-black text-white">{{ $stats['total'] }}</span>
                <span class="text-xs text-orange-400 font-semibold font-mono">Catálogo Central</span>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Add-ons Ativos') }}</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-2xl font-black text-emerald-400">{{ $stats['active'] }}</span>
                <span class="text-xs text-emerald-500/80 font-semibold font-mono">Disponíveis</span>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Preço Médio Padrão') }}</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-2xl font-black text-white">{{ number_format($stats['avg_price'], 0, ',', '.') }} €</span>
                <span class="text-xs text-zinc-400 font-semibold font-mono">+ IVA</span>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold flex items-center gap-2">
            <span>✕</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Tabela de Add-ons -->
    <div class="rounded-2xl bg-zinc-900 border border-zinc-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-300">
                <thead class="bg-zinc-950/80 text-[10px] font-extrabold uppercase tracking-wider text-zinc-500 border-b border-zinc-800">
                    <tr>
                        <th class="px-6 py-4">{{ __('Ordem') }}</th>
                        <th class="px-6 py-4">{{ __('Nome do Serviço') }}</th>
                        <th class="px-6 py-4">{{ __('Preço Padrão') }}</th>
                        <th class="px-6 py-4">{{ __('Pacotes Vinculados') }}</th>
                        <th class="px-6 py-4">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    @forelse ($addons as $addon)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="px-6 py-4 font-mono text-zinc-400">
                                #{{ $addon->sort_order }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span class="text-sm font-bold text-white flex items-center gap-1.5">
                                        <span class="text-orange-400 font-bold">+</span>
                                        {{ $addon->name }}
                                    </span>
                                    @if($addon->description)
                                        <p class="text-[11px] text-zinc-400 line-clamp-1">{{ $addon->description }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-sm text-white">
                                {{ number_format($addon->default_price, 0, ',', '.') }} {{ $addon->currency === 'eur' ? '€' : ($addon->currency === 'usd' ? '$' : 'R$') }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-zinc-800 border border-zinc-700 text-zinc-300">
                                    {{ $addon->packs_count }} {{ $addon->packs_count == 1 ? __('pacote') : __('pacotes') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('addons.toggle', $addon) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" title="{{ __('Clique para alternar status') }}"
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase transition border {{ $addon->is_active ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-zinc-800 text-zinc-500 border-zinc-700 hover:bg-zinc-700' }}">
                                        {{ $addon->is_active ? __('Ativo') : __('Inativo') }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="openEditAddonModal({{ htmlspecialchars($addon->toJson(), ENT_QUOTES, 'UTF-8') }})"
                                            class="px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition text-xs font-semibold">
                                        {{ __('Editar') }}
                                    </button>
                                    @if($addon->packs_count === 0)
                                        <form action="{{ route('addons.destroy', $addon) }}" method="POST" onsubmit="return confirm('{{ __('Deseja realmente remover este Add-on?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 transition text-xs font-semibold">
                                                {{ __('Excluir') }}
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" disabled title="{{ __('Não pode ser excluído pois está vinculado a pacotes.') }}" class="px-3 py-1.5 rounded-lg bg-zinc-800 text-zinc-600 border border-zinc-700/50 cursor-not-allowed transition text-xs font-semibold">
                                            {{ __('Excluir') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-500">
                                {{ __('Nenhum Add-on cadastrado no catálogo ainda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Criar Add-on -->
<div id="create-addon-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                <span class="text-orange-500 font-bold">+</span>
                {{ __('Cadastrar Novo Add-on') }}
            </h3>
            <button type="button" onclick="closeCreateAddonModal()" class="text-zinc-400 hover:text-white text-xs">✕</button>
        </div>

        <form action="{{ route('addons.store') }}" method="POST" class="space-y-4">
            @csrf

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Nome do Add-on') }} <span class="text-orange-500">*</span>
                </label>
                <input type="text" name="name" required placeholder="Ex: Composición con IA"
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Descrição Comercial (Opcional)') }}
                </label>
                <textarea name="description" rows="2" placeholder="Ex: Criação de elementos visuais, cenários e retoques avançados com inteligência artificial."
                          class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-orange-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Preço Padrão') }} <span class="text-orange-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-zinc-500">€</span>
                        <input type="number" step="0.01" min="0" name="default_price" required value="100.00"
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl pl-8 pr-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Ordem de Exibição') }}
                    </label>
                    <input type="number" name="sort_order" value="0" min="0"
                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="create-is-active" value="1" checked
                       class="w-4 h-4 rounded bg-zinc-950 border-zinc-800 text-orange-600 focus:ring-orange-500">
                <label for="create-is-active" class="text-xs text-zinc-300 font-semibold cursor-pointer">
                    {{ __('Ativar imediatamente no catálogo da produtora') }}
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800">
                <button type="button" onclick="closeCreateAddonModal()"
                        class="px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-bold transition">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold uppercase tracking-wider transition">
                    {{ __('Salvar Add-on') }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Add-on -->
<div id="edit-addon-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                <span class="text-orange-500"></span>
                {{ __('Editar Add-on') }}
            </h3>
            <button type="button" onclick="closeEditAddonModal()" class="text-zinc-400 hover:text-white text-xs">✕</button>
        </div>

        <form id="edit-addon-form" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Nome do Add-on') }} <span class="text-orange-500">*</span>
                </label>
                <input type="text" name="name" id="edit-addon-name" required
                       class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Descrição Comercial (Opcional)') }}
                </label>
                <textarea name="description" id="edit-addon-description" rows="2"
                          class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:border-orange-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Preço Padrão') }} <span class="text-orange-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-zinc-500">€</span>
                        <input type="number" step="0.01" min="0" name="default_price" id="edit-addon-price" required
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl pl-8 pr-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Ordem de Exibição') }}
                    </label>
                    <input type="number" name="sort_order" id="edit-addon-order" min="0"
                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="edit-is-active" value="1"
                       class="w-4 h-4 rounded bg-zinc-950 border-zinc-800 text-orange-600 focus:ring-orange-500">
                <label for="edit-is-active" class="text-xs text-zinc-300 font-semibold cursor-pointer">
                    {{ __('Add-on Ativo no Catálogo') }}
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800">
                <button type="button" onclick="closeEditAddonModal()"
                        class="px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-bold transition">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold uppercase tracking-wider transition">
                    {{ __('Salvar Alterações') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateAddonModal() {
    document.getElementById('create-addon-modal').classList.remove('hidden');
}
function closeCreateAddonModal() {
    document.getElementById('create-addon-modal').classList.add('hidden');
}

function openEditAddonModal(addon) {
    const form = document.getElementById('edit-addon-form');
    form.action = `/addons/${addon.id}`;
    document.getElementById('edit-addon-name').value = addon.name;
    document.getElementById('edit-addon-description').value = addon.description || '';
    document.getElementById('edit-addon-price').value = addon.default_price;
    document.getElementById('edit-addon-order').value = addon.sort_order;
    document.getElementById('edit-is-active').checked = !!addon.is_active;

    document.getElementById('edit-addon-modal').classList.remove('hidden');
}
function closeEditAddonModal() {
    document.getElementById('edit-addon-modal').classList.add('hidden');
}
</script>
@endsection
