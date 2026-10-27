@extends('layouts.app')

@section('title', __('NUME | Categorias de Vídeos & Pacotes'))
@section('breadcrumb', __('Categorias'))

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                {{ __('Categorias de Vídeos & Pacotes') }}
            </h1>
            <p class="text-xs sm:text-sm text-zinc-400 mt-1">
                {{ __('Gerencie as taxonomias estratégicas utilizadas no portfólio 3D, formulários e catálogo de pacotes da sua produtora.') }}
            </p>
        </div>
        <button type="button" onclick="openCreateCategoryModal()"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-orange-900/30 transition">
            <span>+ {{ __('Nova Categoria') }}</span>
        </button>
    </div>

    <!-- Navegação de Abas do Catálogo & Cadastros -->
    <x-catalog-tabs active="categories" />

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabela de Categorias -->
    <div class="rounded-2xl bg-zinc-900 border border-zinc-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-300">
                <thead class="bg-zinc-950/80 text-[10px] font-extrabold uppercase tracking-wider text-zinc-500 border-b border-zinc-800">
                    <tr>
                        <th class="px-6 py-4">{{ __('Ordem') }}</th>
                        <th class="px-6 py-4">{{ __('Nome da Categoria') }}</th>
                        <th class="px-6 py-4">{{ __('Slug') }}</th>
                        <th class="px-6 py-4">{{ __('Vídeos no Portfólio') }}</th>
                        <th class="px-6 py-4">{{ __('Pacotes Ativos') }}</th>
                        <th class="px-6 py-4">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="px-6 py-4 font-mono text-zinc-400">
                                #{{ $category->sort_order }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-white">{{ $category->name }}</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-zinc-500">
                                {{ $category->slug }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-zinc-800 border border-zinc-700 text-zinc-300">
                                    {{ $category->showcase_videos_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-zinc-800 border border-zinc-700 text-zinc-300">
                                    {{ $category->packs_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($category->is_active)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        {{ __('Ativa') }}
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-zinc-800 text-zinc-500 border border-zinc-700">
                                        {{ __('Inativa') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="openEditCategoryModal({{ $category->toJson() }})"
                                            class="px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition text-xs font-semibold">
                                        {{ __('Editar') }}
                                    </button>
                                    @if($category->showcase_videos_count === 0 && $category->packs_count === 0)
                                        <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('{{ __('Deseja realmente remover esta categoria?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 transition text-xs font-semibold">
                                                {{ __('Excluir') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-zinc-500">
                                {{ __('Nenhuma categoria cadastrada.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Criação / Edição de Categoria -->
<div id="category-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="relative w-full max-w-md bg-zinc-900 border border-zinc-800 rounded-3xl p-6 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-800">
            <h3 id="modal-category-title" class="text-base font-bold text-white">{{ __('Nova Categoria') }}</h3>
            <button type="button" onclick="closeCategoryModal()" class="text-zinc-500 hover:text-zinc-300 text-sm">✕</button>
        </div>

        <form id="category-form" method="POST" action="{{ route('categories.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" id="category-form-method" value="POST">

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Nome da Categoria') }} <span class="text-orange-500">*</span>
                </label>
                <input type="text" name="name" id="cat-name" required
                       placeholder="Ex: Vídeos Imobiliários / Real Estate"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Ordem de Exibição') }}
                </label>
                <input type="number" name="sort_order" id="cat-sort-order" min="0" value="0"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 text-xs font-bold text-zinc-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="cat-is-active" value="1" checked
                           class="w-4 h-4 rounded border-zinc-700 bg-zinc-800 text-orange-600 focus:ring-orange-500">
                    <span>{{ __('Categoria Ativa (visível nos formulários e vitrine)') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800">
                <button type="button" onclick="closeCategoryModal()"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-400 hover:text-white transition">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white text-xs font-extrabold uppercase tracking-wider shadow-lg shadow-orange-900/30 transition">
                    {{ __('Salvar Categoria') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateCategoryModal() {
    document.getElementById('modal-category-title').textContent = "{{ __('Nova Categoria') }}";
    const form = document.getElementById('category-form');
    form.action = "{{ route('categories.store') }}";
    document.getElementById('category-form-method').value = "POST";
    document.getElementById('cat-name').value = "";
    document.getElementById('cat-sort-order').value = "0";
    document.getElementById('cat-is-active').checked = true;
    document.getElementById('category-modal').classList.remove('hidden');
}

function openEditCategoryModal(category) {
    document.getElementById('modal-category-title').textContent = "{{ __('Editar Categoria') }}";
    const form = document.getElementById('category-form');
    form.action = `/categories/${category.id}`;
    document.getElementById('category-form-method').value = "PUT";
    document.getElementById('cat-name').value = category.name || "";
    document.getElementById('cat-sort-order').value = category.sort_order || 0;
    document.getElementById('cat-is-active').checked = !!category.is_active;
    document.getElementById('category-modal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('category-modal').classList.add('hidden');
}
</script>
@endsection
