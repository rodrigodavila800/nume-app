@extends('layouts.app')

@section('title', ($type->exists ? __('Editar tipo de vídeo') : __('Novo tipo de vídeo')) . ' | NUME')

@section('content')
@php
    $blocks = old('blocks', $type->blocks ?? []);
    $rows = max(count($blocks), 1);
@endphp
<div class="space-y-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div>
        <a href="{{ route('video_types.index') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition">← {{ __('Tipos de vídeo') }}</a>
        <h1 class="text-2xl font-black tracking-tight text-zinc-900 mt-1">
            {{ $type->exists ? __('Editar tipo de vídeo') : __('Novo tipo de vídeo') }}
        </h1>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ $type->exists ? route('video_types.update', $type) : route('video_types.store') }}" method="POST" class="space-y-6">
        @csrf
        @if($type->exists)
            @method('PUT')
        @endif

        <div class="bg-white rounded-2xl border border-zinc-200 shadow-xs p-5 grid grid-cols-1 sm:grid-cols-6 gap-4">
            <div class="sm:col-span-4">
                <label for="name" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Nome') }}</label>
                <input id="name" name="name" type="text" maxlength="80" required value="{{ old('name', $type->name) }}"
                    placeholder="{{ __('Ex.: Vendas, Posicionamento, Institucional, Podcast') }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
            </div>
            <div>
                <label for="duration_seconds" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Duração (s)') }}</label>
                <input id="duration_seconds" name="duration_seconds" type="number" min="5" max="3600" required value="{{ old('duration_seconds', $type->duration_seconds) }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
            </div>
            <div>
                <label for="sort_order" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Ordem') }}</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $type->sort_order ?? 0) }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
            </div>
            <div class="sm:col-span-6">
                <label for="description" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Descrição (aparece para o cliente)') }}</label>
                <textarea id="description" name="description" rows="2" maxlength="1000"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">{{ old('description', $type->description) }}</textarea>
            </div>
            <div class="sm:col-span-6">
                <label for="sofi_guidance" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Orientação para a Sofi') }}</label>
                <textarea id="sofi_guidance" name="sofi_guidance" rows="2" maxlength="1000"
                    placeholder="{{ __('Ex.: tom de conversa, mostrar o produto em uso, evitar termos técnicos') }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">{{ old('sofi_guidance', $type->sofi_guidance) }}</textarea>
            </div>
            <label class="sm:col-span-6 flex items-center gap-2 text-sm text-zinc-800 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active ?? true)) class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                <span>{{ __('Ativo (disponível para novos roteiros)') }}</span>
            </label>
        </div>

        <div class="bg-white rounded-2xl border border-zinc-200 shadow-xs p-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-zinc-900">{{ __('Blocos do roteiro') }}</h2>
                    <p class="text-[11px] text-zinc-500">{{ __('Em ordem, sem sobrepor e dentro da duração. Até :max blocos.', ['max' => \App\Models\VideoType::MAX_BLOCKS]) }}</p>
                </div>
                <button type="button" id="add-block"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-orange-700 bg-orange-50 hover:bg-orange-100 border border-orange-200 transition">
                    {{ __('Adicionar bloco') }}
                </button>
            </div>

            <div id="blocks" class="space-y-2">
                @for($i = 0; $i < $rows; $i++)
                    @php($block = $blocks[$i] ?? [])
                    <div class="block-row grid grid-cols-12 gap-2 items-start">
                        <input type="hidden" name="blocks[{{ $i }}][key]" value="{{ $block['key'] ?? '' }}">
                        <input type="text" name="blocks[{{ $i }}][name]" maxlength="60" value="{{ $block['name'] ?? '' }}" placeholder="{{ __('Nome do bloco') }}"
                            class="col-span-12 sm:col-span-3 text-sm px-3 py-2 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20">
                        <input type="number" name="blocks[{{ $i }}][start]" min="0" value="{{ $block['start'] ?? '' }}" placeholder="{{ __('Início') }}" aria-label="{{ __('Início (s)') }}"
                            class="col-span-3 sm:col-span-1 text-sm px-2 py-2 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20">
                        <input type="number" name="blocks[{{ $i }}][end]" min="0" value="{{ $block['end'] ?? '' }}" placeholder="{{ __('Fim') }}" aria-label="{{ __('Fim (s)') }}"
                            class="col-span-3 sm:col-span-1 text-sm px-2 py-2 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20">
                        <input type="text" name="blocks[{{ $i }}][purpose]" maxlength="300" value="{{ $block['purpose'] ?? '' }}" placeholder="{{ __('Função do bloco') }}"
                            class="col-span-5 sm:col-span-6 text-sm px-3 py-2 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20">
                        <button type="button" class="remove-block col-span-1 py-2 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition" aria-label="{{ __('Remover bloco') }}">✕</button>
                    </div>
                @endfor
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <a href="{{ route('video_types.index') }}"
                class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition">
                {{ __('Cancelar') }}
            </a>
            <button type="submit"
                class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Salvar') }}
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const container = document.getElementById('blocks');
    const addButton = document.getElementById('add-block');
    const max = {{ \App\Models\VideoType::MAX_BLOCKS }};

    function renumber() {
        container.querySelectorAll('.block-row').forEach((row, index) => {
            row.querySelectorAll('[name^="blocks["]').forEach((input) => {
                input.name = input.name.replace(/^blocks\[\d+\]/, 'blocks[' + index + ']');
            });
        });
        addButton.disabled = container.querySelectorAll('.block-row').length >= max;
    }

    addButton.addEventListener('click', () => {
        const rows = container.querySelectorAll('.block-row');
        if (rows.length >= max) return;
        const clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach((input) => { input.value = ''; });
        const previousEnd = rows[rows.length - 1].querySelector('[name$="[end]"]').value;
        clone.querySelector('[name$="[start]"]').value = previousEnd;
        container.appendChild(clone);
        renumber();
    });

    container.addEventListener('click', (event) => {
        if (!event.target.classList.contains('remove-block')) return;
        if (container.querySelectorAll('.block-row').length > 1) {
            event.target.closest('.block-row').remove();
            renumber();
        }
    });

    renumber();
})();
</script>
@endsection
