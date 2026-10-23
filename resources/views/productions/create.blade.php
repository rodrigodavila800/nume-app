@extends('layouts.app')

@section('title', __('NUME | Nova Produção'))
@section('breadcrumb', __('Nova Produção'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Criar Nova Produção') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Defina o escopo, cliente vinculado e diretrizes estratégicas.') }}</p>
        </div>
        <a href="{{ route('productions.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar às produções') }}
        </a>
    </div>

    <!-- Form Card -->
    <form action="{{ route('productions.store') }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf

        <!-- Título -->
        <div class="space-y-1.5">
            <label for="title" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Título da Produção') }} <span class="text-orange-500">*</span>
            </label>
            <input type="text" name="title" id="title" required
                   placeholder="{{ __('Ex: Vídeo Manifesto Institucional Q3') }}"
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('title') border-rose-500 @enderror"
                   value="{{ old('title') }}">
            @error('title') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Descrição -->
        <div class="space-y-1.5">
            <label for="description" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Descrição & Objetivo') }}
            </label>
            <textarea name="description" id="description" rows="4"
                      placeholder="{{ __('Descreva o público-alvo, formato e objetivo de conversão do vídeo...') }}"
                      class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('description') border-rose-500 @enderror">{{ old('description') }}</textarea>
            @error('description') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Grid 2 colunas: Cliente & Data -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label for="cliente_id" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Cliente Vinculado') }}
                </label>
                <select name="cliente_id" id="cliente_id"
                        class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition @error('cliente_id') border-rose-500 @enderror">
                    <option value="">— {{ __('Sem cliente vinculado (Produção interna)') }} —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id') == $cliente->id)>{{ $cliente->name }}</option>
                    @endforeach
                </select>
                @error('cliente_id') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label for="target_publication_date" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Data de Entrega Alvo') }}
                </label>
                <input type="date" name="target_publication_date" id="target_publication_date"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition @error('target_publication_date') border-rose-500 @enderror"
                       value="{{ old('target_publication_date') }}">
                @error('target_publication_date') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Palavras-chave SEO -->
        <div class="space-y-1.5">
            <label for="seo_keywords" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Palavras-chave & Tags SEO') }}
            </label>
            <input type="text" name="seo_keywords" id="seo_keywords"
                   placeholder="{{ __('b2b, branding, video marketing, youtube...') }}"
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('seo_keywords') border-rose-500 @enderror"
                   value="{{ old('seo_keywords') }}">
            <p class="text-[11px] text-zinc-500">{{ __('Separe as tags por vírgula.') }}</p>
            @error('seo_keywords') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Criar Produção') }}
            </button>
            <a href="{{ route('productions.index') }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
